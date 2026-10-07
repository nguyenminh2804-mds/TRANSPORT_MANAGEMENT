<?php
// Pure memory connection: never connects to or writes transport_db.
require_once __DIR__.'/../backend/app/models/Assignment.php';
$count=0;
function ac($ok,$label){global $count;if(!$ok)throw new RuntimeException($label);$count++;}
function rejects($fn,$code){try{$fn();}catch(DomainException $e){ac($e->getCode()===$code,'Error code');return;}throw new RuntimeException('Expected failure');}
$data=['trip_id'=>'1','driver_id'=>'10','vehicle_id'=>'20','notes'=>null];
ac(Assignment::validate($data+['assigned_by'=>'999'])===$data,'Ignore frontend actor');
ac(Assignment::id('2147483647',true)==='2147483647','Signed trip INT max');
ac(Assignment::id('18446744073709551615')==='18446744073709551615','BIGINT max');
foreach(['2147483648','0','-1',[],null] as $id)rejects(fn()=>Assignment::id($id,true),422);
rejects(fn()=>Assignment::id('18446744073709551616'),422);
rejects(fn()=>Assignment::validate(array_merge($data,['notes'=>[]])),422);
class AssignmentMemory {
 public $a=[],$d=[],$v=[],$t=[],$sql=[],$fail=null,$commits=0,$rollbacks=0,$authorized=true,$duplicate=false;
 private $snapshot,$active=false,$last=0;
 public function __construct(){ $this->d=['10'=>['driver_id'=>'10','status'=>'available','license_expiry'=>null],'11'=>['driver_id'=>'11','status'=>'available','license_expiry'=>null]];$this->v=['20'=>['vehicle_id'=>'20','status'=>'available'],'21'=>['vehicle_id'=>'21','status'=>'available']];$this->t=['1'=>['id'=>'1','driver_id'=>'10','vehicle_id'=>'20','status'=>'PLANNED']]; }
 public function prepare($sql){$this->sql[]=$sql;return new AssignmentMemoryStatement($this,$sql);}
 public function beginTransaction(){$this->snapshot=[$this->a,$this->d,$this->v,$this->t];$this->active=true;}
 public function inTransaction(){return $this->active;}
 public function commit(){$this->active=false;$this->commits++;}
 public function rollBack(){[$this->a,$this->d,$this->v,$this->t]=$this->snapshot;$this->active=false;$this->rollbacks++;}
 public function lastInsertId(){return (string)$this->last;}
 public function run($sql,$p){
  if($this->duplicate&&str_starts_with($sql,'INSERT INTO assignments')){$e=new PDOException('Injected UNIQUE race');$e->errorInfo=['23000',1062,'Duplicate'];throw $e;}
  if($this->fail&&str_starts_with($sql,$this->fail))throw new RuntimeException('Injected memory fault');
  if(str_starts_with($sql,'SELECT id FROM users'))return $this->authorized?[['id'=>$p[0]]]:[];
  if(str_starts_with($sql,'SELECT * FROM trips'))return isset($this->t[$p[0]])?[$this->t[$p[0]]]:[];
  if(str_starts_with($sql,'SELECT * FROM drivers'))return isset($this->d[$p[0]])?[$this->d[$p[0]]]:[];
  if(str_starts_with($sql,'SELECT * FROM vehicles'))return isset($this->v[$p[0]])?[$this->v[$p[0]]]:[];
  if(str_starts_with($sql,'SELECT * FROM assignments'))return isset($this->a[$p[0]])?[$this->a[$p[0]]]:[];
  if(str_starts_with($sql,'SELECT assignment_id FROM assignments')){
   return array_values(array_filter($this->a,function($a)use($sql,$p){if($a['status']!=='assigned')return false;if(str_contains($sql,'trip_id=? OR'))return $a['trip_id']===$p[0]||$a['driver_id']===$p[1]||$a['vehicle_id']===$p[2];$key=str_contains($sql,'WHERE driver_id')?'driver_id':'vehicle_id';return $a[$key]===$p[0];}));
  }
  if(str_starts_with($sql,'SELECT a.assignment_id'))return isset($this->a[$p[0]])?[$this->a[$p[0]]]:[];
  if(str_starts_with($sql,'INSERT INTO assignments')){
   foreach($this->a as $a)if($a['status']==='assigned'&&($a['trip_id']===$p[0]||$a['driver_id']===$p[1]||$a['vehicle_id']===$p[2])){$e=new PDOException('Unique');$e->errorInfo=['23000',1062,'unique'];throw $e;}
   $id=(string)++$this->last;$this->a[$id]=['assignment_id'=>$id,'trip_id'=>$p[0],'driver_id'=>$p[1],'vehicle_id'=>$p[2],'assigned_by'=>$p[3],'assigned_at'=>'2026-10-07 12:00:00','status'=>'assigned','completed_at'=>null,'cancelled_at'=>null,'notes'=>$p[4]];return [];
  }
  if(str_starts_with($sql,'UPDATE assignments')){
   if(str_contains($sql,"status='cancelled'")){$id=$p[0];$status='cancelled';$column='cancelled_at';}else{[$status,$id]=$p;$column=$status==='completed'?'completed_at':'cancelled_at';}
   $this->a[$id]['status']=$status;$this->a[$id][$column]='2026-10-07 12:01:00';return [];
  }
  if(str_starts_with($sql,'UPDATE trips')){$this->t[$p[2]]['driver_id']=$p[0];$this->t[$p[2]]['vehicle_id']=$p[1];return [];}
  foreach(['drivers'=>'d','vehicles'=>'v'] as $table=>$prop)if(str_starts_with($sql,'UPDATE '.$table)){
   $target=str_contains($sql,"SET status='available'")?'available':'on_trip';if($target!=='available'||$this->{$prop}[$p[0]]['status']==='on_trip')$this->{$prop}[$p[0]]['status']=$target;return [];
  }
  throw new RuntimeException('Unrecognized memory SQL: '.$sql);
 }
}
class AssignmentMemoryStatement {private $db,$sql,$rows;public function __construct($db,$sql){$this->db=$db;$this->sql=$sql;}public function execute($p=[]){$this->rows=$this->db->run($this->sql,$p);return true;}public function fetchAll(){return $this->rows;}}
function am($db){$r=new ReflectionClass(Assignment::class);$m=$r->newInstanceWithoutConstructor();$r->getProperty('db')->setValue($m,$db);return $m;}
$db=new AssignmentMemory();$m=am($db);$r=$m->mutate('store','5',$data);
ac($r['assigned_by']==='5'&&$r['status']==='assigned','Session actor and initial status');ac($db->d['10']['status']==='on_trip'&&$db->v['20']['status']==='on_trip','Both resources busy');ac($db->t['1']['status']==='PLANNED','Trip status untouched');
rejects(fn()=>$m->mutate('store','5',$data),409);ac(count($db->a)===1,'Duplicate leaves history');
$r2=$m->mutate('replace','5',array_merge($data,['driver_id'=>'11','vehicle_id'=>'21']),$r['assignment_id']);
ac($db->a['1']['status']==='cancelled'&&$db->a['1']['cancelled_at']!==null,'Old cancelled history');ac($db->t['1']['driver_id']==='11'&&$db->t['1']['vehicle_id']==='21','Trip mirror updated');ac($db->d['10']['status']==='available'&&$db->v['20']['status']==='available','Old released');
$m->mutate('complete','5',null,$r2['assignment_id']);ac($db->a['2']['status']==='completed'&&$db->a['2']['completed_at']!==null&&$db->a['2']['cancelled_at']===null,'Complete timestamps');ac($db->d['11']['status']==='available'&&$db->t['1']['driver_id']==='11','Release retains trip pair');
rejects(fn()=>$m->mutate('cancel','5',null,'2'),409);
$r3=$m->mutate('store','5',$data);$db->d['10']['status']='inactive';$db->v['20']['status']='maintenance';$m->mutate('cancel','5',null,$r3['assignment_id']);ac($db->d['10']['status']==='inactive'&&$db->v['20']['status']==='maintenance','Protected statuses not overwritten');
foreach(['INSERT INTO assignments','UPDATE trips','UPDATE drivers','UPDATE vehicles','SELECT a.assignment_id'] as $fault){$b=new AssignmentMemory();$b->fail=$fault;$before=[$b->a,$b->d,$b->v,$b->t];try{am($b)->mutate('store','5',$data);throw new LogicException('Fault not reached');}catch(RuntimeException $e){ac([$b->a,$b->d,$b->v,$b->t]===$before&&$b->rollbacks===1&&$b->commits===0,'Atomic rollback '.$fault);}}
$b=new AssignmentMemory();$mm=am($b);$old=$mm->mutate('store','5',$data);$before=[$b->a,$b->d,$b->v,$b->t];$b->fail='INSERT INTO assignments';try{$mm->mutate('replace','5',array_merge($data,['driver_id'=>'11','vehicle_id'=>'21']),'1');}catch(RuntimeException $e){ac([$b->a,$b->d,$b->v,$b->t]===$before,'Replacement failure restores old assigned');}
$b=new AssignmentMemory();$b->authorized=false;rejects(fn()=>am($b)->mutate('store','5',$data),403);ac(!$b->a&&$b->rollbacks===1,'No write without permission');
foreach(['inactive','on_trip'] as $status)rejects(fn()=>Assignment::eligible(['status'=>'PLANNED'],['status'=>$status,'license_expiry'=>null],['status'=>'available']),409);
foreach(['maintenance','inactive','on_trip'] as $status)rejects(fn()=>Assignment::eligible(['status'=>'PLANNED'],['status'=>'available','license_expiry'=>null],['status'=>$status]),409);
foreach(['COMPLETED','CANCELLED',null] as $status)rejects(fn()=>Assignment::eligible(['status'=>$status],['status'=>'available','license_expiry'=>null],['status'=>'available']),409);
rejects(fn()=>Assignment::eligible(['status'=>'PLANNED'],['status'=>'available','license_expiry'=>'2000-01-01'],['status'=>'available']),409);

