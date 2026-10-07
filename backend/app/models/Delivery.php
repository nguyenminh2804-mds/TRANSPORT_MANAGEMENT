<?php

class Delivery
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }


    // ============================================================
    // UC-DH-03 - Cập nhật trạng thái giao hàng
    // ============================================================
    public function updateDeliveryStatus(
        $orderId,
        $updatedBy,
        $newStatus
    ) {

        try {

            // ====================================================
            // BƯỚC 1 - Bắt đầu transaction
            // ====================================================

            $this->conn->beginTransaction();


            // ====================================================
            // BƯỚC 2 - Kiểm tra đơn hàng
            // ====================================================

            $orderSql = "SELECT
                            id,
                            order_code,
                            status
                         FROM orders
                         WHERE id = :id
                         LIMIT 1
                         FOR UPDATE";

            $orderStmt = $this->conn->prepare($orderSql);

            $orderStmt->execute([
                ':id' => $orderId
            ]);

            $order = $orderStmt->fetch();


            if (!$order) {

                throw new Exception(
                    'Không tìm thấy đơn hàng'
                );
            }


            // ====================================================
            // BƯỚC 3 - Lấy trạng thái hiện tại
            // ====================================================

            $oldStatus = $order['status'];


            // ====================================================
            // BƯỚC 4 - Kiểm tra trạng thái mới
            // ====================================================

            $validStatuses = [
                'PICKING_UP',
                'IN_TRANSIT',
                'DELIVERED',
                'CANCELLED'
            ];


            if (!in_array($newStatus, $validStatuses, true)) {

                throw new Exception(
                    'Trạng thái giao hàng không hợp lệ'
                );
            }


            // ====================================================
            // BƯỚC 5 - Kiểm tra vòng đời đơn hàng
            // ====================================================

            $allowedTransitions = [

                'PENDING' => [
                    'PICKING_UP',
                    'CANCELLED'
                ],

                'CONFIRMED' => [
                    'PICKING_UP',
                    'CANCELLED'
                ],

                'PICKING_UP' => [
                    'IN_TRANSIT',
                    'CANCELLED'
                ],

                'IN_TRANSIT' => [
                    'DELIVERED',
                    'CANCELLED'
                ],

                'DELIVERED' => [],

                'CANCELLED' => []
            ];


            if (
                !isset($allowedTransitions[$oldStatus]) ||
                !in_array(
                    $newStatus,
                    $allowedTransitions[$oldStatus],
                    true
                )
            ) {

                throw new Exception(
                    "Không thể chuyển trạng thái từ "
                    . "{$oldStatus} sang {$newStatus}"
                );
            }


            // ====================================================
            // BƯỚC 6 - Cập nhật trạng thái
            // ====================================================

            $updateSql = "UPDATE orders
                          SET status = :new_status
                          WHERE id = :id";

            $updateStmt = $this->conn->prepare(
                $updateSql
            );

            $updateStmt->execute([
                ':new_status' => $newStatus,
                ':id' => $orderId
            ]);


            // ====================================================
            // BƯỚC 7 - Ghi lịch sử trạng thái
            // ====================================================

            $historySql = "INSERT INTO order_status_history
                           (
                               order_id,
                               old_status,
                               new_status,
                               updated_by,
                               note
                           )
                           VALUES
                           (
                               :order_id,
                               :old_status,
                               :new_status,
                               :updated_by,
                               :note
                           )";

            $historyStmt = $this->conn->prepare(
                $historySql
            );

            $historyStmt->execute([
                ':order_id' => $orderId,
                ':old_status' => $oldStatus,
                ':new_status' => $newStatus,
                ':updated_by' => $updatedBy,
                ':note' => 'Nhân viên giao nhận cập nhật trạng thái giao hàng'
            ]);


            // ====================================================
            // BƯỚC 8 - Commit
            // ====================================================

            $this->conn->commit();


            // ====================================================
            // BƯỚC 9 - Trả kết quả
            // ====================================================

            return [
                'id' => $order['id'],
                'order_code' => $order['order_code'],
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $updatedBy
            ];


        } catch (Exception $e) {

            // Có lỗi -> rollback

            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            throw $e;
        }
    }
}