<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class Customer
{
    private $conn;
    private $table = 'customers';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Lấy danh sách khách hàng
    public function getAll()
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.name,
                    c.phone,
                    c.email,
                    c.address,
                    c.created_at,
                    u.username,
                    u.status
                FROM {$this->table} c
                LEFT JOIN users u
                    ON c.user_id = u.id
                ORDER BY c.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Lấy khách hàng theo ID
    public function findById($id)
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.name,
                    c.phone,
                    c.email,
                    c.address,
                    c.created_at,
                    u.username,
                    u.status
                FROM {$this->table} c
                LEFT JOIN users u
                    ON c.user_id = u.id
                WHERE c.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':id' => $id
        ]);

        return $stmt->fetch();
    }

    // Lấy thông tin khách hàng theo user_id
    public function findByUserId($userId)
    {
        $sql = "SELECT
                    c.id,
                    c.user_id,
                    c.name,
                    c.phone,
                    c.email,
                    c.address,
                    c.created_at,
                    u.username,
                    u.status
                FROM {$this->table} c
                LEFT JOIN users u
                    ON c.user_id = u.id
                WHERE c.user_id = :user_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $stmt->fetch();
    }

    // Thêm khách hàng
    public function create($data)
    {
        $sql = "INSERT INTO {$this->table}
                    (user_id, name, phone, email, address)
                VALUES
                    (:user_id, :name, :phone, :email, :address)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':user_id' => $data['user_id'] ?? null,
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':address' => $data['address']
        ]);
    }

    // Cập nhật thông tin khách hàng
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table}
                SET name = :name,
                    phone = :phone,
                    email = :email,
                    address = :address
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':name' => $data['name'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':address' => $data['address'],
            ':id' => $id
        ]);
    }

    // Xóa khách hàng
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':id' => $id
        ]);
    }

    // Tìm khách hàng theo email
    public function findByEmail($email)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE email = :email
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':email' => $email
        ]);

        return $stmt->fetch();
    }

    // Tìm khách hàng theo số điện thoại
    public function findByPhone($phone)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE phone = :phone
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':phone' => $phone
        ]);

        return $stmt->fetch();
    }
}