$b=new AssignmentMemory();$b->t['2']=['id'=>'2','driver_id'=>'11','vehicle_id'=>'21','status'=>'PLANNED'];$mm=am($b);$old=$mm->mutate('store','5',$data);$mm->mutate('replace','5',array_merge($data,['trip_id'=>'2','driver_id'=>'11','vehicle_id'=>'21']),'1');
ac($b->t['1']['driver_id']==='10'&&$b->t['2']['driver_id']==='11'&&$b->a['1']['status']==='cancelled'&&$b->a['2']['trip_id']==='2','Cross-trip replacement preserves old pair and history');
foreach(['complete','cancel'] as $action){$b=new AssignmentMemory();$mm=am($b);$mm->mutate('store','5',$data);$before=[$b->a,$b->d,$b->v,$b->t];$b->fail='UPDATE drivers';try{$mm->mutate($action,'5',null,'1');}catch(RuntimeException $e){ac([$b->a,$b->d,$b->v,$b->t]===$before&&$b->rollbacks===1,'Finish/cancel release fault rolls back status and timestamps');}}
$b=new AssignmentMemory();$mm=am($b);$mm->mutate('store','5',$data);$b->a['999']=$b->a['1'];$b->a['999']['assignment_id']='999';$mm->mutate('cancel','5',null,'1');ac($b->d['10']['status']==='on_trip'&&$b->v['20']['status']==='on_trip','Do not release while another effective row exists');
$b=new AssignmentMemory();$b->duplicate=true;$before=[$b->a,$b->d,$b->v,$b->t];try{am($b)->mutate('store','5',$data);}catch(PDOException $e){ac(($e->errorInfo[1]??0)===1062&&[$b->a,$b->d,$b->v,$b->t]===$before&&$b->rollbacks===1,'Unique race rollback');}
ac(!array_filter($db->sql,fn($sql)=>str_starts_with($sql,'DELETE')||str_contains($sql,'UPDATE orders')||str_contains($sql,'SET status')&&str_starts_with($sql,'UPDATE trips')),'No delete/Order/Trip status writes');
echo "PASS: $count Assignment unit/memory transaction checks. No real database writes.\n";
