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
    
    /*
    |--------------------------------------------------------------------------
    | Lấy tất cả đơn hàng cho Nhân viên / Admin / Tài xế
    |--------------------------------------------------------------------------
    */
    public function getAllOrdersForStaff()
    {
        if (!$this->conn) return [];

        try {
            // Liên kết 3 bảng: orders -> customers -> users để lấy tên khách hàng
            $sql = "SELECT o.*, u.full_name as customer_name
                    FROM {$this->table} o
                    LEFT JOIN customers c ON o.customer_id = c.id
                    LEFT JOIN users u ON c.user_id = u.id
                    ORDER BY o.id DESC";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            // Bắt buộc dùng FETCH_ASSOC để JSON trả về gọn gàng, không bị lỗi
            return $stmt->fetchAll(PDO::FETCH_ASSOC); 
        } catch (Exception $e) {
            // Nếu SQL lỗi (sai tên cột), hệ thống trả về mảng rỗng để không bị sập Frontend
            error_log("Lỗi SQL Order: " . $e->getMessage());
            return [];
        }
    }
    
    // Lấy tất cả đơn hàng của một khách hàng
    public function getByCustomerId($customerId)
    {
        if (!$this->conn) return [];
        try {
            $sql = "SELECT * FROM {$this->table}
                    WHERE customer_id = :customer_id
                    ORDER BY created_at DESC";
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
            $sql = "SELECT * FROM {$this->table}
                    WHERE customer_id = :customer_id
                      AND order_code = :order_code
                    LIMIT 1";
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
                ':payment_method'   => $data['payment_method'] ?? 'SENDER_PAY',
                ':payment_status'   => $data['payment_status'] ?? 'UNPAID',
                ':note'             => $data['note'] ?? null
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GÁN ĐƠN HÀNG VÀO MỘT CHUYẾN XE
    |--------------------------------------------------------------------------
    */
    public function assignToTrip($orderId, $tripId)
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET trip_id = :trip_id, 
                        status = 'CONFIRMED' 
                    WHERE id = :id";
                    
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([
                ':trip_id' => $tripId,
                ':id' => $orderId
            ]);
        } catch (Exception $e) {
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
            // Kiểm tra xem đơn này có đúng của khách hàng này và đang PENDING không
            $checkStmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE id = ? AND customer_id = ? AND status = 'PENDING'");
            $checkStmt->execute([$orderId, $customerId]);
            if (!$checkStmt->fetch()) {
                return false; // Không tìm thấy hoặc đơn đã xử lý không cho phép sửa
            }

            $sql = "UPDATE {$this->table} 
                    SET sender_name = :sender_name, sender_phone = :sender_phone, pickup_address = :pickup_address, 
                        receiver_name = :receiver_name, receiver_phone = :receiver_phone, delivery_address = :delivery_address, 
                        goods_type = :goods_type, total_weight = :total_weight, shipping_fee = :shipping_fee, 
                        payment_method = :payment_method, note = :note
                    WHERE id = :id AND customer_id = :customer_id AND status = 'PENDING'";
            
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
            // Cập nhật trạng thái thành CANCELLED
            $sql = "UPDATE {$this->table} 
                    SET status = 'CANCELLED' 
                    WHERE id = ? AND customer_id = ? AND status = 'PENDING'";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$orderId, $customerId]);
            
            // Trả về true nếu có ít nhất 1 dòng bị ảnh hưởng (cập nhật thành công)
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
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