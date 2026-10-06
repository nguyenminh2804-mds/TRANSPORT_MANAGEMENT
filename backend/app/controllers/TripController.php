<?php
require_once '../app/models/Trip.php';

class TripController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    // Hiển thị danh sách
    public function index() {
        $trip = new Trip($this->db);
        $result = $trip->getAllTrips();
        $trips = $result->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(["status" => "success", "data" => $trips]);
    }

    // Xử lý tạo chuyến
    public function create() {
        $data = json_decode(file_get_contents("php://input"));
        if (!empty($data->driver_id) && !empty($data->vehicle_id) && !empty($data->start_location)) {
            $trip = new Trip($this->db);
            if ($trip->createTrip($data->driver_id, $data->vehicle_id, $data->start_location, $data->end_location)) {
                echo json_encode(["status" => "success", "message" => "Tạo chuyến thành công!"]);
            } else {
                echo json_encode(["status" => "error", "message" => "Không thể tạo chuyến."]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "Vui lòng nhập đủ thông tin."]);
        }
    }

    // Xử lý theo dõi
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