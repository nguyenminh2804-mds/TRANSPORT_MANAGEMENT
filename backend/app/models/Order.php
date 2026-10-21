<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

require_once __DIR__.'/DeliveryProgress.php';

class Order
{
    private $conn;
    private $table = 'orders';

    public static function canTransitionStatus($currentStatus, $newStatus)
    {
        $transitions = [
            'PENDING' => ['CONFIRMED', 'CANCELLED'],
            'CONFIRMED' => ['PICKING_UP', 'CANCELLED'],
            'ASSIGNED' => ['PICKING_UP', 'CANCELLED'],
            'PICKING_UP' => ['IN_TRANSIT', 'CANCELLED'],
            'IN_TRANSIT' => ['DELIVERED', 'CANCELLED'],
            'DELIVERING' => ['DELIVERED', 'CANCELLED'],
            'DELIVERED' => [],
            'CANCELLED' => [],
            'CANCELED' => []
        ];

        return in_array($newStatus, $transitions[$currentStatus] ?? [], true);
    }

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }
    
    /*
    |--------------------------------------------------------------------------
    | Lấy tất cả đơn hàng cho Nhân viên / Admin / Tài xế
    |--------------------------------------------------------------------------
    */
    public function getAllOrdersForStaff()
    {
        if (!$this->conn) return [];

        try {
            $sql = DeliveryProgress::orderSelect().' ORDER BY o.id DESC';

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC); 
        } catch (Exception $e) {
            error_log("Lỗi SQL Order: " . $e->getMessage());
            return [];
        }
    }
    
    // Lấy tất cả đơn hàng của một khách hàng
    public function getByCustomerId($customerId)
    {
        if (!$this->conn) return [];
        try {
            $sql = DeliveryProgress::orderSelect().' WHERE o.customer_id=:customer_id ORDER BY o.created_at DESC';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':customer_id' => $customerId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // Tra cứu một đơn hàng của khách hàng
    public function findByCustomerAndCode($customerId, $orderCode) 
    {
        if (!$this->conn) return false;
        try {
            $sql = DeliveryProgress::orderSelect().' WHERE o.customer_id=:customer_id AND o.order_code=:order_code LIMIT 1';
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':customer_id' => $customerId,
                ':order_code' => $orderCode
            ]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | TẠO MỚI ĐƠN HÀNG VÀO DATABASE
    |--------------------------------------------------------------------------
    */
    public function create($data)
    {
        try {
            $paymentMethod = $data['payment_method'] ?? 'SENDER_PAY';
            if (!in_array($paymentMethod, ['SENDER_PAY', 'RECEIVER_PAY'], true)) return false;
            $sql = "INSERT INTO {$this->table} 
                    (order_code, customer_id, sender_name, sender_phone, pickup_address, receiver_name, receiver_phone, delivery_address, goods_type, total_weight, shipping_fee, payment_method, payment_status, status, note) 
                    VALUES 
                    (:order_code, :customer_id, :sender_name, :sender_phone, :pickup_address, :receiver_name, :receiver_phone, :delivery_address, :goods_type, :total_weight, :shipping_fee, :payment_method, :payment_status, 'PENDING', :note)";
            
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute([
                ':order_code'       => $data['order_code'],
                ':customer_id'      => $data['customer_id'],
                ':sender_name'      => $data['sender_name'] ?? null,
                ':sender_phone'     => $data['sender_phone'] ?? null,
                ':pickup_address'   => $data['pickup_address'],
                ':receiver_name'    => $data['receiver_name'] ?? null,
                ':receiver_phone'   => $data['receiver_phone'] ?? null,
                ':delivery_address' => $data['delivery_address'],
                ':goods_type'       => $data['goods_type'] ?? null,
                ':total_weight'     => $data['total_weight'],
                ':shipping_fee'     => $data['shipping_fee'],
                ':payment_method'   => $paymentMethod,
                ':payment_status'   => $paymentMethod === 'SENDER_PAY' ? 'PAID' : 'UNPAID',
                ':note'             => $data['note'] ?? null
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GÁN ĐƠN HÀNG VÀO MỘT CHUYẾN XE (ĐÃ FIX LỖI DATABASE)
    |--------------------------------------------------------------------------
    */
    public function assignToTrip($orderId, $tripId, $expectedTripId = null)
    {
        try {
            $this->conn->beginTransaction();
            $readOrder = $this->conn->prepare('SELECT * FROM orders WHERE id=?');
            $readOrder->execute([$orderId]);
            $snapshot = $readOrder->fetch(PDO::FETCH_ASSOC);
            if (!$snapshot) throw new DomainException('Không tìm thấy đơn hàng.', 404);
            $readLinks = $this->conn->prepare('SELECT trip_id FROM trip_orders WHERE order_id=? ORDER BY trip_id');
            $readLinks->execute([$orderId]);
            $links = array_map('intval', $readLinks->fetchAll(PDO::FETCH_COLUMN));
            $sources = array_values(array_unique(array_filter(array_merge([(int)$snapshot['trip_id']], $links))));
            if (count($sources) > 1) throw new DomainException('Đơn hàng đang liên kết nhiều chuyến không nhất quán; chưa thể đổi chuyến an toàn.', 409);
            $sourceId = $sources[0] ?? 0;
            // Use the same trip-first locking order as Driver transitions.
            $tripIds = array_values(array_unique(array_merge($sources, [(int)$tripId])));
            sort($tripIds, SORT_NUMERIC);
            $lockTrips = $this->conn->prepare('SELECT id,status FROM trips WHERE id IN ('.implode(',', array_fill(0, count($tripIds), '?')).') ORDER BY id FOR UPDATE');
            $lockTrips->execute($tripIds);
            $trips = $lockTrips->fetchAll(PDO::FETCH_KEY_PAIR);
            if (!isset($trips[$tripId])) throw new DomainException('Không tìm thấy chuyến được chọn.', 404);
            if ($trips[$tripId] !== 'PLANNED' || ($sourceId && ($trips[$sourceId] ?? null) !== 'PLANNED')) {
                throw new DomainException('Chỉ được gán hoặc đổi chuyến khi cả chuyến cũ và chuyến mới chưa xuất phát.', 409);
            }
            $lockOrder = $this->conn->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
            $lockOrder->execute([$orderId]);
            $order = $lockOrder->fetch(PDO::FETCH_ASSOC);
            $lockLinks = $this->conn->prepare('SELECT trip_id FROM trip_orders WHERE order_id=? ORDER BY trip_id FOR UPDATE');
            $lockLinks->execute([$orderId]);
            $currentLinks = array_map('intval', $lockLinks->fetchAll(PDO::FETCH_COLUMN));
            if (!$order || (int)$order['trip_id'] !== (int)$snapshot['trip_id'] || $currentLinks !== $links || ($expectedTripId !== null && (int)$expectedTripId !== $sourceId)) {
                throw new DomainException('Liên kết chuyến vừa thay đổi. Hãy tải lại danh sách trước khi lưu.', 409);
            }
            if (!in_array($order['status'], ['PENDING', 'CONFIRMED', 'PICKING_UP', ''], true)) {
                throw new DomainException('Không thể đổi chuyến cho đơn đang vận chuyển, đã giao hoặc đã hủy.', 409);
            }
            // Keep the existing bridge consistent without removing orders or trip history.
            if ($links && $sourceId !== (int)$tripId) {
                $updateLinks = $this->conn->prepare('UPDATE trip_orders SET trip_id=? WHERE order_id=?');
                $updateLinks->execute([$tripId, $orderId]);
            }
            if ($sourceId !== (int)$tripId || (int)$order['trip_id'] !== (int)$tripId) {
                $stmt = $this->conn->prepare("UPDATE orders SET trip_id=?,status='CONFIRMED' WHERE id=?");
                $stmt->execute([$tripId, $orderId]);
            }
            $this->conn->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->conn && $this->conn->inTransaction()) $this->conn->rollBack();
            if ($e instanceof DomainException) throw $e;
            error_log("Lỗi gán chuyến: " . $e->getMessage());
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG 
    |--------------------------------------------------------------------------
    */
    public function updateStatus($orderId, $status)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $orderId]);
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SỬA ĐƠN HÀNG CỦA KHÁCH (Chỉ sửa khi đơn PENDING)
    |--------------------------------------------------------------------------
    */
    public function updateCustomerOrder($orderId, $customerId, $data)
    {
        try {
            $checkStmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE id = ? AND customer_id = ? AND status = 'PENDING' AND (trip_id IS NULL OR trip_id=0) AND NOT EXISTS(SELECT 1 FROM trip_orders linked WHERE linked.order_id=orders.id)");
            $checkStmt->execute([$orderId, $customerId]);
            if (!$checkStmt->fetch()) {
                return false;
            }

            $sql = "UPDATE {$this->table} 
                    SET sender_name = :sender_name, sender_phone = :sender_phone, pickup_address = :pickup_address, 
                        receiver_name = :receiver_name, receiver_phone = :receiver_phone, delivery_address = :delivery_address, 
                        goods_type = :goods_type, total_weight = :total_weight, shipping_fee = :shipping_fee, 
                        payment_method = :payment_method, note = :note
                    WHERE id = :id AND customer_id = :customer_id AND status = 'PENDING' AND (trip_id IS NULL OR trip_id=0) AND NOT EXISTS(SELECT 1 FROM trip_orders linked WHERE linked.order_id=orders.id)";
            
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute([
                ':id'               => $orderId,
                ':customer_id'      => $customerId,
                ':sender_name'      => $data['sender_name'],
                ':sender_phone'     => $data['sender_phone'],
                ':pickup_address'   => $data['pickup_address'],
                ':receiver_name'    => $data['receiver_name'],
                ':receiver_phone'   => $data['receiver_phone'],
                ':delivery_address' => $data['delivery_address'],
                ':goods_type'       => $data['goods_type'],
                ':total_weight'     => $data['total_weight'],
                ':shipping_fee'     => $data['shipping_fee'],
                ':payment_method'   => $data['payment_method'],
                ':note'             => $data['note']
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HỦY ĐƠN HÀNG CỦA KHÁCH (Chỉ hủy khi PENDING)
    |--------------------------------------------------------------------------
    */
    public function cancelCustomerOrder($orderId, $customerId)
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET status = 'CANCELLED' 
                    WHERE id = ? AND customer_id = ? AND status = 'PENDING' AND (trip_id IS NULL OR trip_id=0) AND NOT EXISTS(SELECT 1 FROM trip_orders linked WHERE linked.order_id=orders.id)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId, $customerId]);
            
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CẬP NHẬT TRẠNG THÁI THANH TOÁN
    |--------------------------------------------------------------------------
    */
    public function updatePaymentStatus($orderId, $status)
    {
        try {
            $sql = "UPDATE {$this->table} SET payment_status = ? WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$status, $orderId]);
        } catch (Exception $e) {
            return false;
        }
    }
}
