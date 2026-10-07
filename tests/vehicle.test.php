<?php
// No DDL or DML is sent to MySQL. Writes below use a pure memory double.
require_once __DIR__ . '/../backend/app/models/Vehicle.php';
$count = 0;
function vehicleCheck($value, $label) { global $count; if (!$value) throw new RuntimeException($label); $count++; }
function vehicleReject($callback, $code, $field = null) {
    try { $callback(); } catch (DomainException $e) {
        vehicleCheck($e->getCode() === $code, 'Expected code ' . $code);
        if ($field) vehicleCheck($e instanceof VehicleValidationException && isset($e->fields[$field]), 'Field error '.$field);
        return;
    }
    throw new RuntimeException('Expected rejection');
}
$valid = ['license_plate'=>" 51c - 123.45\u{00a0}", 'vehicle_type'=>' Xe tải ', 'brand'=>' ', 'capacity'=>'1250.50', 'status'=>'available'];
$data = Vehicle::validate($valid);
vehicleCheck($data['license_plate']==='51C-123.45', 'Normalize spaces/NBSP/case');
vehicleCheck($data['brand']===null && $data['vehicle_type']==='Xe tải', 'Optional brand / trim');
vehicleCheck($data['capacity']==='1250.50','Decimal preserved');
foreach ([['license_plate'=>[]],['license_plate'=>'   '],['license_plate'=>'<script>'],['license_plate'=>str_repeat('A',31)],['vehicle_type'=>[]],['vehicle_type'=>' '],['vehicle_type'=>str_repeat('Đ',101)],['brand'=>[]],['brand'=>str_repeat('Đ',101)],['brand'=>"A\0B"],['capacity'=>'0'],['capacity'=>'-1'],['capacity'=>'100000000'],['capacity'=>'1.001'],['capacity'=>'1e2'],['capacity'=>[]],['capacity'=>true],['capacity'=>'NaN'],['status'=>[]],['status'=>'other']] as $override) {
    vehicleReject(fn()=>Vehicle::validate(array_merge($valid,$override)),422,array_key_first($override));
}
foreach (Vehicle::STATUSES as $status) vehicleCheck(Vehicle::validate(array_merge($valid,['status'=>$status]))['status']===$status,'Enum');
vehicleCheck(Vehicle::validate(array_merge($valid,['capacity'=>'99999999.99']))['capacity']==='99999999.99','Decimal limit');
vehicleCheck(Vehicle::positiveId('18446744073709551615')==='18446744073709551615','BIGINT string');
foreach (['18446744073709551616','0','-1',1.2,[],null] as $id) vehicleReject(fn()=>Vehicle::positiveId($id),422);
foreach (['assigned','completed','cancelled'] as $status) vehicleReject(fn()=>Vehicle::assertChangeAllowed(['status'=>'available'],[['status'=>$status]],[],null,true),409);
foreach (['PLANNED','IN_PROGRESS','COMPLETED','CANCELLED'] as $status) vehicleReject(fn()=>Vehicle::assertChangeAllowed(['status'=>'available'],[],[['status'=>$status]],null,true),409);
vehicleReject(fn()=>Vehicle::assertChangeAllowed(['status'=>'on_trip'],[],[],null,true),409);
vehicleReject(fn()=>Vehicle::assertChangeAllowed(['status'=>'available'],[['status'=>'assigned']],[],'inactive',false),409,'status');
foreach (['PLANNED','IN_PROGRESS'] as $status) { Vehicle::assertChangeAllowed(['status'=>'available'],[],[['status'=>$status]],'maintenance',false);vehicleCheck(true,'Trip alone does not reserve'); }
Vehicle::assertChangeAllowed(['status'=>'on_trip'],[['status'=>'assigned']],[['status'=>'IN_PROGRESS']],'on_trip',false);
Vehicle::assertChangeAllowed(['status'=>'available'],[['status'=>'completed']],[['status'=>'COMPLETED']],'maintenance',false);
vehicleCheck(true,'Same-status edit and historical-only status change allowed');
class VehicleMemoryConnection {
    public $rows=[], $assignments=[], $trips=[], $commits=0, $rollbacks=0, $fail=false, $sql=[];
    private $active=false, $snapshot;
    public function prepare($sql) { $this->sql[]=$sql; return new VehicleMemoryStatement($this,$sql); }
    public function beginTransaction() { $this->active=true; $this->snapshot=$this->rows; }
    public function inTransaction() { return $this->active; }
    public function commit() { $this->commits++; $this->active=false; }
    public function rollBack() { $this->rollbacks++; $this->rows=$this->snapshot; $this->active=false; }
    public function lastInsertId() { return '9007199254740993'; }
}
class VehicleMemoryStatement {
    private $db,$sql,$rows=[];
    public function __construct($db,$sql) { $this->db=$db; $this->sql=$sql; }
    public function execute($p=[]) {
        if (str_starts_with($this->sql,'INSERT INTO vehicles')) $this->db->rows['9007199254740993']=$p+['vehicle_id'=>'9007199254740993','has_active_assignment'=>false,'has_active_trip'=>false];
        elseif (str_starts_with($this->sql,'UPDATE vehicles')) {
            if ($this->db->fail) throw new RuntimeException('Injected memory failure');
            $this->db->rows[$p['vehicle_id']]=array_merge($this->db->rows[$p['vehicle_id']],$p);
        } elseif (str_starts_with($this->sql,'DELETE FROM vehicles')) unset($this->db->rows[$p[0]]);
        elseif (str_starts_with($this->sql,'SELECT assignment_id, status FROM assignments')) $this->rows=$this->db->assignments;
        elseif (str_starts_with($this->sql,'SELECT id, status FROM trips')) $this->rows=$this->db->trips;
        elseif (str_starts_with($this->sql,'SELECT vehicle_id, license_plate')) $this->rows=array_values($this->db->rows);
        else { $row=$this->db->rows[$p[0]]??false; $this->rows=$row?[$row]:[]; }
        return true;
    }
    public function fetch() { return $this->rows[0]??false; }
    public function fetchAll() { return $this->rows; }
}
function memoryVehicle($db) { $r=new ReflectionClass(Vehicle::class);$v=$r->newInstanceWithoutConstructor();$r->getProperty('conn')->setValue($v,$db);return $v; }
$db=new VehicleMemoryConnection(); $v=memoryVehicle($db); $result=$v->create($data); $id=$result['vehicle_id'];
vehicleCheck($id==='9007199254740993' && is_string($result['capacity']),'Create DTO no BIGINT rounding');
vehicleReject(fn()=>$v->create($data),409,'license_plate');
$db->rows[$id]['license_plate']=' 51c - 123.45 ';
vehicleReject(fn()=>$v->create($data),409,'license_plate');
$updated=$v->change($id,array_merge($data,['capacity'=>'2000']));
vehicleCheck($updated['capacity']==='2000' && $db->commits===1,'Memory update committed');
$db->assignments=[['status'=>'completed']]; $before=$db->rows;
vehicleReject(fn()=>$v->change($id,null,true),409);
vehicleCheck($db->rows===$before && $db->rollbacks===1,'History delete leaves rows unchanged');
$db->assignments=[];$db->trips=[['status'=>'PLANNED']];
$v->change($id,array_merge($data,['status'=>'maintenance']));
$before=$db->rows;
$db->trips=[];$db->fail=true;
try { $v->change($id,$data); throw new LogicException('Expected injected failure'); } catch (RuntimeException $e) { vehicleCheck($db->rows===$before && !$db->inTransaction(),'Update failure rolled back'); }
$db->fail=false;$v->change($id,null,true);
vehicleCheck(!$db->rows && $db->commits===3,'Unused vehicle memory delete');
vehicleReject(fn()=>$v->change($id,$data),404);
vehicleCheck(!array_filter($db->sql,fn($sql)=>preg_match('/^(INSERT INTO|UPDATE|DELETE FROM) (trips|assignments)/',$sql)),'No writes to trips/assignments');
echo "PASS: $count Vehicle validation/policy/memory CRUD checks; no MySQL writes.\n";
if (!in_array('--read-only-mysql',$argv,true)) exit;
$pdo=new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
// Only SELECTs in this section; no transaction and no DML, even rolled back.
$model=new Vehicle($pdo);$rows=$model->getAll();$stats=$model->stats();
vehicleCheck($stats['total']===count($rows),'Real aggregate and list agree');
foreach (Vehicle::STATUSES as $status) { $filtered=$model->getAll('',$status);vehicleCheck(count($filtered)===$stats[$status],'Real status filter '.$status); }
if ($rows) { vehicleCheck($model->findById($rows[0]['vehicle_id'])['vehicle_id']===$rows[0]['vehicle_id'],'Real show');vehicleCheck(count($model->getAll($rows[0]['license_plate']))>=1,'Real search'); }
vehicleCheck($model->getAll('Vehicle%_NoMatch!')===[],'LIKE escape');
$users=$pdo->query('SELECT id,role,status FROM users')->fetchAll();
foreach ($users as $user) vehicleCheck($model->isManager((string)$user['id'])===(in_array($user['role'],['ADMIN','STAFF'],true)&&(int)$user['status']===1),'Real role/status guard');
vehicleCheck(!$model->isManager('18446744073709551615'),'Nonexistent user denied');
echo "PASS: $count total checks including real MySQL SELECTs only.\n";
