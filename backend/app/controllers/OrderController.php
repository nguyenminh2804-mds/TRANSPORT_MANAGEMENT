<?php
require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Customer.php';
class OrderController extends Controller
{
    private $orderModel;
    private $customerModel;

    public function __construct()
    {
        $this->orderModel = new Order();
        $this->customerModel = new Customer();
    }

    /*
    |--------------------------------------------------------------------------
    | Hàm điều hướng API (Bắt buộc phải có để index.php gọi tới)
    |--------------------------------------------------------------------------
    */
    public function handle($action)
    {
        header('Cache-Control: no-store, private');
        
        switch ($action) {
            case 'updatePaymentStatus':
                $this->updatePaymentStatus();
           break;
            case 'update':
                $this->update();
                break;
            case 'cancel':
                $this->cancel();
                break;
            case 'index':
                $this->index(); // Dành cho Admin/Staff lấy tất cả đơn
                break;
            case 'myOrders':
                $this->myOrders();
                break;
            case 'track':
                $this->track();
                break;
            case 'store':
                $this->store(); // Hàm tạo đơn hàng mới
                break;
            case 'updateStatus':
                $this->updateStatus(); // Bổ sung cập nhật trạng thái đơn hàng
                break;
            default:
                $this->error('Hành động không hợp lệ.', 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra quyền CUSTOMER
    |--------------------------------------------------------------------------
    */
    private function requireCustomer()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
        }

        if (empty($_SESSION['role']) || $_SESSION['role'] !== 'CUSTOMER') {
            $this->error('Chức năng này chỉ dành cho khách hàng.', 403);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get logged-in customer's orders
    |--------------------------------------------------------------------------
    */
    public function myOrders()
    {
        $this->requireCustomer();

        $customer = $this->customerModel->findByUserId($_SESSION['user_id']);

        if (!$customer) {
            $this->error('Không tìm thấy thông tin khách hàng', 404);
        }

        $orders = $this->orderModel->getByCustomerId($customer['id']);

        $this->success($orders, 'Lấy danh sách đơn hàng thành công');
    }

    /*
    |--------------------------------------------------------------------------
    | Track order
    |--------------------------------------------------------------------------
    */
    public function track()
    {
        $this->requireCustomer();

        $data = json_decode(file_get_contents('php://input'), true);

        $orderCode = trim($data['order_code'] ?? '');

        if ($orderCode === '') {
            $this->error('Vui lòng nhập mã đơn hàng');
        }

        $customer = $this->customerModel->findByUserId($_SESSION['user_id']);

        if (!$customer) {
            $this->error('Không tìm thấy thông tin khách hàng', 404);
        }

        $order = $this->orderModel->findByCustomerAndCode($customer['id'], $orderCode);

        if (!$order) {
            $this->error('Không tìm thấy đơn hàng', 404);
        }
        
        $this->success($order, 'Tra cứu đơn hàng thành công');
    }

    /*
    |--------------------------------------------------------------------------
    | Tạo đơn hàng mới (STORE) - ĐÃ BỔ SUNG ĐẦY ĐỦ THÔNG TIN TỪ FORM
    |--------------------------------------------------------------------------
    */
    public function store()
    {
        $this->requireCustomer();

        $customer = $this->customerModel->findByUserId($_SESSION['user_id']);
        if (!$customer) {
            $this->error('Không tìm thấy thông tin khách hàng.', 404);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Kiểm tra các trường thông tin bắt buộc từ giao diện mới
        if (
            empty($data['sender_phone']) || 
            empty($data['pickup_address']) || 
            empty($data['receiver_name']) || 
            empty($data['receiver_phone']) || 
            empty($data['delivery_address']) || 
            empty($data['total_weight'])
        ) {
            $this->error('Vui lòng nhập đầy đủ thông tin bắt buộc (số điện thoại, địa chỉ, người nhận và khối lượng).', 400);
        }

        $weight = (float)$data['total_weight'];
        if ($weight <= 0) {
            $this->error('Khối lượng phải lớn hơn 0.', 400);
        }

        // Tạo mã đơn và tính phí
        $orderCode = 'DH' . time() . rand(10, 99);
        $shippingFee = 15000 + ($weight * 5000);

        // Đóng gói toàn bộ dữ liệu để lưu vào CSDL
        $orderData = [
            'order_code' => $orderCode,
            'customer_id' => $customer['id'],
            
            // Thông tin gửi (Nếu không nhập tên, lấy tên khách hàng)
            'sender_name' => trim($data['sender_name'] ?? $customer['full_name']), 
            'sender_phone' => trim($data['sender_phone']),
            'pickup_address' => trim($data['pickup_address']),
            
            // Thông tin nhận
            'receiver_name' => trim($data['receiver_name']),
            'receiver_phone' => trim($data['receiver_phone']),
            'delivery_address' => trim($data['delivery_address']),
            
            // Thông tin hàng, Phí và Ghi chú
            'goods_type' => trim($data['goods_type'] ?? ''),
            'total_weight' => $weight,
            'shipping_fee' => $shippingFee,
            'payment_method' => trim($data['payment_method'] ?? 'SENDER_PAY'),
            'note' => trim($data['note'] ?? '')
        ];

        // Xử lý logic thanh toán: Nếu chọn "Người nhận trả" thì trạng thái thanh toán là UNPAID
        if ($orderData['payment_method'] === 'RECEIVER_PAY') {
            $orderData['payment_status'] = 'UNPAID';
        }

        // Gọi model để tạo đơn
        if ($this->orderModel->create($orderData)) {
            $this->success(['order_code' => $orderCode], 'Tạo đơn hàng thành công!');
        } else {
            $this->error('Lỗi khi lưu đơn hàng vào hệ thống.', 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Lấy toàn bộ đơn hàng cho Staff / Admin
    |--------------------------------------------------------------------------
    */
    public function index()
    {
        // Thêm dòng này để tắt cảnh báo rác, bảo vệ chuẩn JSON
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

        if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['ADMIN', 'STAFF', 'DRIVER'], true)) {
            $this->error('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        $orders = $this->orderModel->getAllOrdersForStaff();
        $this->success($orders, 'Lấy danh sách đơn hàng thành công');
    }

    
    public function updateStatus()
    {
        // Tắt các cảnh báo rác để JSON không bị lỗi
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

        $role = $_SESSION['role'] ?? '';
        
        // Cấp quyền cho Admin, Staff và cả Driver
        if (empty($role) || !in_array($role, ['ADMIN', 'STAFF', 'DRIVER'], true)) {
            $this->error('Bạn không có quyền thực hiện chức năng này.', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? null;
        $newStatus = $data['status'] ?? null;
        $cancelReason = trim($data['cancel_reason'] ?? ''); // Bắt lý do hủy từ Frontend gửi lên

        if (!$orderId || !$newStatus) {
            $this->error('Thiếu thông tin mã đơn hàng hoặc trạng thái mới.', 400);
        }

        try {
            // Dùng cấu trúc kết nối an toàn như TripController
            require_once __DIR__ . '/../../core/Database.php';
            $dbInstance = new Database();
            $db = $dbInstance->connect();

            if (!$db) {
                $this->error('Không thể kết nối CSDL.', 500);
                return;
            }

            // 1. Lấy thông tin đơn hàng hiện tại
            $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                $this->error('Không tìm thấy đơn hàng trong hệ thống.', 404);
            }

            // 2. KIỂM TRA BẢO MẬT DÀNH RIÊNG CHO TÀI XẾ
            if ($role === 'DRIVER') {
                require_once __DIR__ . '/../models/Driver.php';
                $driverModel = new Driver();
                $driver = $driverModel->findByUserId($_SESSION['user_id']);
                
                if (!$driver) {
                    $this->error('Không tìm thấy hồ sơ tài xế của bạn.', 403);
                }

                // Chặn tài xế sửa đơn của chuyến xe khác
                $stmtCheck = $db->prepare("SELECT id FROM trips WHERE id = ? AND driver_id = ?");
                $stmtCheck->execute([$order['trip_id'], $driver['driver_id']]);
                
                if (!$stmtCheck->fetch()) {
                    $this->error('Cảnh báo: Bạn không được phép cập nhật đơn hàng của xe khác!', 403);
                }
            }

            // 3. LƯU TRẠNG THÁI (VÀ LÝ DO HỦY VÀO GHI CHÚ NẾU CÓ)
            $sql = "UPDATE orders SET status = ?";
            $params = [$newStatus];

            // Nếu hủy đơn và có nhập lý do -> Nối lý do đó vào cột Ghi chú (note)
            if ($newStatus === 'CANCELLED' && $cancelReason !== '') {
                $sql .= ", note = CONCAT(IFNULL(note, ''), '\n[LÝ DO HỦY TỪ TÀI XẾ]: ', ?)";
                $params[] = $cancelReason;
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $orderId;

            $stmtUpdate = $db->prepare($sql);
            if ($stmtUpdate->execute($params)) {
                $this->success(null, 'Cập nhật trạng thái đơn hàng thành công!');
            } else {
                $this->error('Không thể lưu trạng thái vào cơ sở dữ liệu.', 500);
            }
        } catch (Exception $e) {
            $this->error('Lỗi hệ thống: ' . $e->getMessage(), 500);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | KHÁCH HÀNG SỬA ĐƠN
    |--------------------------------------------------------------------------
    */
    public function update()
    {
        $this->requireCustomer();
        $customer = $this->customerModel->findByUserId($_SESSION['user_id']);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? null;

        if (!$orderId) {
            $this->error('Thiếu mã đơn hàng cần sửa.', 400);
        }

        // Tính lại phí vận chuyển nếu khối lượng thay đổi
        $weight = (float)($data['total_weight'] ?? 0);
        if ($weight <= 0) $this->error('Khối lượng không hợp lệ.', 400);
        $shippingFee = 15000 + ($weight * 5000);
        $data['shipping_fee'] = $shippingFee;

        // Lưu dữ liệu sửa
        if ($this->orderModel->updateCustomerOrder($orderId, $customer['id'], $data)) {
            $this->success(null, 'Cập nhật đơn hàng thành công!');
        } else {
            $this->error('Không thể sửa đơn hàng này. (Đơn có thể đã được duyệt hoặc không tồn tại)', 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | KHÁCH HÀNG HỦY ĐƠN
    |--------------------------------------------------------------------------
    */
    public function cancel()
    {
        $this->requireCustomer();
        $customer = $this->customerModel->findByUserId($_SESSION['user_id']);
        
        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? null;

        if (!$orderId) {
            $this->error('Thiếu mã đơn hàng cần hủy.', 400);
        }

        if ($this->orderModel->cancelCustomerOrder($orderId, $customer['id'])) {
            $this->success(null, 'Hủy đơn hàng thành công!');
        } else {
            $this->error('Chỉ có thể hủy những đơn hàng đang chờ xử lý.', 400);
        }
    }
    public function updatePaymentStatus()
    {
        // 1. BẢO MẬT: Kiểm tra quyền (Chỉ ADMIN và STAFF mới được phép đổi trạng thái tiền)
        if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) {
            $this->error('Từ chối truy cập. Bạn không có quyền thực hiện thao tác này.', 403);
            return;
        }

        // 2. Nhận dữ liệu gửi lên từ Frontend
        $data = json_decode(file_get_contents('php://input'), true);
        $orderId = $data['order_id'] ?? null;
        $status = $data['status'] ?? 'PAID';

        // 3. Validate dữ liệu
        if (!$orderId) {
            $this->error('Thiếu mã đơn hàng cần cập nhật.', 400);
            return;
        }

        // 4. Gọi Model để lưu vào Database
        if ($this->orderModel->updatePaymentStatus($orderId, $status)) {
            $this->success(null, 'Cập nhật trạng thái thanh toán thành công!');
        } else {
            $this->error('Không thể cập nhật trạng thái thanh toán trong cơ sở dữ liệu.', 500);
        }
        return;
    }
}