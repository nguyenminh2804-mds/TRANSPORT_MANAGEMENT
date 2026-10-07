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

    // Lấy danh sách người dùng
    public function index()
    {
        $users = $this->userModel->getAll();

        $this->success(
            $users,
            'Lấy danh sách người dùng thành công'
        );
    }

    // Lấy thông tin một người dùng
    public function show($id)
    {
        $user = $this->userModel->findById($id);

        if (!$user) {
            $this->error('Không tìm thấy người dùng', 404);
        }

        unset($user['password']);

        $this->success($user);
    }

    // Thêm người dùng
    public function store()
    {
        $data = json_decode(
            file_get_contents('php://input'),
            true
        );

        $username = trim($data['username'] ?? '');
        $password = $data['password'] ?? '';
        $fullName = trim($data['full_name'] ?? '');
        $role = $data['role'] ?? 'CUSTOMER';
        $status = $data['status'] ?? 'ACTIVE';

        if (
            $username === '' ||
            $password === '' ||
            $fullName === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }

        if ($this->userModel->findByUsername($username)) {
            $this->error(
                'Tên đăng nhập đã tồn tại',
                409
            );
        }

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

    // Cập nhật người dùng
    public function update($id)
    {
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

        $fullName = trim($data['full_name'] ?? '');
        $role = $data['role'] ?? '';
        $status = $data['status'] ?? '';

        if (
            $fullName === '' ||
            $role === '' ||
            $status === ''
        ) {
            $this->error(
                'Vui lòng nhập đầy đủ thông tin'
            );
        }

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

    // Xóa người dùng
    public function destroy($id)
    {
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