<?php
class Trip {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Lấy danh sách chuyến
    public function getAllTrips() {
        $query = "SELECT t.id, t.start_location, t.end_location, t.status, 
                         d.name as driver_name, v.license_plate 
                  FROM trips t 
                  LEFT JOIN drivers d ON t.driver_id = d.id 
                  LEFT JOIN vehicles v ON t.vehicle_id = v.id 
                  ORDER BY t.id DESC";
        return $this->conn->query($query);
    }

    // Tạo chuyến mới
    public function createTrip($driver_id, $vehicle_id, $start, $end) {
        $query = "INSERT INTO trips (driver_id, vehicle_id, start_location, end_location, status) 
                  VALUES (:driver, :vehicle, :start, :end, 'PLANNED')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            ':driver' => $driver_id,
            ':vehicle' => $vehicle_id,
            ':start' => $start,
            ':end' => $end
        ]);
    }

    // Theo dõi chi tiết 1 chuyến
    public function trackTrip($trip_id) {
        $query = "SELECT t.*, d.name as driver_name, d.phone, v.license_plate 
                  FROM trips t 
                  LEFT JOIN drivers d ON t.driver_id = d.id 
                  LEFT JOIN vehicles v ON t.vehicle_id = v.id 
                  WHERE t.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([':id' => $trip_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>