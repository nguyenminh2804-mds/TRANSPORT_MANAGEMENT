<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Customer.php';

class CustomerController extends Controller
{
    private $customerModel;

    public function __construct()
    {
        $this->customerModel = new Customer();
    }

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra quyền ADMIN / STAFF
    |--------------------------------------------------------------------------
    */

    private function requireManager()
    {
        if (empty($_SESSION['user_id'])) {
            $this->error(
                'Vui lòng đăng nhập.',
                401
            );
        }

        if (
            empty($_SESSION['role']) ||
            !in_array(
                $_SESSION['role'],
                ['ADMIN', 'STAFF'],
                true
            )
        ) {
            $this->error(
                'Bạn không có quyền thực hiện chức năng này.',
                403
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra CUSTOMER
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
    | Get all customers
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->requireManager();

        $customers = $this->customerModel->getAll();

        $this->success(
            $customers,
            'Lấy danh sách khách hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Get customer by ID
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $this->requireManager();

        $customer = $this->customerModel
            ->findById($id);

        if (!$customer) {
            $this->error(
                'Không tìm thấy khách hàng',
                404
            );
        }

        $this->success($customer);
    }

    /*
    |--------------------------------------------------------------------------
    | Get logged-in customer's profile
    |--------------------------------------------------------------------------
    */

    public function profile()
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

        $this->success(
            $customer,
            'Lấy thông tin khách hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Create customer
    |--------------------------------------------------------------------------
    */

    public function store()
    {
        $this->requireManager();

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $name = trim(
            $data['name'] ?? ''
        );

        $phone = trim(
            $data['phone'] ?? ''
        );

        $email = trim(
            $data['email'] ?? ''
        );

        $address = trim(
            $data['address'] ?? ''
        );

        $userId = $data['user_id'] ?? null;

        if (
            $name === '' ||
            $phone === '' ||
            $email === '' ||
            $address === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin khách hàng'
            );
        }

        $created = $this->customerModel->create([
            'user_id' => $userId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address
        ]);

        if (!$created) {
            $this->error(
                'Không thể tạo khách hàng',
                500
            );
        }

        $this->success(
            [],
            'Tạo khách hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update customer
    |--------------------------------------------------------------------------
    */

    public function update($id)
    {
        $this->requireManager();

        $customer = $this->customerModel
            ->findById($id);

        if (!$customer) {
            $this->error(
                'Không tìm thấy khách hàng',
                404
            );
        }

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $name = trim(
            $data['name'] ?? ''
        );

        $phone = trim(
            $data['phone'] ?? ''
        );

        $email = trim(
            $data['email'] ?? ''
        );

        $address = trim(
            $data['address'] ?? ''
        );

        if (
            $name === '' ||
            $phone === '' ||
            $email === '' ||
            $address === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin khách hàng'
            );
        }

        $updated = $this->customerModel->update(
            $id,
            [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ]
        );

        if (!$updated) {
            $this->error(
                'Không thể cập nhật khách hàng',
                500
            );
        }

        $this->success(
            [],
            'Cập nhật khách hàng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Delete customer
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $this->requireManager();

        $customer = $this->customerModel
            ->findById($id);

        if (!$customer) {
            $this->error(
                'Không tìm thấy khách hàng',
                404
            );
        }

        $deleted = $this->customerModel
            ->delete($id);

        if (!$deleted) {
            $this->error(
                'Không thể xóa khách hàng',
                500
            );
        }

        $this->success(
            [],
            'Xóa khách hàng thành công'
        );
    }
}