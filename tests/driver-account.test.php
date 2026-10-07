<?php
// Default: validation + in-memory transactions. --mysql: real SQL, every test rolls back.
// No DDL, no changes to existing users, no committed test accounts/profiles.
require_once __DIR__ . '/../backend/app/controllers/DriverController.php';
class AccountValidationProbe extends DriverController
{
    protected function error($message, $statusCode = 400) { throw new DomainException($message, $statusCode); }
}
$count = 0;
function accountCheck($condition, $label) {
    global $count;
    if (!$condition) throw new RuntimeException($label);
    $count++;
}
function accountRejected($callback, $code) {
    try { $callback(); } catch (DomainException $e) {
        accountCheck($e->getCode() === $code, 'Unexpected error code'); return;
    }
    throw new RuntimeException('Expected rejection');
}
$controller = new AccountValidationProbe();
$validate = new ReflectionMethod(DriverController::class, 'validate');
$validateAccount = new ReflectionMethod(DriverController::class, 'validateAccount');
$data = ['driver_code'=>'ACCOUNT-PROBE', 'full_name'=>'Tài xế kiểm thử', 'phone'=>'0901234567', 'address'=>null, 'license_number'=>'ACCOUNT-LICENSE', 'license_class'=>'C', 'license_expiry'=>null, 'status'=>'available', 'username'=>'driver-probe', 'password'=>'  initial-test  '];
$profile = $validate->invoke($controller, $data);
$account = $validateAccount->invoke($controller, $data);
accountCheck($account['password'] === $data['password'], 'Password must not be trimmed');
accountCheck($account['username'] === 'driver-probe', 'Username normalized');
accountCheck(!array_key_exists('password', $profile) && !array_key_exists('username', $profile), 'Profile excludes credentials');
foreach ([['username'=>null],['username'=>[]],['username'=>' '],['username'=>str_repeat('Đ',51)],['username'=>"na\nme"],['password'=>null],['password'=>[]],['password'=>''],['password'=>'  '],['password'=>str_repeat('a',73)],['password'=>str_repeat('Đ',37)],['password'=>"has\0null"],['user_id'=>'1'],['full_name'=>str_repeat('Đ',101)]] as $override) {
    accountRejected(fn() => $validateAccount->invoke($controller,array_merge($data,$override)),422);
}
accountCheck(strlen($validateAccount->invoke($controller,array_merge($data,['password'=>str_repeat('Đ',36)]))['password'])===72,'72-byte boundary');
accountCheck(mb_strlen($validateAccount->invoke($controller,array_merge($data,['username'=>str_repeat('Đ',50)]))['username'])===50,'Username Unicode length');

