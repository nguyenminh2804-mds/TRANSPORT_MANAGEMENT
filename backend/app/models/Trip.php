<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class Trip
{
    private $conn;
    private $table = 'trips';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Lấy tất cả danh sách chuyến (Dành cho Admin / Staff)
    public function getAll()
    {
        $sql = "SELECT t.*, 
                       d.full_name as driver_name, d.phone as driver_phone,
                       v.license_plate, v.vehicle_type
                FROM {$this->table} t
                LEFT JOIN drivers d ON t.driver_id = d.driver_id
                LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                ORDER BY t.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Lấy danh sách chuyến theo tài xế (Dành cho Driver)
    public function getByDriverId($driverId)
    {
        $sql = "SELECT t.*, 
                       d.full_name as driver_name, d.phone as driver_phone,
                       v.license_plate, v.vehicle_type
                FROM {$this->table} t
                LEFT JOIN drivers d ON t.driver_id = d.driver_id
                LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                WHERE t.driver_id = :driver_id
                ORDER BY t.id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':driver_id' => $driverId]);
        return $stmt->fetchAll();
    }

    // Tìm chuyến theo ID
    public function findById($id)
    {
        $sql = "SELECT t.*, 
                       d.full_name as driver_name, d.phone as driver_phone,
                       v.license_plate, v.vehicle_type
                FROM {$this->table} t
                LEFT JOIN drivers d ON t.driver_id = d.driver_id
                LEFT JOIN vehicles v ON t.vehicle_id = v.vehicle_id
                WHERE t.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    // Tạo chuyến mới (Staff / Admin)
    public function create($data)
    {
        $sql = "INSERT INTO {$this->table} 
                (driver_id, vehicle_id, start_location, end_location, start_time, status, goods_name, weight, goods_value, receiver_name, receiver_phone)
                VALUES 
                (:driver_id, :vehicle_id, :start_location, :end_location, :start_time, :status, :goods_name, :weight, :goods_value, :receiver_name, :receiver_phone)";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':driver_id' => $data['driver_id'] ?? null,
            ':vehicle_id' => $data['vehicle_id'] ?? null,
            ':start_location' => $data['start_location'],
            ':end_location' => $data['end_location'],
            ':start_time' => $data['start_time'] ?? null,
            ':status' => $data['status'] ?? 'PLANNED',
            ':goods_name' => $data['goods_name'] ?? null,
            ':weight' => $data['weight'] ?? null,
            ':goods_value' => $data['goods_value'] ?? null,
            ':receiver_name' => $data['receiver_name'] ?? null,
            ':receiver_phone' => $data['receiver_phone'] ?? null
        ]);
    }

    // Cập nhật thông tin chuyến
    public function update($id, $data)
    {
        $sql = "UPDATE {$this->table} SET 
                driver_id = :driver_id,
                vehicle_id = :vehicle_id,
                start_location = :start_location,
                end_location = :end_location,
                start_time = :start_time,
                status = :status,
                goods_name = :goods_name,
                weight = :weight,
                goods_value = :goods_value,
                receiver_name = :receiver_name,
                receiver_phone = :receiver_phone
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':driver_id' => $data['driver_id'] ?? null,
            ':vehicle_id' => $data['vehicle_id'] ?? null,
            ':start_location' => $data['start_location'],
            ':end_location' => $data['end_location'],
            ':start_time' => $data['start_time'] ?? null,
            ':status' => $data['status'],
            ':goods_name' => $data['goods_name'] ?? null,
            ':weight' => $data['weight'] ?? null,
            ':goods_value' => $data['goods_value'] ?? null,
            ':receiver_name' => $data['receiver_name'] ?? null,
            ':receiver_phone' => $data['receiver_phone'] ?? null,
            ':id' => $id
        ]);
    }

    // Cập nhật trạng thái chuyến (Dành cho tài xế hoặc nhân viên)
    public function updateStatus($id, $status)
    {
        $sql = "UPDATE {$this->table} SET status = :status WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':id' => $id
        ]);
    }

    // Xóa chuyến
    public function delete($id)
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}