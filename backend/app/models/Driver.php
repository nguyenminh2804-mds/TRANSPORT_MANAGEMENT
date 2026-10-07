<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';


class Driver
{
    private $conn;
    private $table = 'drivers';


    /*
    |--------------------------------------------------------------------------
    | Khởi tạo kết nối database
    |--------------------------------------------------------------------------
    */

    public function __construct()
    {
        $database = new Database();

        $this->conn = $database->connect();
    }


    /*
    |--------------------------------------------------------------------------
    | UC-DH-03
    | Tìm thông tin tài xế theo user_id
    |--------------------------------------------------------------------------
    */

    public function findByUserId($userId)
    {
        $sql = "SELECT
                    driver_id AS id,
                    user_id,
                    full_name AS name,
                    phone,
                    license_number,
                    status
                FROM {$this->table}
                WHERE user_id = :user_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':user_id' => $userId
        ]);

        return $stmt->fetch();
    }


    /*
    |--------------------------------------------------------------------------
    | UC-DH-03
    | Kiểm tra đơn hàng có được phân công cho tài xế hay không
    |--------------------------------------------------------------------------
    |
    | Quan hệ:
    |
    | drivers
    |     ↓
    | trips
    |     ↓
    | trip_orders
    |     ↓
    | orders
    |
    */

    public function isOrderAssigned($driverId, $orderId)
    {
        $sql = "SELECT
                    d.driver_id AS driver_id,
                    o.id AS order_id
                FROM drivers d

                INNER JOIN trips t
                    ON t.driver_id = d.driver_id

                INNER JOIN trip_orders to2
                    ON to2.trip_id = t.id

                INNER JOIN orders o
                    ON o.id = to2.order_id

                WHERE d.driver_id = :driver_id
                  AND o.id = :order_id

                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':driver_id' => $driverId,
            ':order_id' => $orderId
        ]);

        return $stmt->fetch();
    }
}