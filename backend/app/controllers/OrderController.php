<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Controller.php';

require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Customer.php';

class OrderController extends Controller
{
    private $orderModel;
    private $customerModel;

    public function __construct()
    {
        $database = new Database();
        $db = $database->connect();

        $this->orderModel = new Order($db);
        $this->customerModel = new Customer();
    }

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra quyền CUSTOMER
    |--------------------------------------------------------------------------
    */
    private function requireCustomer()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error(
                'Vui lòng đăng nhập.',
                401
            );
            return false;
        }

        if (
            empty($_SESSION['role']) ||
            $_SESSION['role'] !== 'CUSTOMER'
        ) {
            $this->error(
                'Chức năng này chỉ dành cho khách hàng.',
                403
            );
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Lấy danh sách đơn hàng của khách hàng
    |--------------------------------------------------------------------------
    */
    public function myOrders()
    {
        if (!$this->requireCustomer()) {
            return;
        }

        $customer = $this->customerModel->findByUserId(
            $_SESSION['user_id']
        );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
            return;
        }

        $orders = $this->orderModel->getByCustomerId(
            $customer['id']
        );

        $this->success(
            $orders,
            'Lấy danh sách đơn hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Tra cứu đơn hàng
    |--------------------------------------------------------------------------
    */
    public function track()
    {
        if (!$this->requireCustomer()) {
            return;
        }

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );
            return;
        }

        $orderCode = trim(
            $data['order_code'] ?? ''
        );

        if ($orderCode === '') {
            $this->error(
                'Vui lòng nhập mã đơn hàng',
                400
            );
            return;
        }

        $customer = $this->customerModel->findByUserId(
            $_SESSION['user_id']
        );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
            return;
        }

        $order = $this->orderModel->findByCustomerAndCode(
            $customer['id'],
            $orderCode
        );

        if (!$order) {
            $this->error(
                'Không tìm thấy đơn hàng',
                404
            );
            return;
        }

        $this->success(
            $order,
            'Tra cứu đơn hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UC-DH-01 - TẠO ĐƠN HÀNG
    |--------------------------------------------------------------------------
    */
    public function create()
    {
        // BƯỚC 1 - Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
            return;
        }

        // BƯỚC 2 - Đọc dữ liệu JSON
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );
            return;
        }

        // BƯỚC 3 - Tìm khách hàng đang đăng nhập
        $customer = $this->customerModel->findByUserId(
            $_SESSION['user_id']
        );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
            return;
        }

        // BƯỚC 4 - Lấy thông tin đơn hàng
        $pickupAddress = trim(
            $data['pickup_address'] ?? ''
        );

        $deliveryAddress = trim(
            $data['delivery_address'] ?? ''
        );

        $items = $data['items'] ?? [];

        // BƯỚC 5 - Kiểm tra địa chỉ lấy hàng
        if ($pickupAddress === '') {
            $this->error(
                'Vui lòng nhập địa chỉ lấy hàng',
                400
            );
            return;
        }

        // BƯỚC 6 - Kiểm tra địa chỉ giao hàng
        if ($deliveryAddress === '') {
            $this->error(
                'Vui lòng nhập địa chỉ giao hàng',
                400
            );
            return;
        }

        // BƯỚC 7 - Kiểm tra sản phẩm
        if (
            !is_array($items) ||
            count($items) === 0
        ) {
            $this->error(
                'Đơn hàng phải có ít nhất một sản phẩm',
                400
            );
            return;
        }

        // BƯỚC 8 - Kiểm tra từng sản phẩm
        foreach ($items as $item) {

            // Kiểm tra tên sản phẩm
            if (
                !isset($item['product_name']) ||
                trim($item['product_name']) === ''
            ) {
                $this->error(
                    'Tên sản phẩm không được để trống',
                    400
                );
                return;
            }

            // Kiểm tra số lượng
            if (
                !isset($item['quantity']) ||
                !is_numeric($item['quantity']) ||
                $item['quantity'] <= 0
            ) {
                $this->error(
                    'Số lượng sản phẩm không hợp lệ',
                    400
                );
                return;
            }
        }

        // BƯỚC 9 - Chuẩn bị dữ liệu tạo đơn
        $orderData = [
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'total_weight' => $data['total_weight'] ?? 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'items' => $items
        ];

        // BƯỚC 10 - Lưu đơn hàng
        try {
            $order = $this->orderModel->createOrder(
                $customer['id'],
                $orderData
            );

            // BƯỚC 11 - Trả kết quả
            $this->success(
                $order,
                'Tạo đơn hàng thành công'
            );

            return;

        } catch (Exception $e) {

            /*
             * Nếu database xảy ra lỗi,
             * Order Model sẽ rollback transaction.
             */

            $this->error(
                'Không thể tạo đơn hàng',
                500
            );

            return;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | UC-DH-02 - NHÂN VIÊN XỬ LÝ ĐƠN HÀNG
    |--------------------------------------------------------------------------
    */
    public function process()
    {
        // BƯỚC 1 - Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
            return;
        }

        // BƯỚC 2 - Kiểm tra quyền STAFF
        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'STAFF'
        ) {
            $this->error(
                'Bạn không có quyền xử lý đơn hàng',
                403
            );
            return;
        }

        // BƯỚC 3 - Đọc dữ liệu JSON
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );
            return;
        }

        // BƯỚC 4 - Lấy ID đơn hàng
        $orderId = $data['order_id'] ?? null;

        if (
            $orderId === null ||
            !is_numeric($orderId)
        ) {
            $this->error(
                'ID đơn hàng không hợp lệ',
                400
            );
            return;
        }

        // BƯỚC 5 - Lấy trạng thái mới
        $newStatus = strtoupper(
            trim($data['status'] ?? '')
        );

        if ($newStatus === '') {
            $this->error(
                'Vui lòng nhập trạng thái mới',
                400
            );
            return;
        }

        // BƯỚC 6 - Kiểm tra trạng thái hợp lệ
        $allowedStatuses = [
            'PENDING',
            'CONFIRMED',
            'PICKING_UP',
            'IN_TRANSIT',
            'DELIVERED',
            'CANCELLED'
        ];

        if (!in_array(
            $newStatus,
            $allowedStatuses,
            true
        )) {
            $this->error(
                'Trạng thái đơn hàng không hợp lệ',
                400
            );
            return;
        }

        // BƯỚC 7 - Lấy đơn hàng hiện tại
        $order = $this->orderModel->findById(
            $orderId
        );

        if (!$order) {
            $this->error(
                'Không tìm thấy đơn hàng',
                404
            );
            return;
        }

        // BƯỚC 8 - Kiểm tra trạng thái hiện tại
        $currentStatus = $order['status'];

        /*
         * UC-DH-02:
         * Nhân viên chỉ xử lý đơn đang chờ xử lý.
         */
        if ($currentStatus !== 'PENDING') {
            $this->error(
                'Đơn hàng hiện tại không được phép xử lý',
                400
            );
            return;
        }

        // BƯỚC 9 - Cập nhật trạng thái
        try {
            $result = $this->orderModel->updateStatus(
                $orderId,
                $newStatus
            );

            // BƯỚC 10 - Trả kết quả
            $this->success(
                $result,
                'Xử lý đơn hàng thành công'
            );

            return;

        } catch (Exception $e) {

            /*
             * Nếu database lỗi:
             * - Không cập nhật trạng thái
             * - Trạng thái cũ được giữ nguyên
             */

            $this->error(
                'Không thể cập nhật trạng thái đơn hàng',
                500
            );

            return;
        }
    }
}