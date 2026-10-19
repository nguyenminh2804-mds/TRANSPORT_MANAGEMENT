
<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class SupportTicket
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Tạo phiếu hỗ trợ
    public function create($data)
    {
        $sql = "INSERT INTO support_tickets
                (
                    ticket_code,
                    customer_id,
                    order_id,
                    category,
                    subject,
                    description
                )
                VALUES
                (
                    :ticket_code,
                    :customer_id,
                    :order_id,
                    :category,
                    :subject,
                    :description
                )";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ticket_code' => $data['ticket_code'],
            ':customer_id' => $data['customer_id'],
            ':order_id' => $data['order_id'] ?: null,
            ':category' => $data['category'],
            ':subject' => $data['subject'],
            ':description' => $data['description']
        ]);

        return $this->conn->lastInsertId();
    }

    // Danh sách phiếu của một khách hàng
    public function getByCustomerId($customerId)
    {
        $sql = "SELECT
                    id,
                    ticket_code,
                    order_id,
                    category,
                    subject,
                    description,
                    status,
                    assigned_to,
                    created_at,
                    updated_at
                FROM support_tickets
                WHERE customer_id = :customer_id
                ORDER BY created_at DESC, id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':customer_id' => $customerId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Chi tiết phiếu
    public function findById($ticketId)
    {
        $sql = "SELECT
                    t.*,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    u.full_name AS assigned_name
                FROM support_tickets t
                INNER JOIN customers c
                    ON c.id = t.customer_id
                LEFT JOIN users u
                    ON u.id = t.assigned_to
                WHERE t.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $ticketId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Kiểm tra quyền sở hữu phiếu
    public function belongsToCustomer($ticketId, $customerId)
    {
        $sql = "SELECT id
                FROM support_tickets
                WHERE id = :id
                  AND customer_id = :customer_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $ticketId,
            ':customer_id' => $customerId
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lưu tin nhắn trao đổi
    public function addMessage($ticketId, $senderUserId, $message)
    {
        $sql = "INSERT INTO support_messages
                (ticket_id, sender_user_id, message)
                VALUES
                (:ticket_id, :sender_user_id, :message)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':sender_user_id' => $senderUserId,
            ':message' => $message
        ]);

        return $this->conn->lastInsertId();
    }

    // Lấy lịch sử tin nhắn
    public function getMessages($ticketId)
    {
        $sql = "SELECT
                    m.id,
                    m.ticket_id,
                    m.sender_user_id,
                    m.message,
                    m.created_at,
                    u.full_name AS sender_name,
                    u.role AS sender_role
                FROM support_messages m
                INNER JOIN users u
                    ON u.id = m.sender_user_id
                WHERE m.ticket_id = :ticket_id
                ORDER BY m.created_at ASC, m.id ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ticket_id' => $ticketId
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Danh sách phiếu dành cho nhân viên
    public function getAll($status = null)
    {
        $sql = "SELECT
                    t.id,
                    t.ticket_code,
                    t.customer_id,
                    t.order_id,
                    t.category,
                    t.subject,
                    t.description,
                    t.status,
                    t.assigned_to,
                    t.created_at,
                    t.updated_at,
                    c.name AS customer_name,
                    c.phone AS customer_phone,
                    u.full_name AS assigned_name
                FROM support_tickets t
                INNER JOIN customers c
                    ON c.id = t.customer_id
                LEFT JOIN users u
                    ON u.id = t.assigned_to";

        if ($status !== null && $status !== '') {
            $sql .= " WHERE t.status = :status";
        }

        $sql .= " ORDER BY
                    CASE t.status
                        WHEN 'PENDING' THEN 1
                        WHEN 'PROCESSING' THEN 2
                        WHEN 'RESOLVED' THEN 3
                        WHEN 'CLOSED' THEN 4
                        ELSE 5
                    END,
                    t.created_at DESC";

        $stmt = $this->conn->prepare($sql);

        if ($status !== null && $status !== '') {
            $stmt->bindValue(':status', $status);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Cập nhật trạng thái và nhân viên phụ trách
    public function updateStatus($ticketId, $status, $staffId)
    {
        $sql = "UPDATE support_tickets
                SET status = :status,
                    assigned_to = CASE
                        WHEN :assign_staff = 1 THEN :staff_id
                        ELSE assigned_to
                    END
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':status' => $status,
            ':assign_staff' => $status === 'PROCESSING' ? 1 : 0,
            ':staff_id' => $staffId,
            ':id' => $ticketId
        ]);

        return $stmt->rowCount();
    }
    
    // Phân công phiếu hỗ trợ cho nhân viên
    public function assignStaff($ticketId, $staffId)
    {
        $sql = "UPDATE support_tickets
                SET assigned_to = :staff_id
                WHERE id = :ticket_id";

        $stmt = $this->conn->prepare($sql);

        $stmt->execute([
            ':staff_id' => $staffId,
            ':ticket_id' => $ticketId
        ]);

        return $stmt->rowCount();
    }

}
