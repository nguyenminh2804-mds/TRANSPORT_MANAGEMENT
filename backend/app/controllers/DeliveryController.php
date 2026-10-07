<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Controller.php';

require_once __DIR__ . '/../models/Delivery.php';
require_once __DIR__ . '/../models/Driver.php';


class DeliveryController extends Controller
{
    private $deliveryModel;
    private $driverModel;


    public function __construct()
    {
        /*
         * Tạo kết nối database
         */
        $database = new Database();
        $db = $database->connect();


        /*
         * Khởi tạo các model
         */
        $this->deliveryModel = new Delivery($db);
        $this->driverModel = new Driver();
    }


    // ============================================================
    // UC-DH-03 - Cập nhật trạng thái giao hàng
    // ============================================================
    public function updateStatus()
    {
        // ========================================================
        // BƯỚC 1 - Kiểm tra đăng nhập
        // ========================================================

        if (!isset($_SESSION['user_id'])) {

            $this->error(
                'Chưa đăng nhập',
                401
            );

            return;
        }


        // ========================================================
        // BƯỚC 2 - Kiểm tra quyền nhân viên giao nhận
        // ========================================================

        if (
            !isset($_SESSION['role']) ||
            $_SESSION['role'] !== 'DRIVER'
        ) {

            $this->error(
                'Bạn không có quyền cập nhật trạng thái giao hàng',
                403
            );

            return;
        }


        // ========================================================
        // BƯỚC 3 - Đọc dữ liệu JSON
        // ========================================================

        $data = json_decode(
            file_get_contents('php://input'),
            true
        );


        if (!is_array($data)) {

            $this->error(
                'Dữ liệu gửi lên không hợp lệ',
                400
            );

            return;
        }


        // ========================================================
        // BƯỚC 4 - Lấy dữ liệu request
        // ========================================================

        $orderId = $data['order_id'] ?? null;

        $newStatus = strtoupper(
            trim($data['status'] ?? '')
        );


        // ========================================================
        // BƯỚC 5 - Kiểm tra order_id
        // ========================================================

        if (
            $orderId === null ||
            !is_numeric($orderId)
        ) {

            $this->error(
                'ID đơn hàng không hợp lệ',
                400
            );

            return;
        }


        // ========================================================
        // BƯỚC 6 - Kiểm tra trạng thái mới
        // ========================================================

        if ($newStatus === '') {

            $this->error(
                'Vui lòng nhập trạng thái mới',
                400
            );

            return;
        }


        // ========================================================
        // BƯỚC 7 - Tìm tài xế đang đăng nhập
        // ========================================================

        $driver = $this->driverModel->findByUserId(
            $_SESSION['user_id']
        );


        if (!$driver) {

            $this->error(
                'Không tìm thấy thông tin nhân viên giao nhận',
                404
            );

            return;
        }


        // ========================================================
        // BƯỚC 8 - Kiểm tra đơn có được phân công hay không
        // ========================================================

        /*
         * Driver.php của project đang kiểm tra:
         *
         * drivers
         *     ↓
         * trips
         *     ↓
         * trip_orders
         *     ↓
         * orders
         */

        $assignment = $this->driverModel->isOrderAssigned(
            $driver['id'],
            $orderId
        );


        if (!$assignment) {

            $this->error(
                'Bạn không có quyền cập nhật đơn hàng này',
                403
            );

            return;
        }


        // ========================================================
        // BƯỚC 9 - Cập nhật trạng thái
        // ========================================================

        try {

            $result = $this->deliveryModel->updateDeliveryStatus(
                $orderId,
                $_SESSION['user_id'],
                $newStatus
            );


            // ====================================================
            // BƯỚC 10 - Trả kết quả
            // ====================================================

            $this->success(
                $result,
                'Cập nhật trạng thái giao hàng thành công'
            );

            return;


        } catch (Exception $e) {

            /*
             * Nếu lỗi:
             * - Trạng thái cũ được giữ nguyên
             * - Không tạo lịch sử sai
             */

            $this->error(
                $e->getMessage(),
                400
            );

            return;
        }
    }
}