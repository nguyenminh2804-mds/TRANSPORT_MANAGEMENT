<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Trip.php';
require_once __DIR__ . '/../models/Driver.php';

class TripController extends Controller
{
    private $tripModel;
    private $driverModel;

    public function __construct()
    {
        $this->tripModel = new Trip();
        $this->driverModel = new Driver();
    }

    // Hàm điều phối trung gian giống các Controller khác của bạn
    public function handle($action)
    {
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
        }

        switch ($action) {
            case 'index':
                $this->index();
                break;
            case 'show':
                $this->show();
                break;
            case 'store':
                $this->store();
                break;
            case 'update':
                $this->update();
                break;
            case 'status':
                $this->updateStatus();
                break;
            case 'destroy':
                $this->destroy();
                break;
            default:
                $this->error('Hành động không hợp lệ.', 400);
        }
    }

    // Lấy danh sách chuyến đi (Phân quyền theo Role)
    private function index()
    {
        $role = $_SESSION['role'] ?? '';
        $userId = $_SESSION['user_id'] ?? null;

        if (in_array($role, ['ADMIN', 'STAFF'])) {
            $trips = $this->tripModel->getAll();
        } elseif ($role === 'DRIVER') {
            // Lấy driver_id từ user_id đang đăng nhập
            $driver = $this->driverModel->findByUserId($userId);
            if (!$driver) {
                $this->success([], 'Tài khoản chưa được liên kết hồ sơ tài xế.');
                return;
            }
            $trips = $this->tripModel->getByDriverId($driver['driver_id']);
        } else {
            $this->error('Bạn không có quyền xem danh sách chuyến đi.', 403);
            return;
        }

        $this->success($trips, 'Lấy danh sách chuyến đi thành công');
    }

    // Xem chi tiết một chuyến
    private function show()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->error('Thiếu ID chuyến đi.');
        }

        $trip = $this->tripModel->findById($id);
        if (!$trip) {
            $this->error('Không tìm thấy chuyến đi.', 404);
        }

        $this->success($trip);
    }

    // Tạo chuyến mới (Chỉ ADMIN và STAFF)
    private function store()
    {
        $this->checkManagerRole();

        $data = json_decode(file_get_contents('php://input'), true);

        if (empty($data['start_location']) || empty($data['end_location'])) {
            $this->error('Vui lòng nhập điểm đi và điểm đến.');
        }

        $created = $this->tripModel->create($data);
        if (!$created) {
            $this->error('Không thể tạo chuyến đi mới.', 500);
        }

        $this->success([], 'Tạo chuyến đi thành công');
    }

    // Cập nhật chuyến đi (ADMIN và STAFF)
    private function update()
    {
        $this->checkManagerRole();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->error('Thiếu ID chuyến đi.');
        }

        $data = json_decode(file_get_contents('php://input'), true);

        $updated = $this->tripModel->update($id, $data);
        if (!$updated) {
            $this->error('Không thể cập nhật chuyến đi.', 500);
        }

        $this->success([], 'Cập nhật chuyến đi thành công');
    }

    // Cập nhật trạng thái chuyến (STAFF hoặc DRIVER của chuyến đó)
    private function updateStatus()
    {
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);
        $newStatus = $data['status'] ?? '';

        $allowedStatuses = ['PLANNED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            $this->error('Trạng thái chuyến đi không hợp lệ.');
        }

        $trip = $this->tripModel->findById($id);
        if (!$trip) {
            $this->error('Không tìm thấy chuyến đi.', 404);
        }

        $role = $_SESSION['role'];
        if ($role === 'DRIVER') {
            $driver = $this->driverModel->findByUserId($_SESSION['user_id']);
            if (!$driver || $trip['driver_id'] != $driver['driver_id']) {
                $this->error('Bạn không có quyền cập nhật chuyến đi này.', 403);
            }
        } elseif (!in_array($role, ['ADMIN', 'STAFF'], true)) {
            $this->error('Bạn không có quyền thực hiện hành động này.', 403);
        }

        $updated = $this->tripModel->updateStatus($id, $newStatus);
        if (!$updated) {
            $this->error('Không thể cập nhật trạng thái chuyến đi.', 500);
        }

        $this->success([], 'Cập nhật trạng thái chuyến thành công');
    }

    // Xóa chuyến (ADMIN và STAFF)
    private function destroy()
    {
        $this->checkManagerRole();

        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->error('Thiếu ID chuyến đi.');
        }

        $deleted = $this->tripModel->delete($id);
        if (!$deleted) {
            $this->error('Không thể xóa chuyến đi.', 500);
        }

        $this->success([], 'Xóa chuyến đi thành công');
    }

    private function checkManagerRole()
    {
        $role = $_SESSION['role'] ?? '';
        if (!in_array($role, ['ADMIN', 'STAFF'], true)) {
            $this->error('Chức năng này chỉ dành cho Quản trị viên và Nhân viên điều phối.', 403);
        }
    }
}