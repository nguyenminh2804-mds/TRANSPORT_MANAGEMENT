<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class Statistics
{
    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->connect();
    }

    public function getOverview()
    {
        // 1. Thống kê Đơn hàng
        $stmtOrder = $this->conn->query("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'DELIVERED' THEN 1 ELSE 0 END) as delivered_orders
            FROM orders
        ");
        $orders = $stmtOrder->fetch();

        // 2. Thống kê Doanh thu (Tính tổng cước phí của các đơn đã thanh toán hoặc đã giao)
        $stmtRev = $this->conn->query("
            SELECT SUM(shipping_fee) as total_revenue 
            FROM orders 
            WHERE payment_status = 'PAID' OR status = 'DELIVERED'
        ");
        $revenue = $stmtRev->fetch()['total_revenue'] ?? 0;

        // 3. Thống kê Chuyến xe
        $stmtTrip = $this->conn->query("
            SELECT 
                COUNT(*) as total_trips,
                SUM(CASE WHEN status = 'IN_PROGRESS' THEN 1 ELSE 0 END) as in_progress_trips
            FROM trips
        ");
        $trips = $stmtTrip->fetch();

        // 4. Đếm số lượng Tài xế & Phương tiện
        $totalVehicles = $this->conn->query("SELECT COUNT(*) FROM vehicles")->fetchColumn();
        $totalDrivers = $this->conn->query("SELECT COUNT(*) FROM drivers")->fetchColumn();

        return [
            'revenue' => (float)$revenue,
            'orders' => [
                'total' => (int)$orders['total_orders'],
                'pending' => (int)$orders['pending_orders'],
                'delivered' => (int)$orders['delivered_orders']
            ],
            'trips' => [
                'total' => (int)$trips['total_trips'],
                'in_progress' => (int)$trips['in_progress_trips']
            ],
            'resources' => [
                'vehicles' => (int)$totalVehicles,
                'drivers' => (int)$totalDrivers
            ]
        ];
    }
    
}