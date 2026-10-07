<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Customer.php';

class AuthController extends Controller
{
    private $userModel;
    private $customerModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->customerModel = new Customer();
    }


    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $username = trim(
            $data['username'] ?? ''
        );

        $password = $data['password'] ?? '';

        if ($username === '' || $password === '') {
            $this->error(
                'Vui lòng nhập đầy đủ tài khoản và mật khẩu'
            );
        }

        $user = $this->userModel
            ->findByUsername($username);

        if (!$user) {
            $this->error(
                'Tài khoản hoặc mật khẩu không đúng',
                401
            );
        }

        if ((int)$user['status'] !== 1) {
            $this->error(
                'Tài khoản đã bị khóa',
                403
            );
        }

        if (!password_verify(
            $password,
            $user['password']
        )) {
            $this->error(
                'Tài khoản hoặc mật khẩu không đúng',
                401
            );
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        unset($user['password']);

        $this->success(
            $user,
            'Đăng nhập thành công'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Customer Register
    |--------------------------------------------------------------------------
    */

    public function registerCustomer()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $username = trim(
            $data['username'] ?? ''
        );

        $password = $data['password'] ?? '';

        $confirmPassword = $data['confirm_password'] ?? '';

        $fullName = trim(
            $data['full_name'] ?? ''
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


        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            $username === '' ||
            $password === '' ||
            $confirmPassword === '' ||
            $fullName === '' ||
            $phone === '' ||
            $email === '' ||
            $address === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }


        if (strlen($username) < 4) {
            $this->error(
                'Tên đăng nhập phải có ít nhất 4 ký tự'
            );
        }


        if (strlen($password) < 6) {
            $this->error(
                'Mật khẩu phải có ít nhất 6 ký tự'
            );
        }


        if ($password !== $confirmPassword) {
            $this->error(
                'Mật khẩu xác nhận không khớp'
            );
        }


        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error(
                'Email không hợp lệ'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check username
        |--------------------------------------------------------------------------
        */

        if (
            $this->userModel
                ->findByUsername($username)
        ) {
            $this->error(
                'Tên đăng nhập đã tồn tại',
                409
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Check customer email / phone
        |--------------------------------------------------------------------------
        */

        if (
            $this->customerModel
                ->findByEmail($email)
        ) {
            $this->error(
                'Email đã được sử dụng',
                409
            );
        }


        if (
            $this->customerModel
                ->findByPhone($phone)
        ) {
            $this->error(
                'Số điện thoại đã được sử dụng',
                409
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create user
        |--------------------------------------------------------------------------
        */

        $createdUser = $this->userModel->create([
            'username' => $username,
            'password' => $password,
            'full_name' => $fullName,
            'role' => 'CUSTOMER',
            'status' => 1
        ]);


        if (!$createdUser) {
            $this->error(
                'Không thể tạo tài khoản',
                500
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Get created user
        |--------------------------------------------------------------------------
        */

        $user = $this->userModel
            ->findByUsername($username);


        if (!$user) {
            $this->error(
                'Không tìm thấy tài khoản vừa tạo',
                500
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Create customer profile
        |--------------------------------------------------------------------------
        */

        $createdCustomer =
            $this->customerModel->create([
                'user_id' => $user['id'],
                'name' => $fullName,
                'phone' => $phone,
                'email' => $email,
                'address' => $address
            ]);


        if (!$createdCustomer) {

            // Xóa user vừa tạo nếu tạo customer thất bại
            $this->userModel->delete(
                $user['id']
            );

            $this->error(
                'Không thể tạo thông tin khách hàng',
                500
            );
        }


        $this->success(
            [],
            'Đăng ký tài khoản thành công'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Forgot Password
    |--------------------------------------------------------------------------
    */

    public function forgotPassword()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $username = trim(
            $data['username'] ?? ''
        );

        $fullName = trim(
            $data['full_name'] ?? ''
        );

        $newPassword = $data['new_password'] ?? '';

        $confirmPassword =
            $data['confirm_password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            $username === '' ||
            $fullName === '' ||
            $newPassword === '' ||
            $confirmPassword === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }


        if ($newPassword !== $confirmPassword) {
            $this->error(
                'Mật khẩu xác nhận không khớp'
            );
        }


        if (strlen($newPassword) < 6) {
            $this->error(
                'Mật khẩu mới phải có ít nhất 6 ký tự'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Find user
        |--------------------------------------------------------------------------
        */

        $user = $this->userModel
            ->findByUsername($username);


        if (!$user) {
            $this->error(
                'Thông tin xác minh không chính xác',
                404
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Verify full name
        |--------------------------------------------------------------------------
        */

        if (
            mb_strtolower(
                trim($user['full_name'])
            )
            !==
            mb_strtolower($fullName)
        ) {
            $this->error(
                'Thông tin xác minh không chính xác',
                400
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Update password
        |--------------------------------------------------------------------------
        */

        $updated =
            $this->userModel->updatePassword(
                $user['id'],
                $newPassword
            );


        if (!$updated) {
            $this->error(
                'Không thể cập nhật mật khẩu',
                500
            );
        }


        $this->success(
            [],
            'Đặt lại mật khẩu thành công'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout()
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params =
                session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'] ?? '',
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        $this->success(
            [],
            'Đăng xuất thành công'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Current User
    |--------------------------------------------------------------------------
    */

    public function me()
    {
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        $user = $this->userModel
            ->findById(
                $_SESSION['user_id']
            );

        if (!$user) {
            $this->error(
                'Không tìm thấy người dùng',
                404
            );
        }

        unset($user['password']);

        $this->success($user);
    }
}