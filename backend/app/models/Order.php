<?php

class Order
{
    private $conn;
    private $table = 'orders';

    public function __construct($db)
    {
        $this->conn = $db;
    }


    // ============================================================
    // UC-DH-01 - Lấy danh sách đơn hàng của khách hàng
    // ============================================================
    public function getByCustomerId($customerId)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':customer_id' => $customerId
        ]);

        return $stmt->fetchAll();
    }


    // ============================================================
    // UC-DH-01 - Tìm đơn hàng theo mã đơn của khách hàng
    // ============================================================
    public function findByCustomerAndCode($customerId, $orderCode)
    {
        $sql = "SELECT *
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


    // ============================================================
    // UC-DH-01 - Tạo đơn hàng
    // ============================================================
    public function createOrder($customerId, $data)
    {
        try {

            // Bắt đầu transaction
            $this->conn->beginTransaction();

            /*
             * Tạo mã đơn hàng
             */
            $orderCode = 'ORD' . date('YmdHis') . rand(100, 999);

            /*
             * Trạng thái ban đầu của đơn hàng
             */
            $initialStatus = 'PENDING';

            /*
             * Thêm đơn hàng vào bảng orders
             */
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
                ':total_weight' => $data['total_weight'],
                ':shipping_fee' => $data['shipping_fee'],
                ':payment_status' => $data['payment_status'] ?? 'UNPAID',
                ':status' => $initialStatus
            ]);


            /*
             * Lấy ID đơn hàng vừa tạo
             */
            $orderId = $this->conn->lastInsertId();


            // ====================================================
            // Thêm danh sách sản phẩm trong đơn
            // ====================================================

            if (!empty($data['items'])) {

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
                        ':weight' => $item['weight']
                    ]);
                }
            }


            // ====================================================
            // Ghi lịch sử trạng thái ban đầu
            // ====================================================

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
                ':old_status' => null,
                ':new_status' => $initialStatus,
                ':note' => 'Tạo đơn hàng'
            ]);


            // Hoàn tất transaction
            $this->conn->commit();


            /*
             * Trả kết quả cho Controller
             */
            return [
                'id' => $orderId,
                'order_code' => $orderCode,
                'status' => $initialStatus
            ];


        } catch (Exception $e) {

            /*
             * Nếu xảy ra lỗi thì rollback
             */
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }


    // ============================================================
    // UC-DH-02 - Tìm đơn hàng theo ID
    // ============================================================
    public function findById($orderId)
    {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':id' => $orderId
        ]);

        return $stmt->fetch();
    }


    // ============================================================
    // UC-DH-02 - Cập nhật trạng thái đơn hàng
    // ============================================================
    public function updateStatus($orderId, $newStatus)
    {
        try {

            $this->conn->beginTransaction();

            /*
             * Lấy trạng thái hiện tại
             */
            $sql = "SELECT id, order_code, status
                    FROM {$this->table}
                    WHERE id = :id
                    LIMIT 1
                    FOR UPDATE";

            $stmt = $this->conn->prepare($sql);

            $stmt->execute([
                ':id' => $orderId
            ]);

            $order = $stmt->fetch();


            if (!$order) {
                throw new Exception(
                    'Không tìm thấy đơn hàng'
                );
            }


            /*
             * Không cho cập nhật cùng trạng thái
             */
            if ($order['status'] === $newStatus) {
                throw new Exception(
                    'Trạng thái mới giống trạng thái hiện tại'
                );
            }


            /*
             * Cập nhật trạng thái
             */
            $updateSql = "UPDATE {$this->table}
                          SET status = :new_status
                          WHERE id = :id";

            $updateStmt = $this->conn->prepare($updateSql);

            $updateStmt->execute([
                ':new_status' => $newStatus,
                ':id' => $orderId
            ]);


            /*
             * Ghi lịch sử trạng thái
             */
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
                ':old_status' => $order['status'],
                ':new_status' => $newStatus,
                ':note' => 'Cập nhật trạng thái đơn hàng'
            ]);


            // Hoàn tất transaction
            $this->conn->commit();


            return [
                'id' => $order['id'],
                'order_code' => $order['order_code'],
                'old_status' => $order['status'],
                'new_status' => $newStatus
            ];


        } catch (Exception $e) {

            /*
             * Có lỗi -> giữ nguyên trạng thái cũ
             */
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}