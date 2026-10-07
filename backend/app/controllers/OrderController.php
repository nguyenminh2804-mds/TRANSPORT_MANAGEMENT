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
    | Get logged-in customer's orders
    |--------------------------------------------------------------------------
    */

    public function myOrders()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        $customer = $this->customerModel
            ->findByUserId(
                $_SESSION['user_id']
            );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
        }

        $orders = $this->orderModel
            ->getByCustomerId(
                $customer['id']
            );

        $this->success(
            $orders,
            'Lấy danh sách đơn hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Track order
    |--------------------------------------------------------------------------
    */

    public function track()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $orderCode = trim(
            $data['order_code'] ?? ''
        );

        if ($orderCode === '') {
            $this->error(
                'Vui lòng nhập mã đơn hàng'
            );
        }

        $customer = $this->customerModel
            ->findByUserId(
                $_SESSION['user_id']
            );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
        }

        $order = $this->orderModel
            ->findByCustomerAndCode(
                $customer['id'],
                $orderCode
            );

        if (!$order) {
            $this->error(
                'Không tìm thấy đơn hàng',
                404
            );
        }

        $this->success(
            $order,
            'Tra cứu đơn hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create order
    |--------------------------------------------------------------------------
    */

    public function create()
    {
        // Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        // Đọc dữ liệu JSON từ request
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        // Kiểm tra dữ liệu JSON
        if (!is_array($data)) {
            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );
        }

        // Lấy thông tin khách hàng đang đăng nhập
        $customer = $this->customerModel
            ->findByUserId(
                $_SESSION['user_id']
            );

        if (!$customer) {
            $this->error(
                'Không tìm thấy thông tin khách hàng',
                404
            );
        }

        // Lấy thông tin đơn hàng
        $pickupAddress = trim(
            $data['pickup_address'] ?? ''
        );

        $deliveryAddress = trim(
            $data['delivery_address'] ?? ''
        );

        $items = $data['items'] ?? [];

        // Kiểm tra địa chỉ lấy hàng
        if ($pickupAddress === '') {
            $this->error(
                'Vui lòng nhập địa chỉ lấy hàng'
            );
        }

        // Kiểm tra địa chỉ giao hàng
        if ($deliveryAddress === '') {
            $this->error(
                'Vui lòng nhập địa chỉ giao hàng'
            );
        }

        // Kiểm tra sản phẩm
        if (!is_array($items) || count($items) === 0) {
            $this->error(
                'Đơn hàng phải có ít nhất một sản phẩm'
            );
        }

        // Kiểm tra từng sản phẩm
        foreach ($items as $item) {

            if (
                !isset($item['product_name']) ||
                trim($item['product_name']) === ''
            ) {
                $this->error(
                    'Tên sản phẩm không được để trống'
                );
            }

            if (
                !isset($item['quantity']) ||
                !is_numeric($item['quantity']) ||
                $item['quantity'] <= 0
            ) {
                $this->error(
                    'Số lượng sản phẩm không hợp lệ'
                );
            }
        }

        // Chuẩn bị dữ liệu để tạo đơn
        $orderData = [
            'pickup_address' => $pickupAddress,
            'delivery_address' => $deliveryAddress,
            'total_weight' => $data['total_weight'] ?? 0,
            'shipping_fee' => $data['shipping_fee'] ?? 0,
            'items' => $items
        ];

        try {

            // Tạo đơn hàng
            $order = $this->orderModel->createOrder(
                $customer['id'],
                $orderData
            );

            // Trả kết quả
            $this->success(
                $order,
                'Tạo đơn hàng thành công'
            );

        } catch (Exception $e) {

            // Lỗi khi lưu database
            $this->error(
                'Không thể tạo đơn hàng',
                500
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Process order - UC-DH-02
    |--------------------------------------------------------------------------
    */

    public function process()
    {
        // Kiểm tra đăng nhập
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        // Kiểm tra quyền nhân viên xử lý đơn
        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'STAFF'
        ) {
            $this->error(
                'Bạn không có quyền xử lý đơn hàng',
                403
            );
        }

        // Đọc dữ liệu JSON
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($data)) {
            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );
        }

        // Lấy ID đơn hàng
        $orderId = $data['order_id'] ?? null;

        // Lấy trạng thái mới
        $newStatus = strtoupper(
            trim($data['status'] ?? '')
        );

        // Kiểm tra ID đơn hàng
        if (
            $orderId === null ||
            !is_numeric($orderId)
        ) {
            $this->error(
                'ID đơn hàng không hợp lệ'
            );
        }

        // Kiểm tra trạng thái mới
        if ($newStatus === '') {
            $this->error(
                'Vui lòng nhập trạng thái mới'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra trạng thái hợp lệ
        |--------------------------------------------------------------------------
        */

        $allowedStatuses = [
            'PENDING',
            'PICKING_UP',
            'IN_TRANSIT',
            'DELIVERED',
            'CANCELLED',
            'RETURNED'
        ];

        if (!in_array(
            $newStatus,
            $allowedStatuses
        )) {
            $this->error(
                'Trạng thái đơn hàng không hợp lệ'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Lấy đơn hàng hiện tại
        |--------------------------------------------------------------------------
        */

        $order = $this->orderModel
            ->findById($orderId);

        if (!$order) {
            $this->error(
                'Không tìm thấy đơn hàng',
                404
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra trạng thái hiện tại
        |--------------------------------------------------------------------------
        */

        $currentStatus = $order['status'];

        /*
        | Chỉ cho phép xử lý đơn đang ở trạng thái PENDING
        */

        if ($currentStatus !== 'PENDING') {
            $this->error(
                'Đơn hàng hiện tại không được phép xử lý'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Cập nhật trạng thái
        |--------------------------------------------------------------------------
        */

        try {

            $result = $this->orderModel
                ->updateStatus(
                    $orderId,
                    $newStatus
                );

            $this->success(
                $result,
                'Xử lý đơn hàng thành công'
            );

        } catch (Exception $e) {

            /*
            | Nếu cập nhật database lỗi,
            | trạng thái cũ được giữ nguyên
            */

            $this->error(
                'Không thể cập nhật trạng thái đơn hàng',
                500
            );
        }
    }
}