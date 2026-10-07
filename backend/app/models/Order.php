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

    // Tạo đơn hàng và các sản phẩm trong đơn
    public function createOrder($customerId, $data)
    {
        try {
            $this->conn->beginTransaction();

            // Tạo mã đơn hàng
            $orderCode = 'DH' . date('YmdHis') . rand(100, 999);

            // Thêm đơn hàng
            $sql = "INSERT INTO {$this->table}
                        (
                            order_code,
                            customer_id,
                            pickup_address,
                            delivery_address,
                            total_weight,
                            shipping_fee,
                            payment_status,
                            status
                        )
                    VALUES
                        (
                            :order_code,
                            :customer_id,
                            :pickup_address,
                            :delivery_address,
                            :total_weight,
                            :shipping_fee,
                            :payment_status,
                            :status
                        )";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                ':order_code' => $orderCode,
                ':customer_id' => $customerId,
                ':pickup_address' => $data['pickup_address'],
                ':delivery_address' => $data['delivery_address'],
                ':total_weight' => $data['total_weight'] ?? 0,
                ':shipping_fee' => $data['shipping_fee'] ?? 0,
                ':payment_status' => 'UNPAID',
                ':status' => 'PENDING'
            ]);

            $orderId = $this->conn->lastInsertId();

            // Thêm các sản phẩm trong đơn
            $itemSql = "INSERT INTO order_items
                            (
                                order_id,
                                product_name,
                                quantity,
                                weight
                            )
                        VALUES
                            (
                                :order_id,
                                :product_name,
                                :quantity,
                                :weight
                            )";

            $itemStmt = $this->conn->prepare($itemSql);

            foreach ($data['items'] as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_name' => $item['product_name'],
                    ':quantity' => $item['quantity'],
                    ':weight' => $item['weight'] ?? 0
                ]);
            }

            // Ghi trạng thái ban đầu vào lịch sử
            $historySql = "INSERT INTO order_status_history
                                (
                                    order_id,
                                    old_status,
                                    new_status,
                                    note
                                )
                           VALUES
                                (
                                    :order_id,
                                    NULL,
                                    :new_status,
                                    :note
                                )";

            $historyStmt = $this->conn->prepare($historySql);

            $historyStmt->execute([
                ':order_id' => $orderId,
                ':new_status' => 'PENDING',
                ':note' => 'Tạo đơn hàng'
            ]);

            $this->conn->commit();

            return [
                'id' => $orderId,
                'order_code' => $orderCode,
                'status' => 'PENDING'
            ];

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }

    // Lấy thông tin một đơn hàng theo ID
    public function findById($orderId)
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
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':id' => $orderId
        ]);

        return $stmt->fetch();
    }

    // Cập nhật trạng thái đơn hàng
    public function updateStatus($orderId, $newStatus)
    {
        try {
            $this->conn->beginTransaction();

            // Lấy trạng thái hiện tại
            $sql = "SELECT status
                    FROM {$this->table}
                    WHERE id = :id
                    LIMIT 1";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                ':id' => $orderId
            ]);

            $order = $stmt->fetch();

            if (!$order) {
                throw new Exception('Không tìm thấy đơn hàng');
            }

            $oldStatus = $order['status'];

            // Nếu trạng thái không thay đổi
            if ($oldStatus === $newStatus) {
                throw new Exception(
                    'Trạng thái mới giống trạng thái hiện tại'
                );
            }

            // Cập nhật trạng thái đơn hàng
            $updateSql = "UPDATE {$this->table}
                          SET status = :new_status
                          WHERE id = :id";

            $updateStmt = $this->conn->prepare($updateSql);

            $updateStmt->execute([
                ':new_status' => $newStatus,
                ':id' => $orderId
            ]);

            // Ghi lịch sử thay đổi trạng thái
            $historySql = "INSERT INTO order_status_history
                                (
                                    order_id,
                                    old_status,
                                    new_status,
                                    note
                                )
                           VALUES
                                (
                                    :order_id,
                                    :old_status,
                                    :new_status,
                                    :note
                                )";

            $historyStmt = $this->conn->prepare($historySql);

            $historyStmt->execute([
                ':order_id' => $orderId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':note' => 'Cập nhật trạng thái đơn hàng'
            ]);

            $this->conn->commit();

            return [
                'id' => $orderId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus
            ];

        } catch (Exception $e) {

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}