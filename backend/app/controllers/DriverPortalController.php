<?php
require_once __DIR__.'/../../core/Controller.php';
require_once __DIR__.'/../models/DriverPortal.php';
class DriverPortalController extends Controller {
    public function handle($action) {
        header('Cache-Control: no-store, private');
        if(empty($_SESSION['user_id']))$this->error('Vui lòng đăng nhập.',401);
        try {
            $m=new DriverPortal();$d=$m->identity($_SESSION['user_id']);
            if($action==='availability')$this->success($m->availability($_SESSION['user_id'],$this->body()),'Cập nhật trạng thái làm việc thành công.');
            if($action==='profile')$this->success($d);
            if($action==='updateProfile')$this->success($m->updateProfile($_SESSION['user_id'],$this->body()),'Cập nhật hồ sơ thành công.');
            if($action==='schedule')$this->success($m->schedule($d['driver_id'],$_GET['from']??'',$_GET['to']??'',$_GET['trip_id']??'',$_GET['route_search']??''));
            if($action==='home')$this->success(DriverPortal::summary($m->schedule($d['driver_id']),$m->clock()));
            if(in_array($action,['accept','start','complete'],true))$this->success($m->action($_SESSION['user_id'],$_GET['id']??null,$action),'Cập nhật chuyến thành công.');
            $this->error('Không tìm thấy thao tác.',404);
        }catch(DomainException $e){$this->error($e->getMessage(),$e->getCode());}
        catch(Throwable $e){error_log('Driver portal: '.get_class($e).' code='.$e->getCode());$this->error('Không thể xử lý lúc này. Vui lòng tải lại hoặc liên hệ nhân viên.',500);}
    }
    private function body():array { $data=json_decode(file_get_contents('php://input'));if(!is_object($data)||json_last_error()!==JSON_ERROR_NONE)throw new DomainException('Thông tin gửi lên không hợp lệ.',422);return (array)$data; }
}
