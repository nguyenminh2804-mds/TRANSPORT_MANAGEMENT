<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class User
{
    private $conn;

    private $table = 'users';


    public function __construct()
    {
        $database = new Database();

        $this->conn = $database->connect();
    }


    /*
    |--------------------------------------------------------------------------
    | Tìm user theo username
    |--------------------------------------------------------------------------
    | Dùng cho đăng nhập và kiểm tra username trùng
    |--------------------------------------------------------------------------
    */

    public function findByUsername($username)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE username = :username
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':username' => $username
        ]);

        return $stmt->fetch();
    }


    /*
    |--------------------------------------------------------------------------
    | Tìm user theo ID
    |--------------------------------------------------------------------------
    */

    public function findById($id)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->fetch();
    }


    /*
    |--------------------------------------------------------------------------
    | Lấy danh sách người dùng
    |--------------------------------------------------------------------------
    */

    public function getAll()
    {
        $sql = "SELECT
                    id,
                    username,
                    full_name,
                    role,
                    status,
                    created_at
                FROM {$this->table}
                ORDER BY id DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute();

        return $stmt->fetchAll();
    }


    /*
    |--------------------------------------------------------------------------
    | Thêm người dùng
    |--------------------------------------------------------------------------
    */

    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                    (
                        username,
                        password,
                        full_name,
                        role,
                        status
                    )
                VALUES
                    (
                        :username,
                        :password,
                        :full_name,
                        :role,
                        :status
                    )";

        $stmt = $this->conn->prepare($sql);


        return $stmt->execute([
            ':username' => $data['username'],

            // Hash password trước khi lưu DB
            ':password' => password_hash(
                $data['password'],
                PASSWORD_DEFAULT
            ),

            ':full_name' => $data['full_name'],

            ':role' => $data['role'],

            // DB dùng TINYINT:
            // 1 = hoạt động
            // 0 = bị khóa
            ':status' => isset($data['status'])
                ? (int)$data['status']
                : 1
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Cập nhật người dùng
    |--------------------------------------------------------------------------
    |
    | Hiện tại cho phép sửa:
    | - Họ tên
    | - Vai trò
    | - Trạng thái
    |
    | Không sửa username/password.
    |
    |--------------------------------------------------------------------------
    */

    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET
                    full_name = :full_name,
                    role = :role,
                    status = :status
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);


        return $stmt->execute([
            ':full_name' => $data['full_name'],

            ':role' => $data['role'],

            ':status' => (int)$data['status'],

            ':id' => $id
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Xóa người dùng
    |--------------------------------------------------------------------------
    */

    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':id' => $id
        ]);
    }
    // Cập nhật mật khẩu
public function updatePassword($id, $password)
{
    $sql = "UPDATE {$this->table}
            SET password = :password
            WHERE id = :id";

    $stmt = $this->conn->prepare($sql);

    return $stmt->execute([
        ':password' => password_hash(
            $password,
            PASSWORD_DEFAULT
        ),
        ':id' => $id
    ]);
}
    
}