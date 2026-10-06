<?php
// Cho phép Frontend gọi API mà không bị chặn (CORS)
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

// Khởi tạo kết nối DB trực tiếp (để test nhanh)
$host = 'localhost';
$db_name = 'transport_db';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Lỗi CSDL: " . $e->getMessage()]);
    exit();
}

// Bắt đường dẫn từ URL (ví dụ: ?url=trips)
$url = isset($_GET['url']) ? $_GET['url'] : '';

switch ($url) {
    case 'trips':
        require_once '../app/controllers/TripController.php';
        $controller = new TripController($db);
        $action = isset($_GET['action']) ? $_GET['action'] : 'index';
        
        if ($action === 'create') {
            $controller->create();
        } else if ($action === 'track') {
            $controller->track();
        } else {
            $controller->index();
        }
        break;
        
    case 'statistics':
        require_once '../app/controllers/StatisticsController.php';
        $controller = new StatisticsController($db);
        $controller->getDashboardData();
        break;

    default:
        echo json_encode(["status" => "error", "message" => "Không tìm thấy API (404)"]);
        break;
}
?>