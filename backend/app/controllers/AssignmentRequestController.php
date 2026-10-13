<?php
require_once __DIR__.'/../../core/Controller.php';
require_once __DIR__.'/../models/AssignmentRequest.php';
class AssignmentRequestController extends Controller {
 public function handle($action){header('Cache-Control: no-store, private');if(empty($_SESSION['user_id']))$this->error('Vui lòng đăng nhập.',401);
  try{$m=new AssignmentRequest();$actor=$_SESSION['user_id'];
   if($action==='mine')$this->success($m->listing($actor));
   if($action==='list'){$m->manager($actor);$status=$_GET['status']??'pending';if(!is_string($status))throw new DomainException('Bộ lọc không hợp lệ.',422);$search=$_GET['search']??'';if(!is_string($search))throw new DomainException('Tìm kiếm không hợp lệ.',422);$this->success($m->listing($actor,true,$status,$search));}
   if($action==='submit')$this->success($m->submit($actor,$this->body()),'Đã gửi yêu cầu chờ duyệt.');
   if($action==='review'){$m->manager($actor);$this->success($m->review($actor,$_GET['id']??null,$this->body()),'Đã xử lý yêu cầu.');}
  }catch(DomainException $e){$this->error($e->getMessage(),$e->getCode());}catch(Throwable $e){error_log('Assignment request: '.get_class($e).' code='.$e->getCode());$this->error('Không thể xử lý yêu cầu lúc này. Vui lòng tải lại và thử lại.',500);}
 }
 private function body():array{$v=json_decode(file_get_contents('php://input'));if(!is_object($v)||json_last_error()!==JSON_ERROR_NONE)throw new DomainException('Dữ liệu không hợp lệ.',422);return (array)$v;}
}
