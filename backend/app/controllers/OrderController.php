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
        }

        if (
            empty($_SESSION['role']) ||
            $_SESSION['role'] !== 'CUSTOMER'
        ) {
            $this->error(
                'Chức năng này chỉ dành cho khách hàng.',
                403
            );
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
        $this->requireCustomer();

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
}