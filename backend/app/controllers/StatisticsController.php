<?php
require_once '../app/models/Statistics.php';

class StatisticsController {
    private $db;

    public function __construct($dbConnection) {
        $this->db = $dbConnection;
    }

    public function getDashboardData() {
        $stats = new Statistics($this->db);
        $data = $stats->getOverview();

        header('Content-Type: application/json');
        echo json_encode(["status" => "success", "data" => $data]);
    }
}
?>