class AccountMemoryConnection
{
    public $users = [], $drivers = [], $rollbacks = 0, $commits = 0, $fail = null;
    private $active = false, $snapshot, $lastId;
    public function beginTransaction() { $this->active=true; $this->snapshot=[$this->users,$this->drivers]; }
    public function inTransaction() { return $this->active; }
    public function commit() { $this->active=false; $this->commits++; }
    public function rollBack() { [$this->users,$this->drivers]=$this->snapshot; $this->active=false; $this->rollbacks++; }
    public function lastInsertId() { return (string)$this->lastId; }
    public function prepare($sql) { return new AccountMemoryStatement($this,$sql); }
    public function insertUser($params) {
        if ($this->fail==='user-race') {
            $e=new PDOException('Duplicate');$e->errorInfo=['23000',1062,'Duplicate'];throw $e;
        }
        $this->lastId=101;
        $this->users[101]=array_merge($params,['id'=>101,'role'=>'DRIVER','status'=>1]);
    }
    public function insertDriver($params) {
        if ($this->fail==='driver') throw new RuntimeException('Injected driver insertion failure');
        $this->lastId='9007199254740993';
        $this->drivers[$this->lastId]=array_merge($params,['driver_id'=>$this->lastId,'has_active_assignment'=>false,'has_active_trip'=>false]);
    }
}
class AccountMemoryStatement
{
    private $db,$sql,$rows=[];
    public function __construct($db,$sql) { $this->db=$db;$this->sql=$sql; }
    public function execute($params) {
        if (str_starts_with($this->sql,'INSERT INTO users')) {
            accountCheck(str_contains($this->sql,"'DRIVER', 1"),'Server fixes account role/status');
            $this->db->insertUser($params);
        } elseif (str_starts_with($this->sql,'INSERT INTO drivers')) $this->db->insertDriver($params);
        elseif (str_contains($this->sql,'FROM users WHERE username')) {
            $this->rows=array_values(array_filter($this->db->users,fn($user)=>mb_strtolower($user['username'])===mb_strtolower($params[0])));
        } elseif (str_contains($this->sql,'FROM users')) {
            $user=$this->db->users[$params[0]]??null;$this->rows=$user?[$user]:[];
        } else {
            if ($this->db->fail==='read') throw new RuntimeException('Injected failure after both inserts');
            $driver=$this->db->drivers[$params[0]]??null;$this->rows=$driver?[$driver]:[];
        }
        return true;
    }
    public function fetch() { return $this->rows[0]??false; }
}
function memoryDriver($db) {
    $reflection=new ReflectionClass(Driver::class);
    $model=$reflection->newInstanceWithoutConstructor();
    $reflection->getProperty('conn')->setValue($model,$db);
    return $model;
}
$db=new AccountMemoryConnection();
$result=memoryDriver($db)->createWithAccount($profile,$account);
accountCheck($db->commits===1 && $db->rollbacks===0,'One committed transaction');
accountCheck($result['user_id']==='101','Linked account id stays string');
accountCheck(password_verify($account['password'],$db->users[101]['password']),'Hash compatible with login');
accountCheck(!password_verify(trim($account['password']),$db->users[101]['password']),'Leading/trailing password spaces preserved');
accountCheck($db->users[101]['role']==='DRIVER' && $db->users[101]['status']===1,'Correct role/status');
accountCheck(!array_key_exists('password',$result) && !array_key_exists('username',$result),'No credentials in response');
$baseline=$db->users;
accountRejected(fn()=>memoryDriver($db)->createWithAccount($profile,$account),409);
accountCheck($db->users===$baseline && count($db->drivers)===1,'Duplicate username preserves existing rows');
foreach (['user-race','driver','read'] as $stage) {
    $db=new AccountMemoryConnection();$db->fail=$stage;
    try { memoryDriver($db)->createWithAccount($profile,$account); throw new LogicException('Failure not injected'); }
    catch (Throwable $e) {
        accountCheck($db->rollbacks===1 && $db->commits===0,'Rollback at '.$stage);
        accountCheck(!$db->users && !$db->drivers,'No orphan at '.$stage);
        if ($stage==='user-race') accountCheck($e instanceof DomainException && $e->getCode()===409,'Concurrent username duplicate mapped');
    }
}
echo 'PASS: '.$count." account validation/hash/transaction assertions (memory).\n";

