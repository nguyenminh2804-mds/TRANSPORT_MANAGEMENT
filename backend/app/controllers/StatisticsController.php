<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Statistics.php';

class StatisticsController extends Controller
{
    private $statsModel;

    public function __construct()
    {
        $this->statsModel = new Statistics();
    }

    public function handle($action)
    {
        // Yêu cầu đăng nhập và có quyền ADMIN/STAFF
        if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'], ['ADMIN', 'STAFF'])) {
            $this->error('Bạn không có quyền truy cập thống kê.', 403);
        }

        switch ($action) {
            case 'overview':
                $this->overview();
                break;
            default:
                $this->error('Hành động không hợp lệ.', 400);
        }
    }

    private function overview()
    {
        try {
            $data = $this->statsModel->getOverview();
            $this->success($data, 'Lấy dữ liệu thống kê thành công.');
        } catch (Exception $e) {
            $this->error('Lỗi khi lấy dữ liệu thống kê: ' . $e->getMessage(), 500);
        }
    }
}