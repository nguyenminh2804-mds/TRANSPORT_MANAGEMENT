<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends Controller
{
    private $userModel;

    // Giữ nguyên module User; chỉ chuyển lỗi database thành phản hồi JSON.
    private function findAccount($method, $value)
    {
        try {
            return $this->userModel->$method($value);
        } catch (PDOException $e) {
            error_log('Account integration: ' . $e->getMessage());
            if ((int)($e->errorInfo[1] ?? 0) === 1146) {
                $this->error('Chưa có bảng users trong database. Cần schema tài khoản chính thức trước khi đăng nhập hoặc quản lý tài xế.', 503);
            }
            $this->error('Không thể đọc tài khoản. Kiểm tra kết nối và schema module users.', 500);
        }
    }


    public function __construct()
    {
        $this->userModel = new User();
    }

    /*
    |--------------------------------------------------------------------------
    | Login
    |--------------------------------------------------------------------------
    */

    public function login()
    {
        header('Cache-Control: no-store, private');
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

        $user = $this->findAccount('findByUsername', $username);

        if (!$user) {
            $this->error(
                'Tài khoản hoặc mật khẩu không đúng',
                401
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check account status
        |--------------------------------------------------------------------------
        */

        if ((int)$user['status'] !== 1) {
            $this->error(
                'Tài khoản đã bị khóa',
                403
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Check password
        |--------------------------------------------------------------------------
        */

        if (!password_verify(
            $password,
            $user['password']
        )) {
            $this->error(
                'Tài khoản hoặc mật khẩu không đúng',
                401
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Save session
        |--------------------------------------------------------------------------
        */

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        /*
        |--------------------------------------------------------------------------
        | Remove password before response
        |--------------------------------------------------------------------------
        */

        unset($user['password']);

        $this->success(
            $user,
            'Đăng nhập thành công'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Logout
    |--------------------------------------------------------------------------
    */

    public function logout()
    {
        header('Cache-Control: no-store, private');
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

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
    | Current logged-in user
    |--------------------------------------------------------------------------
    */

    public function me()
    {
        header('Cache-Control: no-store, private');
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        $user = $this->findAccount('findById', $_SESSION['user_id']);

        if (!$user) {
            $this->error(
                'Phiên đăng nhập không còn hợp lệ',
                401
            );
        }

        if ((int)$user['status'] !== 1) {
            $this->error('Tài khoản đã bị khóa', 403);
        }

        unset($user['password']);

        $this->success($user);
    }
}