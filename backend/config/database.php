<?php
class Database {
    private $host = "127.0.0.1";
    private $db_name = "transport_db"; // Tên database chính xác của bạn
    private $username = "root";        // Tài khoản mặc định của XAMPP
    private $password = "";            // Mật khẩu mặc định của XAMPP (để trống)
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", $this->username, $this->password);
            // Thiết lập chế độ báo lỗi chi tiết
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo json_encode(["status" => "error", "message" => "Lỗi kết nối CSDL: " . $exception->getMessage()]);
            exit;
        }
        return $this->conn;
    }
}
?>