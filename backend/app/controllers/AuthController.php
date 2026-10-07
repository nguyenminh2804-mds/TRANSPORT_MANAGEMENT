<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/User.php';

class AuthController extends Controller
{
    private $userModel;

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
        if (!isset($_SESSION['user_id'])) {
            $this->error(
                'Chưa đăng nhập',
                401
            );
        }

        $user = $this->userModel
            ->findById($_SESSION['user_id']);

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