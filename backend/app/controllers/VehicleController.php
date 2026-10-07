<?php

require_once __DIR__ . '/../../core/Controller.php';
require_once __DIR__ . '/../models/Vehicle.php';

class VehicleController extends Controller
{
    public function handle($action)
    {
        header('Cache-Control: no-store, private');
        if (empty($_SESSION['user_id'])) $this->error('Vui lòng đăng nhập.', 401);
        try {
            $model = new Vehicle();
            if (!$model->isManager($_SESSION['user_id'])) $this->error('Chỉ ADMIN/STAFF đang hoạt động được quản lý phương tiện.', 403);
            if ($action === 'index') {
                $search = $this->query('search'); $status = $this->query('status');
                if ($status !== '' && !in_array($status, Vehicle::STATUSES, true)) throw new DomainException('Bộ lọc trạng thái không hợp lệ.', 422);
                $this->success($model->getAll($search, $status));
            }
            if ($action === 'stats') $this->success($model->stats());
            if ($action === 'store') $this->success($model->create(Vehicle::validate($this->body())), 'Thêm phương tiện thành công.');
            $id = Vehicle::positiveId($_GET['id'] ?? null);
            switch ($action) {
                case 'show':
                    $vehicle = $model->findById($id);
                    if (!$vehicle) $this->error('Không tìm thấy phương tiện.', 404);
                    $this->success($vehicle); break;
                case 'update': $this->success($model->change($id, Vehicle::validate($this->body())), 'Cập nhật phương tiện thành công.'); break;
                case 'destroy': $this->success($model->change($id, null, true), 'Xóa phương tiện thành công.'); break;
                default: $this->error('Thao tác không tồn tại.', 404);
            }
        } catch (VehicleValidationException $e) {
            $this->json(['success' => false, 'message' => $e->getMessage(), 'errors' => $e->fields], $e->getCode());
        } catch (DomainException $e) {
            $this->error($e->getMessage(), $e->getCode());
        } catch (PDOException $e) {
            error_log('Vehicle API: ' . get_class($e) . ', code=' . $e->getCode());
            $code = (int)($e->errorInfo[1] ?? 0);
            if ($code === 1062) $this->json(['success' => false, 'message' => 'Biển số đã được sử dụng.', 'errors' => ['license_plate' => 'Biển số đã được sử dụng.']], 409);
            if (in_array($code, [1451,1452], true)) $this->error('Phương tiện có dữ liệu liên quan; không thể thực hiện thao tác này.', 409);
            $this->error('Không thể xử lý phương tiện lúc này. Vui lòng thử lại hoặc liên hệ quản trị viên.', 500);
        } catch (Throwable $e) {
            error_log('Vehicle API: ' . get_class($e) . ', code=' . $e->getCode());
            $this->error('Không thể xử lý yêu cầu phương tiện. Vui lòng thử lại.', 500);
        }
    }

    private function query($key): string
    {
        $value = $_GET[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > 150) throw new DomainException('Tham số tìm kiếm/lọc không hợp lệ.', 422);
        return trim($value);
    }

    private function body(): array
    {
        $object = json_decode(file_get_contents('php://input'));
        if (json_last_error() !== JSON_ERROR_NONE || !is_object($object)) throw new DomainException('Thông tin gửi lên không hợp lệ.', 400);
        return (array)$object;
    }
}
