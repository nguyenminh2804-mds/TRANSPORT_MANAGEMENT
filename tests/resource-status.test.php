<?php
// Shared lifecycle and API model checks using memory only; no MySQL connection.
require_once __DIR__.'/driver-portal.test.php';
require_once __DIR__.'/../backend/app/models/Driver.php';
require_once __DIR__.'/../backend/app/models/Vehicle.php';
class ResourceMemory extends PortalMemory {
 public function lastInsertId(){return '1';}
 public function query($sql){$s=$this->prepare($sql);$s->execute([]);return $s;}
 public function prepare($sql){$this->sql[]=$sql;return new ResourceStatement($this,$sql);}
 public function run($sql,$p){
  if(str_starts_with($sql,'SELECT id FROM users')&&str_contains($sql,"role IN ('ADMIN','STAFF')"))return [['id'=>$p[0]]];
  if(str_starts_with($sql,'SELECT * FROM drivers'))return [$this->d];
  if(str_starts_with($sql,'SELECT * FROM assignments')&&count($p)===1)return isset($this->a['assignment_id'])&&$this->a['assignment_id']===$p[0]?[$this->a]:[];
  if(str_starts_with($sql,'SELECT assignment_id FROM assignments'))return ($this->a['status']??null)==='assigned'?[$this->a]:($this->otherActive?[['assignment_id'=>'2']]:[]);
  if(str_starts_with($sql,'INSERT INTO assignments')){$this->a=['assignment_id'=>'1','trip_id'=>$p[0],'driver_id'=>$p[1],'vehicle_id'=>$p[2],'assigned_by'=>$p[3],'assigned_at'=>'2026-10-13 07:00:00','accepted_at'=>null,'completed_at'=>null,'cancelled_at'=>null,'status'=>'assigned','notes'=>$p[4]];return [];}
  if(str_starts_with($sql,'UPDATE trips SET driver_id')){$this->t['driver_id']=$p[0];$this->t['vehicle_id']=$p[1];return [];}
  if(str_starts_with($sql,'UPDATE assignments SET notes')){$this->a['notes']=$p[0];return [];}
  if(str_starts_with($sql,'UPDATE assignments SET status=?')){$this->a['status']=$p[0];$this->a[$p[0]==='completed'?'completed_at':'cancelled_at']='2026-10-13 09:00:00';return [];}

  if(str_starts_with($sql,'SELECT d.*')||str_starts_with($sql,'SELECT v.*')){
   $driver=str_starts_with($sql,'SELECT d.*');$alias=$driver?'d':'v';$key=$driver?'driver_id':'vehicle_id';
   pc(str_contains($sql,ResourceStatus::sql($alias,$key)),'API selects derived SQL');
   $row=$driver?$this->d+['user_id'=>'7']:$this->v+['license_plate'=>'51C','vehicle_type'=>'Truck','brand'=>null,'capacity'=>'1000'];
   $active=$this->a['status']==='assigned';$row['has_active_assignment']=$row['has_active_trip']=$active;
   $row['display_status']=ResourceStatus::resolve($row['status'],$active?$this->a:null,$this->t);
   if(isset($p['status'])){pc(str_contains($sql,' AND (CASE WHEN'),'Filter uses derived SQL, not stored status');if($p['status']!==$row['display_status'])return [];}
   return [$row];
  }
  if(str_starts_with($sql,'SELECT display_status AS status')){pc(str_contains($sql,'GROUP BY display_status'),'Vehicle stats group derived SQL');$status=ResourceStatus::resolve($this->v['status'],$this->a['status']==='assigned'?$this->a:null,$this->t);return [['status'=>$status,'amount'=>1]];}
  return parent::run($sql,$p);
 }
}
class ResourceStatement {private $db,$sql,$rows;function __construct($db,$sql){$this->db=$db;$this->sql=$sql;}function execute($p=[]){$this->rows=$this->db->run($this->sql,$p);}function fetchAll(){return $this->rows;}function fetch(){return $this->rows[0]??false;}}
function model($class,$db){$r=new ReflectionClass($class);$m=$r->newInstanceWithoutConstructor();$r->getProperty($class===Assignment::class?'db':'conn')->setValue($m,$db);return $m;}
function checkResources($db,$expected,$phase){$d=model(Driver::class,$db);$v=model(Vehicle::class,$db);$dr=$d->getAll('','')[0];$vr=$v->getAll('','')[0];pc($dr['display_status']===$expected&&$vr['display_status']===$expected,'Both API lists '.$phase);pc($d->findById('10')['display_status']===$expected&&$v->findById('20')['display_status']===$expected,'Both details '.$phase);pc(count($d->getAll('',$expected))===1&&count($v->getAll('',$expected))===1,'Both filters '.$phase);pc($v->stats()[$expected]===1&&$v->stats()['total']===1,'Vehicle stats '.$phase);pc(Assignment::phase($db->a+['trip_status'=>$db->t['status']])===$phase,'Assignment API phase '.$phase);if($expected!=='available')pc(!$d->getAll('','available')&&!$v->getAll('','available')&&$v->stats()['available']===0,'Held resources excluded from ready');}
$db=new ResourceMemory();$db->a=['status'=>'none'];$staff=model(Assignment::class,$db);$staff->mutate('store','5',['trip_id'=>'1','driver_id'=>'10','vehicle_id'=>'20','notes'=>null]);$m=new DriverPortal($db);checkResources($db,'pending','pending');pc($db->d['status']==='available'&&$db->v['status']==='available','Reservation never changes physical enums');$m->action('7','1','accept');checkResources($db,'accepted','accepted');$m->action('7','1','start');checkResources($db,'on_trip','running');$db->orders=[];$m->action('7','1','complete');checkResources($db,'available','completed');
foreach(['pending','accepted'] as $phase){$db=new ResourceMemory();$m=new DriverPortal($db);if($phase!=='pending')$m->action('7','1','accept');if($phase==='running')$m->action('7','1','start');model(Assignment::class,$db)->mutate('cancel','5',null,'1','Tài xế không thể chạy');checkResources($db,'available','cancelled');}
foreach(['maintenance','inactive'] as $status){$db=new ResourceMemory();$db->v['status']=$status;$db->d['status']='inactive';pc(model(Vehicle::class,$db)->getAll('','')[0]['display_status']===$status&&model(Driver::class,$db)->getAll('','')[0]['display_status']==='inactive','Protected states override reservation');$db->a['status']='cancelled';pc(model(Vehicle::class,$db)->stats()[$status]===1&&model(Driver::class,$db)->getAll('','')[0]['display_status']==='inactive','Cancel preserves protected states');}
$db=new ResourceMemory();$db->t['status']='CANCELLED';pc(model(Driver::class,$db)->getAll('','')[0]['display_status']==='unknown'&&model(Vehicle::class,$db)->stats()['unknown']===1,'An assigned row still reserves even if Trip cancelled');$db->a['status']='cancelled';$db->d['status']='on_trip';pc(model(Driver::class,$db)->getAll('','')[0]['display_status']==='inactive'&&$db->d['status']==='on_trip','Unreserved legacy on_trip shows not ready without rewriting data');
echo "PASS: $n cumulative Driver/Resource API memory checks, no MySQL.\n";
