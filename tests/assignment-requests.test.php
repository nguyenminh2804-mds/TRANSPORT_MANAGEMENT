<?php
// No PDO connection: run the actual workflow against in-memory SQL doubles.
ob_start();
require_once __DIR__.'/driver-portal.test.php';
require_once __DIR__.'/../backend/app/models/AssignmentRequest.php';
class RequestMemory extends PortalMemory {
 public $requests=[],$manager=true;private $requestSnapshot;
 public function beginTransaction(){parent::beginTransaction();$this->requestSnapshot=$this->requests;}
 public function rollBack(){parent::rollBack();$this->requests=$this->requestSnapshot;}
 public function run($sql,$p){
  if($this->fail&&str_starts_with($sql,$this->fail))throw new RuntimeException('Request fault');
  if(str_starts_with($sql,'SELECT id FROM users')&&str_contains($sql,"role IN ('ADMIN','STAFF')"))return $this->manager?[['id'=>$p[0]]]:[];
  if(str_starts_with($sql,'SELECT * FROM drivers'))return [$this->d];
  if(str_starts_with($sql,'SELECT * FROM assignments')&&count($p)===1)return $p[0]===$this->a['assignment_id']?[$this->a]:[];
  if(str_starts_with($sql,'SELECT request_id FROM assignment_requests'))return array_values(array_filter($this->requests,fn($r)=>$r['assignment_id']===$p[0]&&$r['request_status']==='pending'));
  if(str_starts_with($sql,'SELECT * FROM assignment_requests'))return isset($this->requests[$p[0]])?[$this->requests[$p[0]]]:[];
  if(str_starts_with($sql,'INSERT INTO assignment_requests')){$id=(string)(count($this->requests)+1);$this->requests[$id]=['request_id'=>$id,'assignment_id'=>$p[0],'driver_id'=>$p[1],'request_type'=>$p[2],'reason'=>$p[3],'request_status'=>'pending','requested_at'=>'2026-10-13 08:00:00','reviewed_by'=>null,'reviewed_at'=>null,'review_reason'=>null];return [];}
  if(str_starts_with($sql,'UPDATE assignment_requests')){[$decision,$actor,$reason,$id]=$p;$this->requests[$id]=array_replace($this->requests[$id],['request_status'=>$decision,'reviewed_by'=>$actor,'reviewed_at'=>'2026-10-13 09:00:00','review_reason'=>$reason]);return [];}
  if(str_starts_with($sql,"UPDATE assignments SET status='cancelled'")){$this->a['status']='cancelled';$this->a['cancelled_at']='2026-10-13 09:00:00';return [];}
  if(str_starts_with($sql,"UPDATE trips SET status='CANCELLED'")){$this->t['status']='CANCELLED';return [];}
  if(str_starts_with($sql,'SELECT r.*')){return array_map(fn($r)=>$r+['trip_id'=>'1','driver_code'=>'TX10','driver_name'=>'A','reviewer_name'=>'STAFF','start_location'=>'A','end_location'=>'B'],array_values(array_filter($this->requests,fn($r)=>!str_contains($sql,'r.driver_id=?')||$r['driver_id']===$p[0])));}
  return parent::run($sql,$p);
 }
}
$submit=['assignment_id'=>'1','request_type'=>'decline_assignment','reason'=>'Không phù hợp'];$decision=['decision'=>'approved','review_reason'=>'Đã kiểm tra','cancel_trip'=>false];
foreach([null,'','   ',[],str_repeat('a',2001),"bad\0"] as $reason)pr(fn()=>AssignmentRequest::reason($reason),422);
$db=new RequestMemory();$m=new AssignmentRequest($db);$before=[$db->a,$db->t,$db->d,$db->v];$m->submit('7',$submit);pc([$db->a,$db->t,$db->d,$db->v]===$before,'Submit holds resources, changes no lifecycle');pr(fn()=>$m->submit('7',$submit),409);pc(count($db->requests)===1,'Duplicate pending prevented');pr(fn()=>$m->submit('7',$submit+['driver_id'=>'99']),422);$db->a['driver_id']='11';pr(fn()=>$m->submit('7',$submit),404);$db->a['driver_id']='10';
$m->review('5','1',$decision);pc($db->a['status']==='cancelled'&&$db->t['status']==='PLANNED','Approve decline preserves reassignable Trip');pc($db->requests['1']['request_status']==='approved'&&$db->requests['1']['reviewed_by']==='5'&&$db->requests['1']['review_reason']==='Đã kiểm tra','Audit reviewer/time/reason');pr(fn()=>$m->review('5','1',$decision),409);pc($m->listing('7')[0]['request_status']==='approved'&&$m->listing('5',true,'')[0]['review_reason']==='Đã kiểm tra','Persistent result exposed to Driver and Staff');
foreach(['decline_assignment','cancel_assignment','cancel_trip'] as $type){$db=new RequestMemory();$portal=new DriverPortal($db);if($type!=='decline_assignment')$portal->action('7','1','accept');if($type==='cancel_trip')$portal->action('7','1','start');$m=new AssignmentRequest($db);$m->submit('7',array_replace($submit,['request_type'=>$type]));$before=[$db->a,$db->t,$db->d,$db->v];$m->review('5','1',array_replace($decision,['decision'=>'rejected']));pc([$db->a,$db->t,$db->d,$db->v]===$before,'Reject preserves all lifecycle '.$type);pc($db->requests['1']['request_status']==='rejected','Rejected result visible '.$type);}
$db=new RequestMemory();$portal=new DriverPortal($db);$portal->action('7','1','accept');$m=new AssignmentRequest($db);pr(fn()=>$m->submit('7',$submit),409);$m->submit('7',array_replace($submit,['request_type'=>'cancel_assignment']));$m->review('5','1',$decision);pc($db->t['status']==='PLANNED'&&$db->a['status']==='cancelled','Approve cancellation before start');
$db=new RequestMemory();$m=new AssignmentRequest($db);$m->submit('7',$submit);$m->review('5','1',array_replace($decision,['cancel_trip'=>true]));pc($db->t['status']==='CANCELLED','Explicit staff cancel whole Trip');
foreach(['available','maintenance','inactive'] as $state){$db=new RequestMemory();$portal=new DriverPortal($db);$portal->action('7','1','accept');$portal->action('7','1','start');$db->v['status']=$state==='available'?'on_trip':$state;$db->d['status']=$state==='inactive'?'inactive':'on_trip';$m=new AssignmentRequest($db);$m->submit('7',array_replace($submit,['request_type'=>'cancel_trip']));$m->review('5','1',$decision);pc($db->t['status']==='CANCELLED'&&$db->a['status']==='cancelled','Running cancellation atomic');pc($db->v['status']===$state&&$db->d['status']===($state==='inactive'?'inactive':'available'),'Protected resource states survive approval');pc(!array_filter($db->sql,fn($s)=>str_starts_with($s,'UPDATE orders')),'No Order writes');}
$db=new RequestMemory();$m=new AssignmentRequest($db);$m->submit('7',$submit);(new DriverPortal($db))->action('7','1','accept');pr(fn()=>$m->review('5','1',$decision),409);pc($db->requests['1']['request_status']==='pending','Stale approval rolls back');$m->review('5','1',array_replace($decision,['decision'=>'rejected']));pc($db->a['status']==='assigned'&&$db->a['accepted_at']!==null,'Stale request may be closed by rejection without reverting Trip');
$db=new RequestMemory();$db->role='STAFF';pr(fn()=>(new AssignmentRequest($db))->submit('7',$submit),403);$db=new RequestMemory();$db->manager=false;pr(fn()=>(new AssignmentRequest($db))->listing('5',true),403);
foreach(['INSERT INTO assignment_requests','UPDATE trips','UPDATE assignments','UPDATE drivers','UPDATE vehicles','UPDATE assignment_requests'] as $fault){$db=new RequestMemory();$portal=new DriverPortal($db);$portal->action('7','1','accept');$portal->action('7','1','start');$m=new AssignmentRequest($db);$data=array_replace($submit,['request_type'=>'cancel_trip']);if($fault!=='INSERT INTO assignment_requests')$m->submit('7',$data);$before=[$db->a,$db->t,$db->d,$db->v,$db->requests];$db->fail=$fault;try{if($fault==='INSERT INTO assignment_requests')$m->submit('7',$data);else $m->review('5','1',$decision);throw new LogicException('Fault not reached');}catch(RuntimeException $e){pc([$db->a,$db->t,$db->d,$db->v,$db->requests]===$before&&!$db->inTransaction(),'Full request rollback '.$fault);}}
pc((bool)array_filter($db->sql,fn($s)=>str_contains($s,'assignments WHERE assignment_id')&&str_contains($s,'FOR UPDATE')),'Assignment lock serializes pending inserts');
require_once __DIR__.'/../backend/app/controllers/AssignmentRequestController.php';
class RequestGuestProbe extends AssignmentRequestController {protected function error($message,$statusCode=400){throw new DomainException($message,$statusCode);}}
$_SESSION=[];foreach(['mine','submit','list','review'] as $action)pr(fn()=>(new RequestGuestProbe())->handle($action),401);
$db=new RequestMemory();$m=new AssignmentRequest($db);$m->submit('7',$submit);$db->manager=false;pr(fn()=>$m->review('5','1',$decision),403);pc($db->requests['1']['request_status']==='pending'&&$db->a['status']==='assigned','Denied reviewer changes nothing');
echo "PASS: $n cumulative Driver/request memory checks; no MySQL.\n";

ob_end_flush();