if (!in_array('--mysql',$argv,true)) exit;
// Same production SQL on MySQL. Commit is intercepted to verify the staged pair then roll back.
class RollbackOnlyAccountPDO extends PDO
{
    public $onBegin = null, $beforeCommit = null, $fault = null, $prefix, $boundaries = 0;
    public function beginTransaction(): bool {
        $result=parent::beginTransaction();
        if ($this->onBegin) ($this->onBegin)($this);
        return $result;
    }
    public function commit(): bool {
        if ($this->beforeCommit) ($this->beforeCommit)($this);
        $this->boundaries++;
        return parent::rollBack();
    }
    public function prepare(string $query, array $options=[]): PDOStatement|false {
        if ($this->fault==='driver' && str_starts_with($query,'INSERT INTO drivers')) {
            accountCheck((int)$this->query("SELECT COUNT(*) FROM users WHERE username LIKE '".$this->prefix."%' ")->fetchColumn()===1,'User inserted before injected driver fault');
            throw new RuntimeException('Injected between users and drivers');
        }
        if ($this->fault==='read' && str_starts_with($query,'SELECT d.*')) {
            accountCheck((int)$this->query("SELECT COUNT(*) FROM drivers WHERE driver_code LIKE '".$this->prefix."%' ")->fetchColumn()===1,'Both rows inserted before injected read fault');
            throw new RuntimeException('Injected after both inserts');
        }
        return parent::prepare($query,$options);
    }
}
$mysql=new RollbackOnlyAccountPDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$engines=$mysql->query("SELECT TABLE_NAME,ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('users','drivers')")->fetchAll(PDO::FETCH_KEY_PAIR);
accountCheck(count($engines)===2 && count(array_filter($engines,fn($engine)=>strtoupper($engine)==='INNODB'))===2,'Both tables support rollback');
$prefix='DAProbe'.bin2hex(random_bytes(8));
$mysql->prefix=$prefix;
$profile['driver_code']=$prefix.'TX';$profile['license_number']=$prefix.'LIC';$account['username']=$prefix.'Login';
function noProbeRows($db,$prefix) {
    foreach (['users'=>'username','drivers'=>'driver_code'] as $table=>$column) {
        $stmt=$db->prepare("SELECT COUNT(*) FROM $table WHERE $column LIKE ?");$stmt->execute([$prefix.'%']);
        accountCheck((int)$stmt->fetchColumn()===0,'No persisted test rows in '.$table);
    }
    accountCheck(!$db->inTransaction(),'No open transaction');
}
try {
    $mysql->beforeCommit=function($db) use ($account,$profile) {
        $stmt=$db->prepare('SELECT * FROM users WHERE username=?');$stmt->execute([$account['username']]);$user=$stmt->fetch();
        $stmt=$db->prepare('SELECT * FROM drivers WHERE driver_code=?');$stmt->execute([$profile['driver_code']]);$driver=$stmt->fetch();
        accountCheck($user && $driver && (string)$driver['user_id']===(string)$user['id'],'Real linked records staged together');
        accountCheck($user['role']==='DRIVER' && (int)$user['status']===1,'Real DRIVER account active');
        accountCheck(password_verify($account['password'],$user['password']),'Real stored hash verifies');
    };
    $result=(new Driver($mysql))->createWithAccount($profile,$account);
    accountCheck($mysql->boundaries===1 && !isset($result['password']),'Reached success boundary without exposing password');
    noProbeRows($mysql,$prefix);
    $mysql->beforeCommit=null;
    $mysql->onBegin=function($db) use ($account) {
        $stmt=$db->prepare("INSERT INTO users (username,password,full_name,role,status) VALUES (?,?,?,'DRIVER',1)");
        $stmt->execute([$account['username'],password_hash('seed-only',PASSWORD_DEFAULT),'Tài xế kiểm thử']);
    };
    $duplicate=$account;$duplicate['username']=strtoupper($account['username']);
    accountRejected(fn()=>(new Driver($mysql))->createWithAccount($profile,$duplicate),409);
    noProbeRows($mysql,$prefix);
    $mysql->onBegin=function($db) use ($profile,$prefix) {
        $stmt=$db->prepare("INSERT INTO drivers (driver_code,full_name,phone,license_number,license_class,status) VALUES (?,?,?,?,?,'available')");
        $stmt->execute([$profile['driver_code'],$profile['full_name'],$profile['phone'],$prefix.'SeedLIC','C']);
    };
    try { (new Driver($mysql))->createWithAccount($profile,$account); throw new LogicException('Expected real unique conflict'); }
    catch (PDOException $e) { accountCheck((int)($e->errorInfo[1]??0)===1062,'Real profile duplicate rejects after account insert'); }
    noProbeRows($mysql,$prefix);
    $mysql->onBegin=null;
    foreach (['driver','read'] as $fault) {
        $mysql->fault=$fault;
        try { (new Driver($mysql))->createWithAccount($profile,$account); throw new LogicException('Expected injected fault'); }
        catch (LogicException $e) { throw $e; }
        catch (RuntimeException $e) { accountCheck(str_starts_with($e->getMessage(),'Injected'),'Expected fault'); }
        $mysql->fault=null;
        noProbeRows($mysql,$prefix);
    }
} finally {
    if ($mysql->inTransaction()) $mysql->rollBack();
}
echo 'PASS: MySQL real INSERT/SELECT, username/profile UNIQUE conflicts, and injected faults rollback; no test rows committed. Successful COMMIT persistence and HTTP authenticated flow are not tested. Auto-increment may advance. Total assertions: '.$count."\n";
