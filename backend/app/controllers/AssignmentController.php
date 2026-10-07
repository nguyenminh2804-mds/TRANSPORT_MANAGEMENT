<?php
require_once __DIR__.'/../../core/Controller.php';
require_once __DIR__.'/../models/Assignment.php';
class AssignmentController extends Controller {
    public function handle($action) {
        header('Cache-Control: no-store, private');
        if(empty($_SESSION['user_id']))$this->error('Vui lòng đăng nhập.',401);
        try {
            $actor=Assignment::id($_SESSION['user_id']);$m=new Assignment();if(!$m->manager($actor))$this->error('Chỉ ADMIN/STAFF đang hoạt động được quản lý phân công.',403);
            if($action==='index'){$search=$_GET['search']??'';$status=$_GET['status']??'';if(!is_string($search)||mb_strlen($search)>150||!is_string($status)||!in_array($status,['','assigned','completed','cancelled'],true))throw new DomainException('Bộ lọc không hợp lệ.',422);$this->success($m->all(trim($search),$status));}
            if($action==='options')$this->success($m->options());
            $id=$action==='store'?null:Assignment::id($_GET['id']??null);
            if($action==='show'){$r=$m->find($id);if(!$r)$this->error('Không tìm thấy phân công.',404);$this->success($r);}
            if(!in_array($action,['store','replace','complete','cancel'],true))$this->error('Thao tác không tồn tại.',404);
            $data=null;if(in_array($action,['store','replace'],true)){$body=json_decode(file_get_contents('php://input'));if(!is_object($body)||json_last_error()!==JSON_ERROR_NONE)throw new DomainException('Thông tin phải là JSON object hợp lệ.',400);$data=Assignment::validate((array)$body);}
            $this->success($m->mutate($action,$actor,$data,$id),'Cập nhật phân công thành công.');
        }catch(DomainException $e){$this->error($e->getMessage(),$e->getCode());}
        catch(PDOException $e){error_log('Assignment API code='.$e->getCode());$c=(int)($e->errorInfo[1]??0);if($c===1062)$this->error('Chuyến, tài xế hoặc xe đã được phân công bởi thao tác khác. Hãy làm mới.',409);if(in_array($c,[1205,1213],true))$this->error('Dữ liệu đang được nhân viên khác cập nhật. Hãy làm mới và thử lại.',409);$this->error('Không thể cập nhật phân công. Dữ liệu được giữ nguyên nếu giao dịch thất bại.',500);}
        catch(Throwable $e){error_log('Assignment API '.get_class($e));$this->error('Không thể xử lý yêu cầu phân công.',500);}
    }
}
