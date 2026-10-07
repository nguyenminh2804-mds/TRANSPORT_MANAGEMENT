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
}