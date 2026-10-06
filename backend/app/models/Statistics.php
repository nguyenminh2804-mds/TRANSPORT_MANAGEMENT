<?php
class Statistics {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getOverview() {
        // Tổng số đơn hàng
        $stmtOrder = $this->conn->query("SELECT COUNT(*) as total_orders FROM orders");
        $totalOrders = $stmtOrder->fetch(PDO::FETCH_ASSOC)['total_orders'];

        // Tổng doanh thu (phí vận chuyển của đơn hàng đã giao)
        $stmtRev = $this->conn->query("SELECT SUM(shipping_fee) as revenue FROM orders WHERE status = 'DELIVERED'");
        $revenue = $stmtRev->fetch(PDO::FETCH_ASSOC)['revenue'];

        // Tổng chuyến xe đang chạy
        $stmtTrip = $this->conn->query("SELECT COUNT(*) as active_trips FROM trips WHERE status = 'IN_PROGRESS'");
        $activeTrips = $stmtTrip->fetch(PDO::FETCH_ASSOC)['active_trips'];

        return [
            "total_orders" => $totalOrders,
            "revenue" => $revenue ?? 0,
            "active_trips" => $activeTrips
        ];
    }
}
?>