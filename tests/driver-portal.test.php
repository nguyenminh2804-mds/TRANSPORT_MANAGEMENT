<?php
// Unit and in-memory SQL adapter only. No PDO connection and no MySQL writes.
require_once __DIR__.'/../backend/app/models/DriverPortal.php';
$n=0;function pc($ok,$label){global $n;if(!$ok)throw new RuntimeException($label);$n++;}
function pr($fn,$code){try{$fn();}catch(DomainException $e){pc($e->getCode()===$code,'Expected rejection');return;}throw new RuntimeException('Expected DomainException');}
class PortalMemory {
 public $role='DRIVER',$activeUser=true,$linked=true,$fail=null,$commits=0,$rollbacks=0,$sql=[];
 public $d=['driver_id'=>'10','driver_code'=>'TX10','full_name'=>'A','phone'=>'0901234567','address'=>null,'license_number'=>'L10','license_class'=>'C','license_expiry'=>null,'status'=>'available'];
 public $v=['vehicle_id'=>'20','status'=>'available'];
 public $t=['id'=>'1','driver_id'=>'10','vehicle_id'=>'20','status'=>'PLANNED','start_time'=>'2026-10-13 08:00:00','end_time'=>null];
 public $a=['assignment_id'=>'1','trip_id'=>'1','driver_id'=>'10','vehicle_id'=>'20','assigned_by'=>'5','assigned_at'=>'2026-10-13 07:00:00','accepted_at'=>null,'completed_at'=>null,'cancelled_at'=>null,'status'=>'assigned'];
 public $orders=[['id'=>'1','status'=>'DELIVERED']],$otherActive=false;
 private $tx=false,$snapshot;
 public function beginTransaction(){$this->tx=true;$this->snapshot=[$this->a,$this->t,$this->d,$this->v];}
 public function inTransaction(){return $this->tx;}
 public function commit(){$this->tx=false;$this->commits++;}
 public function rollBack(){[$this->a,$this->t,$this->d,$this->v]=$this->snapshot;$this->tx=false;$this->rollbacks++;}
 public function prepare($sql){$this->sql[]=$sql;return new PortalStatement($this,$sql);}
 public function run($sql,$p){
  if($this->fail&&str_starts_with($sql,$this->fail))throw new RuntimeException('Injected transaction failure');
  if(str_starts_with($sql,'SELECT DATE_FORMAT'))return [['server_now'=>'2026-10-13 09:30:00']];
  if(str_starts_with($sql,'SELECT id FROM users'))return $this->role==='DRIVER'&&$this->activeUser?[['id'=>$p[0]]]:[];
  if(str_starts_with($sql,'SELECT driver_id,driver_code'))return $this->linked?[$this->d+['has_active_assignment'=>$this->a['status']==='assigned','has_active_trip'=>$this->t['status']==='IN_PROGRESS']]:[];
  if(str_starts_with($sql,'SELECT * FROM assignments'))return $p[0]===$this->a['assignment_id']&&$p[1]===$this->a['driver_id']?[$this->a]:[];
  if(str_starts_with($sql,'SELECT * FROM trips'))return [$this->t];
  if(str_starts_with($sql,'SELECT * FROM vehicles'))return [$this->v];
  if(str_starts_with($sql,'SELECT id FROM trips WHERE driver_id'))return $this->t['status']==='IN_PROGRESS'?[$this->t]:[];
  if(str_starts_with($sql,'SELECT o.id,o.status'))return $this->orders;
  if(str_starts_with($sql,'SELECT a.assignment_id'))return [$this->a+['trip_status'=>$this->t['status'],'start_time'=>$this->t['start_time'],'end_time'=>$this->t['end_time'],'order_count'=>count($this->orders)]];
  if(str_starts_with($sql,'SELECT assignment_id FROM assignments'))return $this->a['status']==='assigned'?[$this->a]:($this->otherActive?[['assignment_id'=>'2']]:[]);
  if(str_starts_with($sql,'UPDATE assignments SET accepted_at')){$this->a['accepted_at']='2026-10-13 07:30:00';return [];}
  if(str_starts_with($sql,"UPDATE assignments SET status='completed'")){$this->a['status']='completed';$this->a['completed_at']='2026-10-13 09:00:00';return [];}
  if(str_starts_with($sql,'UPDATE trips')){$this->t['status']=str_contains($sql,'IN_PROGRESS')?'IN_PROGRESS':'COMPLETED';if($this->t['status']==='COMPLETED')$this->t['end_time']='2026-10-13 09:00:00';return [];}
  if(str_starts_with($sql,'UPDATE drivers SET full_name')){[$this->d['full_name'],$this->d['phone'],$this->d['address']]=$p;return [];}
  if(str_starts_with($sql,'UPDATE drivers SET status=?')){$this->d['status']=$p[0];return [];}
  foreach(['drivers'=>'d','vehicles'=>'v'] as $table=>$property)if(str_starts_with($sql,'UPDATE '.$table.' SET status')){if(str_contains($sql,"SET status='on_trip'"))$this->{$property}['status']='on_trip';elseif($this->{$property}['status']==='on_trip')$this->{$property}['status']='available';return [];}
  throw new RuntimeException('Unexpected SQL '.$sql);
 }
}
class PortalStatement {private $db,$sql,$rows;function __construct($db,$sql){$this->db=$db;$this->sql=$sql;}function execute($p){$this->rows=$this->db->run($this->sql,$p);}function fetchAll(){return $this->rows;}}
foreach(['STAFF','ADMIN','CUSTOMER'] as $role){$db=new PortalMemory();$db->role=$role;pr(fn()=>(new DriverPortal($db))->identity('7'),403);}
$db=new PortalMemory();$db->activeUser=false;pr(fn()=>(new DriverPortal($db))->identity('7'),403);$db->activeUser=true;$db->linked=false;pr(fn()=>(new DriverPortal($db))->identity('7'),403);
foreach(['status','user_id','driver_id','driver_code','license_number','license_class','license_expiry','password'] as $key)pr(fn()=>DriverPortal::profileData(['full_name'=>'A','phone'=>'0901234567','address'=>null,$key=>'bad']),422);
pr(fn()=>DriverPortal::profileData(['full_name'=>'','phone'=>'0901234567','address'=>null]),422);pr(fn()=>DriverPortal::dates('2026-02-30',''),422);pr(fn()=>DriverPortal::dates('2026-10-14','2026-10-13'),422);pr(fn()=>DriverPortal::dates([],''),422);
$db=new PortalMemory();$m=new DriverPortal($db);pr(fn()=>$m->action('7','1','start'),409);pr(fn()=>$m->action('7','2','accept'),404);$db->a['driver_id']='11';pr(fn()=>$m->action('7','1','accept'),404);$db->a['driver_id']='10';
$m->action('7','1','accept');pc($db->a['accepted_at']!==null&&$db->d['status']==='available'&&$db->v['status']==='available','Accept persists without on_trip');pr(fn()=>$m->action('7','1','accept'),409);
$m->action('7','1','start');pc($db->t['status']==='IN_PROGRESS'&&$db->d['status']==='on_trip'&&$db->v['status']==='on_trip','Start atomic states');pc($db->t['start_time']==='2026-10-13 08:00:00','Scheduled departure preserved');pr(fn()=>$m->action('7','1','start'),409);
foreach([[],[['id'=>'1','status'=>'DELIVERED'],['id'=>'2','status'=>'IN_TRANSIT']],[['id'=>'1','status'=>'IN_TRANSIT']],[['id'=>'1','status'=>'CANCELLED']]] as $orders){$b=new PortalMemory();$mm=new DriverPortal($b);$mm->action('7','1','accept');$mm->action('7','1','start');$b->orders=$orders;$mm->action('7','1','complete');pc($b->a['status']==='completed'&&$b->t['status']==='COMPLETED','Completion independent of Order count/status');pc(!array_filter($b->sql,fn($s)=>str_starts_with($s,'SELECT o.id,o.status')||str_starts_with($s,'UPDATE orders')),'No Order gate or writes');}
$db->orders=[['id'=>'1','status'=>'DELIVERED']];$m->action('7','1','complete');pc($db->a['status']==='completed'&&$db->t['status']==='COMPLETED'&&$db->d['status']==='available'&&$db->v['status']==='available','Completion atomic release');pr(fn()=>$m->action('7','1','complete'),409);pc(!array_filter($db->sql,fn($s)=>str_starts_with($s,'UPDATE orders')),'Never mark Order delivered');
foreach(['inactive','maintenance'] as $state){$db=new PortalMemory();$m=new DriverPortal($db);$m->action('7','1','accept');$m->action('7','1','start');$db->v['status']=$state;$db->d['status']='inactive';$m->action('7','1','complete');pc($db->v['status']===$state&&$db->d['status']==='inactive','Protected statuses preserved');}
$db=new PortalMemory();$m=new DriverPortal($db);$m->action('7','1','accept');$m->action('7','1','start');$db->otherActive=true;$m->action('7','1','complete');pc($db->d['status']==='on_trip'&&$db->v['status']==='on_trip','Other active assignment prevents release');
foreach(['cancelled','completed'] as $state){$db=new PortalMemory();$db->a['status']=$state;pr(fn()=>(new DriverPortal($db))->action('7','1','accept'),409);}
$db=new PortalMemory();$db->t['driver_id']='11';pr(fn()=>(new DriverPortal($db))->action('7','1','accept'),409);
foreach(['UPDATE assignments SET accepted_at','UPDATE trips','UPDATE drivers SET status','UPDATE vehicles SET status','SELECT a.assignment_id'] as $fault){$db=new PortalMemory();$m=new DriverPortal($db);if($fault!=='UPDATE assignments SET accepted_at')$m->action('7','1','accept');$before=[$db->a,$db->t,$db->d,$db->v];$db->fail=$fault;try{$m->action('7','1',$fault==='UPDATE assignments SET accepted_at'?'accept':'start');throw new LogicException('Fault not reached');}catch(RuntimeException $e){pc([$db->a,$db->t,$db->d,$db->v]===$before&& !$db->inTransaction(),'Start/accept rollback '.$fault);}}
foreach(['UPDATE trips','UPDATE assignments SET status','UPDATE drivers SET status','UPDATE vehicles SET status','SELECT a.assignment_id'] as $fault){$db=new PortalMemory();$m=new DriverPortal($db);$m->action('7','1','accept');$m->action('7','1','start');$before=[$db->a,$db->t,$db->d,$db->v];$db->fail=$fault;try{$m->action('7','1','complete');throw new LogicException('Fault not reached');}catch(RuntimeException $e){pc([$db->a,$db->t,$db->d,$db->v]===$before&& !$db->inTransaction(),'Completion rollback '.$fault);}}
$db=new PortalMemory();$m=new DriverPortal($db);$d=$m->updateProfile('7',['full_name'=>'B','phone'=>'0901234567','address'=>'X']);pc($d['full_name']==='B'&&$d['driver_code']==='TX10'&&$d['license_number']==='L10','Profile whitelist');$before=$db->d;$db->fail='UPDATE drivers';try{$m->updateProfile('7',['full_name'=>'C','phone'=>'0901234567','address'=>null]);}catch(RuntimeException $e){pc($db->d===$before&&!$db->inTransaction(),'Profile rollback');}
pc((new DriverPortal(new PortalMemory()))->clock()->format('Y-m-d H:i:s')==='2026-10-13 09:30:00','Dashboard uses database wall clock');
$base=['status'=>'assigned','trip_status'=>'PLANNED','accepted_at'=>null];pc(DriverPortal::phase($base)==='pending','Pending phase');pc(DriverPortal::phase(array_merge($base,['accepted_at'=>'2026-10-13']))==='accepted','Accepted phase');pc(DriverPortal::phase(array_merge($base,['trip_status'=>'IN_PROGRESS']))==='running','Saved running phase');pc(DriverPortal::phase(array_merge($base,['trip_status'=>'COMPLETED']))==='unknown','Do not invent completion');
$summary=DriverPortal::summary([['status'=>'assigned','trip_status'=>'PLANNED','accepted_at'=>null,'start_time'=>'2026-10-13 10:00:00'],['status'=>'completed','trip_status'=>'COMPLETED','accepted_at'=>'2026-10-12','start_time'=>'2026-10-13 08:00:00','completed_at'=>'2026-10-13 09:00:00']],new DateTimeImmutable('2026-10-13 09:30:00'));pc($summary['today_count']===2&&$summary['completed_week']===1&&count($summary['pending'])===1&&$summary['next']!==null,'Real-row summary calculation');
$b=new PortalMemory();$mm=new DriverPortal($b);pr(fn()=>$mm->availability('7',['status'=>'inactive']),409);$b->a['status']='cancelled';$mm->availability('7',['status'=>'inactive']);pc($b->d['status']==='inactive','Driver chooses not ready without assignment');$mm->availability('7',['status'=>'available']);pc($b->d['status']==='available','Driver chooses ready');pr(fn()=>$mm->availability('7',['status'=>'on_trip']),422);$b->t['status']='IN_PROGRESS';pr(fn()=>$mm->availability('7',['status'=>'inactive']),409);
require_once __DIR__.'/../backend/app/controllers/DriverPortalController.php';
class PortalGuestProbe extends DriverPortalController { protected function error($message,$statusCode=400){throw new DomainException($message,$statusCode);} }
$_SESSION=[];pr(fn()=>(new PortalGuestProbe())->handle('home'),401);
foreach(['inactive','on_trip'] as $status){$db=new PortalMemory();$db->d['status']=$status;pr(fn()=>(new DriverPortal($db))->action('7','1','accept'),409);}
foreach(['maintenance','inactive','on_trip'] as $status){$db=new PortalMemory();$db->v['status']=$status;pr(fn()=>(new DriverPortal($db))->action('7','1','accept'),409);}
$db=new PortalMemory();$db->d['license_expiry']='2000-01-01';pr(fn()=>(new DriverPortal($db))->action('7','1','accept'),409);
$db=new PortalMemory();(new DriverPortal($db))->schedule('10','2026-10-13','2026-10-14');pc((bool)array_filter($db->sql,fn($sql)=>str_contains($sql,'t.start_time>=?')&&str_contains($sql,'t.start_time<?')&&str_contains($sql,'WHERE a.driver_id=?')),'Parameterized own-driver date filtering');
echo "PASS: $n Driver portal unit/memory checks. No MySQL connection or writes.\n";
