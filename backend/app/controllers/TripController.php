<?php
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Trip.php';
require_once __DIR__ . '/../models/Driver.php';

class TripController extends Controller {
    private $tripModel;
    private $driverModel;

    public function __construct() {
        $this->tripModel = new Trip();
        $this->driverModel = new Driver();
    }

    public function handle($action) {
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
            return;
        }
        
        switch ($action) {
            case 'index': $this->index(); break;
            case 'show': $this->show(); break;
            case 'store': $this->store(); break;
            case 'update': $this->update(); break;
            case 'status': $this->updateStatus(); break;
            case 'destroy': $this->destroy(); break;
            case 'assignOrder': $this->assignOrder(); break;
            default: $this->error('Hành động không hợp lệ.', 400);
        }
    }

    public function index()
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        $role = $_SESSION['role'] ?? '';
        
        require_once __DIR__ . '/../../core/Database.php';
        $dbInstance = new Database();
        $db = $dbInstance->connect();

        if (!$db) {
            $this->error('Không thể kết nối CSDL gốc.', 500);
            return;
        }
        
        // 1. NẾU LÀ ADMIN HOẶC NHÂN VIÊN
        if (in_array($role, ['ADMIN', 'STAFF'])) {
            try {
                $sql = "SELECT t.*, 
                               d.full_name AS driver_name, 
                               v.license_plate 
                        FROM trips t
                        LEFT JOIN drivers d ON t.driver_id = d.driver_id
                        LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                        ORDER BY t.id DESC";

                $stmt = $db->query($sql);
                $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->success($trips, 'Lấy toàn bộ chuyến xe thành công');
            } catch (Exception $e) {
                $this->error('Lỗi SQL: ' . $e->getMessage(), 500);
            }
            return;
        } 
        
        // 2. NẾU LÀ TÀI XẾ
        if ($role === 'DRIVER') {
            try {
                $driver = $this->driverModel->findByUserId($_SESSION['user_id']);
                
                if (!$driver) {
                    $this->success([], 'Chưa có hồ sơ tài xế');
                    return;
                }

                $stmt = $db->prepare("SELECT * FROM trips WHERE driver_id = ? ORDER BY id DESC");
                $stmt->execute([$driver['driver_id']]); 
                $trips = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $this->success($trips, 'Lấy chuyến xe của tài xế thành công');
            } catch (Exception $e) {
                $this->error('Lỗi truy xuất dữ liệu: ' . $e->getMessage(), 500);
            }
            return;
        }

        $this->error('Bạn không có quyền truy cập dữ liệu này.', 403);
    }
    
    private function show() {
        $id = $_GET['id'] ?? null;
        $trip = $this->tripModel->findById($id);
        if (!$trip) { $this->error('Không tìm thấy chuyến đi.', 404); return; }
        $this->success($trip);
    }

    private function store() {
        if (!in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) { $this->error('Chỉ Quản trị và Điều phối được thao tác.', 403); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        
        $data['driver_id'] = empty($data['driver_id']) ? null : $data['driver_id'];
        $data['vehicle_id'] = empty($data['vehicle_id']) ? null : $data['vehicle_id'];

        if ($this->tripModel->create($data)) $this->success([], 'Tạo thành công');
        else $this->error('Lỗi khi tạo.', 500);
    }

    private function update() {
        if (!in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) { $this->error('Chỉ Quản trị và Điều phối được thao tác.', 403); return; }
        $id = $_GET['id'] ?? null;
        $data = json_decode(file_get_contents('php://input'), true);

        $data['driver_id'] = empty($data['driver_id']) ? null : $data['driver_id'];
        $data['vehicle_id'] = empty($data['vehicle_id']) ? null : $data['vehicle_id'];

        if ($this->tripModel->update($id, $data)) $this->success([], 'Cập nhật thành công');
        else $this->error('Lỗi khi cập nhật.', 500);
    }

    private function updateStatus() 
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        $role = $_SESSION['role'] ?? '';
        
        if (!in_array($role, ['ADMIN', 'STAFF', 'DRIVER'])) {
            $this->error('Bạn không có quyền cập nhật chuyến xe.', 403);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $tripId = $data['id'] ?? $_GET['id'] ?? null;
        $status = $data['status'] ?? null;

        if (!$tripId || !$status) {
            $this->error('Thiếu thông tin mã chuyến xe hoặc trạng thái.', 400);
            return;
        }

        if ($this->tripModel->updateStatus($tripId, $status)) {
            $this->success([], 'Cập nhật trạng thái chuyến xe thành công');
        } else {
            $this->error('Lỗi khi cập nhật trạng thái.', 500);
        }
    }

    private function destroy() {
        if (!in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) { $this->error('Chỉ Quản trị và Điều phối được thao tác.', 403); return; }
        $id = $_GET['id'] ?? null;
        if ($this->tripModel->delete($id)) $this->success([], 'Xóa thành công');
        else $this->error('Lỗi xóa.', 500);
    }

    private function assignOrder() {
        if (!in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) {
            $this->error('Không có quyền thực hiện chức năng này.', 403);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? null;
        $tripId = $data['trip_id'] ?? null;

        if (!$orderId || !$tripId) {
            $this->error('Thiếu thông tin đơn hàng hoặc chuyến xe.', 400);
            return;
        }

        require_once __DIR__ . '/../models/Order.php';
        $orderModel = new Order();
        
        if ($orderModel->assignToTrip($orderId, $tripId)) {
            $this->success(null, 'Gán đơn hàng vào chuyến xe thành công!');
        } else {
            $this->error('Đơn hàng đã được gán hoặc không đủ điều kiện (Chỉ được gán đơn Chờ Xử Lý).', 400);
        }
    }
}