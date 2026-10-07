<?php
class Trip {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getAllTrips() {
        // Lấy dữ liệu trực tiếp từ bảng trips
        $query = "SELECT * FROM trips ORDER BY id DESC";
        return $this->conn->query($query);
    }

    public function createTrip($data) {
        // Thay NULL bằng 1 để vượt qua ràng buộc NOT NULL của cơ sở dữ liệu
        $query = "INSERT INTO trips (driver_id, vehicle_id, driver_name, driver_phone, vehicle_plate, start_location, end_location, goods_name, goods_value, weight, receiver_name, receiver_phone, status) 
                  VALUES (1, 1, :dname, :dphone, :vplate, :start, :end, :gname, :gvalue, :weight, :rname, :rphone, 'PLANNED')";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':dname' => $data->driver_name,
            ':dphone' => $data->driver_phone,
            ':vplate' => $data->vehicle_plate,
            ':start' => $data->start_location,
            ':end' => $data->end_location,
            ':gname' => $data->goods_name,
            ':gvalue' => $data->goods_value,
            ':weight' => $data->weight,
            ':rname' => $data->receiver_name,
            ':rphone' => $data->receiver_phone
        ]);
    }
    public function trackTrip($trip_id) {
        $query = "SELECT * FROM trips WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $trip_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>