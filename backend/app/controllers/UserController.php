<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/User.php';

class UserController extends Controller
{
    private $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /*
    |--------------------------------------------------------------------------
    | Kiểm tra quyền ADMIN
    |--------------------------------------------------------------------------
    */

    private function requireAdmin()
    {
        // Chưa đăng nhập
        if (empty($_SESSION['user_id'])) {
            $this->error(
                'Vui lòng đăng nhập.',
                401
            );
        }

        // Lấy thông tin user hiện tại từ database
        $currentUser = $this->userModel->findById(
            $_SESSION['user_id']
        );

        // User không tồn tại hoặc đã bị khóa
        if (
            !$currentUser ||
            (int)$currentUser['status'] !== 1
        ) {
            $this->error(
                'Tài khoản không hợp lệ hoặc đã bị khóa.',
                401
            );
        }

        // Không phải ADMIN
        if ($currentUser['role'] !== 'ADMIN') {
            $this->error(
                'Bạn không có quyền thực hiện chức năng này.',
                403
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Lấy danh sách người dùng
    |--------------------------------------------------------------------------
    */

    public function index()
    {
        $this->requireAdmin();

        $users = $this->userModel->getAll();

        $this->success(
            $users,
            'Lấy danh sách người dùng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lấy thông tin một người dùng
    |--------------------------------------------------------------------------
    */

    public function show($id)
    {
        $this->requireAdmin();

        $user = $this->userModel->findById($id);

        if (!$user) {
            $this->error(
                'Không tìm thấy người dùng',
                404
            );
        }

        // Không trả password về frontend
        unset($user['password']);

        $this->success($user);
    }

    /*
    |--------------------------------------------------------------------------
    | Thêm người dùng
    |--------------------------------------------------------------------------
    */

    public function store()
    {
        $this->requireAdmin();

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $username = trim(
            $data['username'] ?? ''
        );

        $password = $data['password'] ?? '';

        $fullName = trim(
            $data['full_name'] ?? ''
        );

        $role = $data['role'] ?? 'CUSTOMER';

        // users.status là TINYINT:
        // 1 = hoạt động
        // 0 = bị khóa
        $status = isset($data['status'])
            ? (int)$data['status']
            : 1;

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            $username === '' ||
            $password === '' ||
            $fullName === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate role
        |--------------------------------------------------------------------------
        */

        $allowedRoles = [
            'ADMIN',
            'STAFF',
            'DRIVER',
            'CUSTOMER'
        ];

        if (!in_array($role, $allowedRoles, true)) {
            $this->error(
                'Vai trò không hợp lệ'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate status
        |--------------------------------------------------------------------------
        */

        if ($status !== 0 && $status !== 1) {
            $this->error(
                'Trạng thái tài khoản không hợp lệ'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Kiểm tra username
        |--------------------------------------------------------------------------
        */

        if ($this->userModel->findByUsername($username)) {
            $this->error(
                'Tên đăng nhập đã tồn tại',
                409
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Tạo user
        |--------------------------------------------------------------------------
        */

        $created = $this->userModel->create([
            'username' => $username,
            'password' => $password,
            'full_name' => $fullName,
            'role' => $role,
            'status' => $status
        ]);

        if (!$created) {
            $this->error(
                'Không thể tạo người dùng',
                500
            );
        }

        $this->success(
            [],
            'Tạo người dùng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Cập nhật người dùng
    |--------------------------------------------------------------------------
    */

    public function update($id)
    {
        $this->requireAdmin();

        $user = $this->userModel->findById($id);

        if (!$user) {
            $this->error(
                'Không tìm thấy người dùng',
                404
            );
        }

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $fullName = trim(
            $data['full_name'] ?? ''
        );

        $role = $data['role'] ?? '';

        $status = isset($data['status'])
            ? (int)$data['status']
            : -1;

        /*
        |--------------------------------------------------------------------------
        | Validate
        |--------------------------------------------------------------------------
        */

        if (
            $fullName === '' ||
            $role === '' ||
            $status === -1
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate role
        |--------------------------------------------------------------------------
        */

        $allowedRoles = [
            'ADMIN',
            'STAFF',
            'DRIVER',
            'CUSTOMER'
        ];

        if (!in_array($role, $allowedRoles, true)) {
            $this->error(
                'Vai trò không hợp lệ'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Validate status
        |--------------------------------------------------------------------------

        */

        if ($status !== 0 && $status !== 1) {
            $this->error(
                'Trạng thái tài khoản không hợp lệ'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Update
        |--------------------------------------------------------------------------
        */

        $updated = $this->userModel->update(
            $id,
            [
                'full_name' => $fullName,
                'role' => $role,
                'status' => $status
            ]
        );

        if (!$updated) {
            $this->error(
                'Không thể cập nhật người dùng',
                500
            );
        }

        $this->success(
            [],
            'Cập nhật người dùng thành công'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Xóa người dùng
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $this->requireAdmin();

        $user = $this->userModel->findById($id);

        if (!$user) {
            $this->error(
                'Không tìm thấy người dùng',
                404
            );
        }

        $deleted = $this->userModel->delete($id);

        if (!$deleted) {
            $this->error(
                'Không thể xóa người dùng',
                500
            );
        }

        $this->success(
            [],
            'Xóa người dùng thành công'
        );
    }
}