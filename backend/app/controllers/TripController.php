<?php
require_once '../app/models/Trip.php';

class TripController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function index() {
        $trip = new Trip($this->db);
        $result = $trip->getAllTrips();
        $trips = $result->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $trips]);
    }

    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->driver_name) && !empty($data->start_location) && !empty($data->goods_name)) {
            $trip = new Trip($this->db);
            
            // Dùng Try-Catch để bắt lỗi CSDL nếu có
            try {
                if ($trip->createTrip($data)) {
                    echo json_encode(["status" => "success", "message" => "Tạo chuyến vận chuyển thành công!"]);
                } else {
                    echo json_encode(["status" => "error", "message" => "Lỗi DB: Không thể tạo chuyến."]);
                }
            } catch (PDOException $e) {
                // Trả về thẳng thông báo lỗi của CSDL để dễ sửa
                echo json_encode(["status" => "error", "message" => "Lỗi MySQL: " . $e->getMessage()]);
            }

        } else {
            echo json_encode(["status" => "error", "message" => "Vui lòng nhập đầy đủ thông tin."]);
        }
    }

    public function track() {
        $id = isset($_GET['id']) ? $_GET['id'] : die();
        $trip = new Trip($this->db);
        $data = $trip->trackTrip($id);
        if ($data) {
            echo json_encode(["status" => "success", "data" => $data]);
        } else {
            echo json_encode(["status" => "error", "message" => "Không tìm thấy chuyến xe."]);
        }
    }
}
?>