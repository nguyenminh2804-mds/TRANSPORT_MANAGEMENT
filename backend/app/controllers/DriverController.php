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
        $phase = 'users';
        try {
            // Vẫn xác minh tài khoản thật trong module User, không tin role phía frontend/session.
            $user = (new User())->findById($_SESSION['user_id']);
            if (!$user || (int)$user['status'] !== 1 || !in_array($user['role'], ['ADMIN', 'STAFF'], true)) {
                $this->error('Chỉ Admin và nhân viên vận tải đang hoạt động được quản lý tài xế.', 403);
            }
            $phase = 'drivers/assignments/trips';
            $model = new Driver();
            if ($action === 'index') {
                $search = $this->query('search');
                $status = $this->query('status');
                if ($status !== '') {
                    $this->validateStatus($status);
                }
                $this->success($model->getAll($search, $status));
            }
            if ($action === 'store') {
                $data = $this->body();
                $profile = $this->validate($data);
                $account = $this->validateAccount($data);
                $this->success($model->createWithAccount($profile, $account), 'Tạo hồ sơ và tài khoản DRIVER thành công.');
            }
            // Giữ query parameter id để tương thích routes hiện có; dữ liệu dùng driver_id.
            $id = $this->positiveId($_GET['id'] ?? null);
            switch ($action) {
                case 'show':
                    $driver = $model->findById($id);
                    if (!$driver) {
                        $this->error('Không tìm thấy tài xế.', 404);
                    }
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
                default:
                    $this->error('Thao tác không tồn tại.', 404);
            }
        } catch (DomainException $e) {
            $this->error($e->getMessage(), $e->getCode());
        } catch (PDOException $e) {
            error_log('Driver API error: ' . get_class($e) . ', code=' . $e->getCode());
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1146) {
                $this->error('Thiếu bảng ' . $phase . ' trong database. Cần schema chính thức; chức năng không bỏ qua đăng nhập/quyền.', 503);
            }
            if ($code === 1062) {
                $this->error('Mã tài xế, số GPLX hoặc tài khoản liên kết đã được sử dụng.', 409);
            }
            if (in_array($code, [1451, 1452], true)) {
                $this->error('Có dữ liệu liên quan hoặc liên kết không hợp lệ; không thể thực hiện thao tác.', 409);
            }
            $this->error('Không thể xử lý dữ liệu tài xế. Kiểm tra kết nối và schema database.', 500);
        } catch (Throwable $e) {
            error_log('Driver API error: ' . get_class($e) . ', code=' . $e->getCode());
            $this->error('Có lỗi khi xử lý yêu cầu tài xế.', 500);
        }
    }

    private function positiveId($value)
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)$value)) {
            $this->error('ID phải là số nguyên dương.', 422);
        }
        $value = (string)$value;
        if (strlen($value) === 20 && strcmp($value, '18446744073709551615') > 0) {
            $this->error('ID vượt giới hạn BIGINT UNSIGNED.', 422);
        }
        return $value;
    }

    private function query($key)
    {
        $value = $_GET[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > 150) {
            $this->error('Tham số tìm kiếm/lọc không hợp lệ.', 422);
        }
        return trim($value);
    }

    private function body()
    {
        $object = json_decode(file_get_contents('php://input'));
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object)) {
            $this->error('Body phải là một JSON object hợp lệ.', 400);
        }
        return (array)$object;
    }

    private function validateStatus($value)
    {
        if (!is_string($value) || !in_array($value, self::STATUSES, true)) {
            $this->error('Trạng thái phải là available, on_trip hoặc inactive.', 422);
        }
    }

    private function validateAccount($data)
    {
        if (($data['user_id'] ?? null) !== null) {
            $this->error('Khi thêm tài xế, tài khoản được tạo mới. Chỉ liên kết user_id có sẵn khi sửa hồ sơ.', 422);
        }
        if (!isset($data['username']) || !is_string($data['username'])) {
            $this->error('Tên đăng nhập (username) là bắt buộc.', 422);
        }
        $username = trim($data['username']);
        if ($username === '' || mb_strlen($username) > 50 || preg_match('/[\x00-\x1F\x7F]/u', $username)) {
            $this->error('Tên đăng nhập (username) bắt buộc, tối đa 50 ký tự và không chứa ký tự điều khiển.', 422);
        }
        $password = $data['password'] ?? null;
        // Match the application's non-empty password rule. Do not trim passwords.
        // PASSWORD_DEFAULT currently uses bcrypt; reject >72 bytes rather than truncate.
        if (!is_string($password) || trim($password) === '' || strlen($password) > 72 || strpos($password, "\0") !== false) {
            $this->error('Mật khẩu (password) bắt buộc, tối đa 72 byte UTF-8 và không chứa ký tự null.', 422);
        }
        if (mb_strlen(trim($data['full_name'] ?? '')) > 100) {
            $this->error('Họ tên (full_name) tối đa 100 ký tự khi tạo tài khoản đăng nhập.', 422);
        }
        return ['username' => $username, 'password' => $password];
    }

    private function validate($data)
    {
        $result = [];
        foreach (['driver_code' => 50, 'full_name' => 150, 'phone' => 20, 'license_number' => 50, 'license_class' => 30] as $field => $limit) {
            if (!isset($data[$field]) || !is_string($data[$field])) {
                $this->error('Thiếu hoặc sai kiểu dữ liệu trường ' . $field . '.', 422);
            }
            $value = trim($data[$field]);
            if ($value === '' || mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
                $this->error('Trường ' . $field . ' bắt buộc, tối đa ' . $limit . ' ký tự và không chứa ký tự điều khiển.', 422);
            }
            $result[$field] = $value;
        }
        if (!preg_match('/^\+?[0-9]{9,15}$/D', $result['phone'])) {
            $this->error('Số điện thoại gồm 9–15 chữ số, có thể bắt đầu bằng +.', 422);
        }
        // VARCHAR(30), không khóa danh sách hạng bằng lái theo schema tạm v2.
        $result['license_class'] = mb_strtoupper($result['license_class']);
        if (mb_strlen($result['license_class']) > 30) {
            $this->error('Hạng GPLX sau chuẩn hóa vượt 30 ký tự.', 422);
        }
        $address = $data['address'] ?? null;
        if ($address !== null && (!is_string($address) || mb_strlen(trim($address)) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $address))) {
            $this->error('Địa chỉ phải là chuỗi tối đa 255 ký tự hoặc null.', 422);
        }
        $result['address'] = $address === null || trim($address) === '' ? null : trim($address);
        $expiry = $data['license_expiry'] ?? null;
        if ($expiry === '') {
            $expiry = null;
        }
        if ($expiry !== null) {
            $date = is_string($expiry) && preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $expiry) ? DateTimeImmutable::createFromFormat('!Y-m-d', $expiry) : false;
            if (!$date || $date->format('Y-m-d') !== $expiry) {
                $this->error('Ngày hết hạn GPLX phải là ngày hợp lệ dạng YYYY-MM-DD hoặc null.', 422);
            }
        }
        // Cho phép lưu hồ sơ GPLX đã hết hạn để quản lý, không thực hiện phân công.
        $result['license_expiry'] = $expiry;
        $result['status'] = $data['status'] ?? 'available';
        $this->validateStatus($result['status']);
        $result['user_id'] = ($data['user_id'] ?? null) === null ? null : $this->positiveId($data['user_id']);
        return $result;
    }
}
