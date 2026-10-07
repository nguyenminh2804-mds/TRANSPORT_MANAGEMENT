<?php
// Không INSERT/UPDATE/DELETE/DDL. Validation/policy độc lập + SELECT schema v2 thực tế.
require_once __DIR__ . '/../backend/app/controllers/DriverController.php';
class ValidationProbe extends DriverController
{
    protected function error($message, $statusCode = 400)
    {
        throw new DomainException($message, $statusCode);
    }
}
$count = 0;
function check($condition, $message)
{
    global $count;
    if (!$condition) {
        throw new RuntimeException($message);
    }
    $count++;
}
function rejected($callback, $code)
{
    try {
        $callback();
    } catch (DomainException $e) {
        check($e->getCode() === $code, $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected rejection ' . $code);
}
$probe = new ValidationProbe();
$validate = new ReflectionMethod(DriverController::class, 'validate');
$id = new ReflectionMethod(DriverController::class, 'positiveId');
$data = ['driver_code'=>'TX001', 'full_name'=>'Nguyễn Văn A', 'phone'=>'0901234567', 'address'=>null, 'license_number'=>'123456789012', 'license_class'=>'C', 'license_expiry'=>'2028-02-29', 'status'=>'available', 'user_id'=>null];
$result = $validate->invoke($probe, $data);
check($result['full_name'] === $data['full_name'], 'Unicode name');
check($result['address'] === null && $result['user_id'] === null, 'Nullable fields');
check($result['license_expiry'] === '2028-02-29', 'Leap date');
check($validate->invoke($probe, array_merge($data, ['license_class'=>'Hạng mới']))['license_class'] === 'HẠNG MỚI', 'No fixed license allowlist');
check($validate->invoke($probe, array_merge($data, ['full_name'=>str_repeat('Đ', 150)]))['full_name'] === str_repeat('Đ', 150), '150 Unicode chars');
foreach (['driver_code'=>51, 'full_name'=>151, 'phone'=>21, 'license_number'=>51, 'license_class'=>31, 'address'=>256] as $field=>$length) {
    rejected(fn() => $validate->invoke($probe, array_merge($data, [$field=>str_repeat('a', $length)])), 422);
}
foreach ([['full_name'=>' '], ['driver_code'=>[]], ['phone'=>'abc'], ['status'=>'AVAILABLE'], ['license_expiry'=>'2027-02-29'], ['license_expiry'=>'2026-13-01'], ['license_expiry'=>'2026-10-07abc'], ['license_expiry'=>[]], ['address'=>[]], ['user_id'=>'0'], ['user_id'=>1.5]] as $override) {
    rejected(fn() => $validate->invoke($probe, array_merge($data, $override)), 422);
}
check($validate->invoke($probe, array_merge($data, ['address'=>'', 'license_expiry'=>'']))['license_expiry'] === null, 'Blank date -> null');
check($validate->invoke($probe, array_merge($data, ['license_expiry'=>'2020-01-01']))['license_expiry'] === '2020-01-01', 'Expired profile can be recorded');
check($id->invoke($probe, '18446744073709551615') === '18446744073709551615', 'Max unsigned bigint preserved');
rejected(fn() => $id->invoke($probe, '18446744073709551616'), 422);
rejected(fn() => $id->invoke($probe, '-1'), 422);
foreach (['assigned', 'completed', 'cancelled'] as $status) {
    rejected(fn() => Driver::assertChangeAllowed(['status'=>'available'], [['status'=>$status]], null, true), 409);
}
foreach (['available', 'inactive'] as $target) {
    rejected(fn() => Driver::assertChangeAllowed(['status'=>'on_trip'], [['status'=>'assigned']], $target, false), 409);
}
Driver::assertChangeAllowed(['status'=>'on_trip'], [['status'=>'assigned']], 'on_trip', false);
check(true, 'Active assignment allows profile edit with unchanged status');
Driver::assertChangeAllowed(['status'=>'available'], [['status'=>'completed'], ['status'=>'cancelled']], 'inactive', false);
check(true, 'Historical assignments allow status change');
Driver::assertChangeAllowed(['status'=>'available'], [], null, true);
check(true, 'Unused available driver can be deleted');
rejected(fn() => Driver::assertChangeAllowed(['status'=>'on_trip'], [], null, true), 409);
// Chuyến trực tiếp phải được bảo vệ bên cạnh assignments.
foreach (['PLANNED','IN_PROGRESS','COMPLETED','CANCELLED'] as $status) {
    rejected(fn() => Driver::assertChangeAllowed(['status'=>'available'], [], null, true, [['status'=>$status]]), 409);
}
foreach (['PLANNED','IN_PROGRESS'] as $status) {
    Driver::assertChangeAllowed(['status'=>'on_trip'], [], 'available', false, [['status'=>$status]]);
    check(true, 'Trip status alone does not reserve resources');
}
Driver::assertChangeAllowed(['status'=>'available'], [], 'inactive', false, [['status'=>'COMPLETED']]);
check(true, 'Completed direct trip allows status update');

// Test double trong bộ nhớ: không mở kết nối, không chạy SQL trên MySQL.
class MemoryConnection
{
    public $driver;
    public $assignments = [];
    public $trips = [];
    public $writes = [];
    public $commits = 0;
    public $rollbacks = 0;
    public $failWrite = false;
    private $active = false;
    private $snapshot;
    public function beginTransaction() { $this->active=true; $this->snapshot=$this->driver; }
    public function inTransaction() { return $this->active; }
    public function commit() { $this->active=false; $this->commits++; }
    public function rollBack() { $this->driver=$this->snapshot; $this->active=false; $this->rollbacks++; }
    public function prepare($sql) { return new MemoryStatement($this, $sql); }
    public function lastInsertId() { return '42'; }
}
class MemoryStatement
{
    private $db;
    private $sql;
    private $rows = [];
    public function __construct($db, $sql) { $this->db=$db; $this->sql=$sql; }
    public function execute($params)
    {
        $sql=$this->sql;
        if (str_starts_with($sql, 'SELECT')) {
            if (str_contains($sql, 'FROM assignments WHERE')) $this->rows=$this->db->assignments;
            elseif (str_contains($sql, 'FROM trips WHERE')) $this->rows=$this->db->trips;
            elseif (str_contains($sql, 'FROM users')) $this->rows=[];
            else {
                $driver=$this->db->driver;
                if ($driver && str_contains($sql, 'has_active_assignment')) {
                    $driver['has_active_assignment']=count(array_filter($this->db->assignments, fn($a)=>$a['status']==='assigned'))>0;
                    $driver['has_active_trip']=count(array_filter($this->db->trips, fn($t)=>in_array($t['status'], ['PLANNED','IN_PROGRESS'], true)))>0;
                }
                $this->rows=$driver ? [$driver] : [];
            }
        } else {
            if (!preg_match('/^(INSERT INTO|UPDATE|DELETE FROM) drivers\b/', $sql)) throw new RuntimeException('Unexpected write outside drivers');
            $this->db->writes[]=[$sql,$params];
            if ($this->db->failWrite) throw new RuntimeException('Simulated write failure');
            if (str_starts_with($sql, 'INSERT')) $this->db->driver=array_merge($params,['driver_id'=>'42']);
            elseif (str_starts_with($sql, 'DELETE')) $this->db->driver=null;
            elseif (str_contains($sql, 'SET status=?')) $this->db->driver['status']=$params[0];
            else $this->db->driver=array_merge($this->db->driver,$params);
        }
        return true;
    }
    public function fetch() { return $this->rows[0] ?? false; }
    public function fetchAll() { return $this->rows; }
}
function memoryModel($driver, $assignments=[], $trips=[])
{
    $db=new MemoryConnection();
    $db->driver=$driver;
    $db->assignments=$assignments;
    $db->trips=$trips;
    $model=(new ReflectionClass(Driver::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty(Driver::class,'conn'))->setValue($model,$db);
    return [$model,$db];
}
[$memory,$db]=memoryModel(null);
$created=$memory->create($data);
check($created['driver_id']==='42' && $created['driver_code']==='TX001', 'Memory create v2');
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']));
$updated=$memory->change('42',array_merge($data,['full_name'=>'Đã sửa']));
check($updated['full_name']==='Đã sửa' && $db->commits===1, 'Profile update commits');
$updated=$memory->change('42',null,'inactive');
check($updated['status']==='inactive' && $db->commits===2, 'Status update commits');
check($memory->change('42',null,null,true)===[] && $db->driver===null, 'Unused delete');
foreach ([['assigned'],['completed'],['cancelled']] as [$state]) {
    [$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']), [['assignment_id'=>'1','status'=>$state]]);
    rejected(fn()=>$memory->change('42',null,null,true),409);
    check($db->writes===[] && $db->rollbacks===1, 'Assignment history rollback without write');
}
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42','status'=>'on_trip']),[['assignment_id'=>'1','status'=>'assigned']]);
rejected(fn()=>$memory->change('42',null,'inactive'),409);
check($db->writes===[], 'Active assignment blocks status before write');
$updated=$memory->change('42',array_merge($data,['status'=>'on_trip','full_name'=>'Hồ sơ mới']));
check($updated['full_name']==='Hồ sơ mới' && $updated['status']==='on_trip', 'Active profile edit preserves status');
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']),[],[['id'=>1,'status'=>'PLANNED']]);
$memory->change('42',null,'inactive');
check(count($db->writes)>0, 'Trip alone no longer reserves resources');
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']),[],[['id'=>1,'status'=>'COMPLETED']]);
rejected(fn()=>$memory->change('42',null,null,true),409);
check($db->writes===[], 'Direct trip history blocks delete');
[$memory,$db]=memoryModel(null);
rejected(fn()=>$memory->change('42',null,'inactive'),404);
check($db->rollbacks===1 && $db->writes===[], 'Missing driver rollback');
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']));
$db->failWrite=true;
try { $memory->change('42',null,'inactive'); throw new LogicException('Expected failure'); }
catch (RuntimeException $e) { check($e->getMessage()==='Simulated write failure' && $db->driver['status']==='available' && $db->rollbacks===1, 'Write failure rollback'); }
[$memory,$db]=memoryModel(array_merge($data,['driver_id'=>'42']));
rejected(fn()=>$memory->change('42',array_merge($data,['user_id'=>'999'])),422);
check($db->writes===[] && $db->rollbacks===1, 'Invalid linked user rejected before write');

// Chỉ gọi các phương thức đọc trên database đang có.
$model = new Driver();
foreach ([['',''], ['%','available'], ['TX','inactive'], ['!_','on_trip']] as [$search,$status]) {
    $rows = $model->getAll($search, $status);
    check(is_array($rows), 'v2 SELECT search/status');
}
$rows = $model->getAll('', '');
if ($rows) {
    $driver = $model->findById($rows[0]['driver_id']);
    check(is_string($driver['driver_id']), 'API ID is string');
    check(is_bool($driver['has_active_assignment']), 'Active flag is boolean');
} else {
    check($model->findById('18446744073709551615') === false, 'Read missing driver');
}
echo 'PASS: ' . $count . " validation/policy/SELECT checks. No database writes.\n";
