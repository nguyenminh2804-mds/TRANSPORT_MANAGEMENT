<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Driver.php';
require_once __DIR__ . '/../models/User.php';

class DriverController extends Controller
{
    private const STATUSES = ['available', 'on_trip', 'inactive'];

    public function handle($action)
    {
        header('Cache-Control: no-store, private');
        if (empty($_SESSION['user_id'])) {
            $this->error('Vui lòng đăng nhập.', 401);
        }

        try {
            $user = (new User())->findById($_SESSION['user_id']);
            if (!$user || (int)$user['status'] !== 1) {
                $this->error('Tài khoản bị khóa hoặc không tồn tại.', 403);
            }

            $model = new Driver();
            
            // Xử lý quyền truy cập: Nếu là DRIVER, chỉ được xem danh sách
            $role = $user['role'];
            $isAdminOrStaff = in_array($role, ['ADMIN', 'STAFF'], true);

            // ==========================================
            // ACTION: INDEX
            // ==========================================
            if ($action === 'index') {
                if ($isAdminOrStaff) {
                    $search = $this->query('search');
                    $status = $this->query('status');
                    if ($status !== '') {
                        $this->validateStatus($status);
                    }
                    $this->success($model->getAll($search, $status));
                } elseif ($role === 'DRIVER') {
                    $driverInfo = $model->findByUserId($_SESSION['user_id']);
                    if ($driverInfo) {
                        $this->success([$driverInfo]);
                    } else {
                        $this->success([]);
                    }
                } else {
                    $this->error('Không có quyền.', 403);
                }
            }

            // Các chức năng Quản lý: Chỉ dành cho ADMIN/STAFF
            if (!$isAdminOrStaff) {
                $this->error('Chỉ Admin và nhân viên mới có quyền thực hiện thao tác này.', 403);
            }

            // ==========================================
            // ACTION: STORE (THÊM MỚI TÀI XẾ)
            // ==========================================
            if ($action === 'store') {
                $data = $this->body();
                $profile = $this->validate($data);
                
                // KIỂM TRA: Có nhập ID tài khoản liên kết không?
                if ($profile['user_id'] !== null) {
                    // Nếu CÓ ID: Chỉ tạo hồ sơ nghiệp vụ và móc nối vào tài khoản có sẵn
                    $this->success($model->create($profile), 'Tạo hồ sơ và liên kết tài khoản thành công.');
                } else {
                    // Nếu KHÔNG CÓ ID: Bắt buộc kiểm tra Username/Password để tạo mới toàn bộ
                    $account = $this->validateAccount($data);
                    $this->success($model->createWithAccount($profile, $account), 'Tạo hồ sơ và tài khoản DRIVER thành công.');
                }
            }

            $id = $this->positiveId($_GET['id'] ?? null);
            switch ($action) {
                case 'show':
                    $driver = $model->findById($id);
                    if (!$driver) $this->error('Không tìm thấy tài xế.', 404);
                    $this->success($driver);
                    break;
                case 'update':
                    $this->success($model->change($id, $this->validate($this->body())), 'Cập nhật tài xế thành công.');
                    break;
                case 'status':
                    $data = $this->body();
                    $this->validateStatus($data['status'] ?? null);
                    $this->success($model->change($id, null, $data['status']), 'Cập nhật trạng thái thành công.');
                    break;
                case 'destroy':
                    $this->success($model->change($id, null, null, true), 'Xóa tài xế thành công.');
                    break;
            }
        } catch (DomainException $e) {
            $this->error($e->getMessage(), $e->getCode());
        } catch (PDOException $e) {
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1062) $this->error('Dữ liệu (Mã, Số điện thoại hoặc Số GPLX) đã bị trùng lặp.', 409);
            if (in_array($code, [1451, 1452], true)) $this->error('Dữ liệu đang được liên kết với chuyến xe, không thể xóa.', 409);
            $this->error('Lỗi Database.', 500);
        } catch (Throwable $e) {
            $this->error('Lỗi hệ thống khi xử lý yêu cầu.', 500);
        }
    }

    // Các hàm validate phụ trợ
    private function positiveId($value) {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)$value)) {
            $this->error('ID phải là số nguyên dương.', 422);
        }
        return (string)$value;
    }

    private function query($key) {
        return trim($_GET[$key] ?? '');
    }

    private function body() {
        $object = json_decode(file_get_contents('php://input'));
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object)) {
            $this->error('Lỗi dữ liệu đầu vào.', 400);
        }
        return (array)$object;
    }

    private function validateStatus($value) {
        if (!in_array($value, self::STATUSES, true)) {
            $this->error('Trạng thái không hợp lệ.', 422);
        }
    }

    private function validateAccount($data) {
        if (!isset($data['username']) || !is_string($data['username'])) $this->error('Tên đăng nhập bắt buộc.', 422);
        $username = trim($data['username']);
        $password = $data['password'] ?? null;
        if (!is_string($password) || trim($password) === '') $this->error('Mật khẩu bắt buộc.', 422);
        return ['username' => $username, 'password' => $password];
    }

    private function validate($data) {
        $result = [];
        foreach (['driver_code' => 50, 'full_name' => 150, 'phone' => 20, 'license_number' => 50, 'license_class' => 30] as $field => $limit) {
            $value = trim($data[$field] ?? '');
            if ($value === '') $this->error("Trường $field là bắt buộc.", 422);
            $result[$field] = $value;
        }
        $result['address'] = trim($data['address'] ?? null) ?: null;
        $result['license_expiry'] = empty($data['license_expiry']) ? null : $data['license_expiry'];
        $result['status'] = $data['status'] ?? 'available';
        
        // BỔ SUNG: Xử lý thu thập ID tài khoản để phân luồng cập nhật hoặc tạo mới
        $result['user_id'] = empty($data['user_id']) ? null : $this->positiveId($data['user_id']);
        
        return $result;
    }
}