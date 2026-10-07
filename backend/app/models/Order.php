<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class Order
{
    private $conn;
    private $table = 'orders';

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Lấy tất cả đơn hàng của một khách hàng
    public function getByCustomerId($customerId)
    {
        $sql = "SELECT
                    id,
                    order_code,
                    customer_id,
                    pickup_address,
                    delivery_address,
                    total_weight,
                    shipping_fee,
                    payment_status,
                    status,
                    created_at
                FROM {$this->table}
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':customer_id' => $customerId
        ]);

        return $stmt->fetchAll();
    }

    // Tra cứu một đơn hàng của khách hàng
    public function findByCustomerAndCode(
        $customerId,
        $orderCode
    ) {
        $sql = "SELECT
                    id,
                    order_code,
                    customer_id,
                    pickup_address,
                    delivery_address,
                    total_weight,
                    shipping_fee,
                    payment_status,
                    status,
                    created_at
                FROM {$this->table}
                WHERE customer_id = :customer_id
                  AND order_code = :order_code
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':customer_id' => $customerId,
            ':order_code' => $orderCode
        ]);

        return $stmt->fetch();
    }
}