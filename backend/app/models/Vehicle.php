<?php

require_once __DIR__ . '/../../config/database.php';

class VehicleValidationException extends DomainException
{
    public $fields;
    public function __construct(array $fields, int $code = 422)
    {
        parent::__construct('Vui lòng kiểm tra các trường được đánh dấu.', $code);
        $this->fields = $fields;
    }
}

class Vehicle
{
    private $conn;
    public const STATUSES = ['available', 'on_trip', 'maintenance', 'inactive'];

    public function __construct(?PDO $connection = null)
    {
        // PDO exceptions reach the controller; connection details never reach the client.
        $this->conn = $connection ?? new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }

    public function isManager($id)
    {
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id=? AND status=1 AND role IN ('ADMIN','STAFF')");
        $stmt->execute([$id]);
        return (bool)$stmt->fetch();
    }

    public static function normalizePlate(string $value): string
    {
        return strtoupper(preg_replace('/[\s\p{Z}]+/u', '', $value));
    }

    public static function validate(array $data): array
    {
        $errors = [];
        $plate = is_string($data['license_plate'] ?? null) ? self::normalizePlate($data['license_plate']) : '';
        if ($plate === '' || strlen($plate) > 30 || !preg_match('/^[A-Z0-9.-]+$/D', $plate)) {
            $errors['license_plate'] = 'Biển số bắt buộc, tối đa 30 ký tự: chữ, số, dấu chấm hoặc gạch ngang.';
        }
        $type = is_string($data['vehicle_type'] ?? null) ? trim($data['vehicle_type']) : '';
        if ($type === '' || mb_strlen($type) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $type)) {
            $errors['vehicle_type'] = 'Loại xe bắt buộc, tối đa 100 ký tự và không chứa ký tự điều khiển.';
        }
        $brand = $data['brand'] ?? null;
        if ($brand !== null && (!is_string($brand) || mb_strlen(trim($brand)) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $brand))) {
            $errors['brand'] = 'Hãng xe tối đa 100 ký tự và không chứa ký tự điều khiển.';
        }
        $capacity = $data['capacity'] ?? null;
        $capacity = is_string($capacity) || is_int($capacity) || is_float($capacity) ? (string)$capacity : '';
        if (!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D', $capacity) || (float)$capacity <= 0) {
            $errors['capacity'] = 'Tải trọng phải lớn hơn 0, tối đa 99999999.99 kg và có tối đa 2 chữ số thập phân.';
        }
        $status = $data['status'] ?? 'available';
        if (!is_string($status) || !in_array($status, self::STATUSES, true)) {
            $errors['status'] = 'Vui lòng chọn trạng thái hợp lệ.';
        }
        if ($errors) throw new VehicleValidationException($errors);
        return ['license_plate' => $plate, 'vehicle_type' => $type, 'brand' => $brand === null || trim($brand) === '' ? null : trim($brand), 'capacity' => $capacity, 'status' => $status];
    }

    public static function positiveId($id): string
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/^[1-9][0-9]{0,19}$/D', (string)$id) || (strlen((string)$id) === 20 && strcmp((string)$id, '18446744073709551615') > 0)) {
            throw new DomainException('Mã phương tiện không hợp lệ.', 422);
        }
        return (string)$id;
    }

    private function dto($row)
    {
        if (!$row) return false;
        $row['vehicle_id'] = (string)$row['vehicle_id'];
        $row['capacity'] = (string)$row['capacity'];
        $row['has_active_assignment'] = (bool)$row['has_active_assignment'];
        $row['has_active_trip'] = (bool)$row['has_active_trip'];
        return $row;
    }

    private function selectSql(): string
    {
        return "SELECT v.*, EXISTS(SELECT 1 FROM assignments a WHERE a.vehicle_id=v.vehicle_id AND a.status='assigned') AS has_active_assignment, EXISTS(SELECT 1 FROM assignments a WHERE a.vehicle_id=v.vehicle_id AND a.status='assigned') AS has_active_trip FROM vehicles v";
    }

    public function getAll(string $search = '', string $status = ''): array
    {
        $sql = $this->selectSql() . ' WHERE 1=1'; $params = [];
        if ($search !== '') {
            $sql .= " AND (v.license_plate LIKE :plate ESCAPE '!' OR v.vehicle_type LIKE :type ESCAPE '!' OR v.brand LIKE :brand ESCAPE '!')";
            foreach (['plate','type','brand'] as $key) $params[$key] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        if ($status !== '') { $sql .= ' AND v.status=:status'; $params['status'] = $status; }
        $stmt = $this->conn->prepare($sql . ' ORDER BY v.vehicle_id DESC'); $stmt->execute($params);
        return array_map([$this, 'dto'], $stmt->fetchAll());
    }

    public function stats(): array
    {
        $result = array_fill_keys(array_merge(['total'], self::STATUSES), 0);
        foreach ($this->conn->query('SELECT status, COUNT(*) AS amount FROM vehicles GROUP BY status')->fetchAll() as $row) {
            $result[$row['status']] = (int)$row['amount']; $result['total'] += (int)$row['amount'];
        }
        return $result;
    }

    public function findById(string $id)
    {
        $stmt = $this->conn->prepare($this->selectSql() . ' WHERE v.vehicle_id=?'); $stmt->execute([$id]);
        return $this->dto($stmt->fetch());
    }

    private function assertUnique(string $plate, ?string $exclude = null)
    {
        // Check legacy, non-normalized records without rewriting any existing data.
        $stmt = $this->conn->prepare('SELECT vehicle_id, license_plate FROM vehicles'); $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            if ((string)$row['vehicle_id'] !== $exclude && self::normalizePlate($row['license_plate']) === $plate) {
                throw new VehicleValidationException(['license_plate' => 'Biển số đã được sử dụng (kể cả khác chữ hoa/thường hoặc khoảng trắng).'], 409);
            }
        }
        // The UNIQUE index also rejects concurrent normalized inserts/updates.
    }

    public static function assertChangeAllowed(array $vehicle, array $assignments, array $trips, ?string $target, bool $delete)
    {
        if ($delete && ($assignments || $trips || $vehicle['status'] === 'on_trip')) {
            throw new DomainException('Không thể xóa phương tiện đang thực hiện chuyến hoặc đã có lịch sử chuyến/phân công.', 409);
        }
        if ($target !== null && $target !== $vehicle['status']) {
            foreach ($assignments as $assignment) if ($assignment['status'] === 'assigned') {
                throw new VehicleValidationException(['status' => 'Không thể đổi trạng thái khi còn phân công đang hoạt động.'], 409);
            }
        }
    }

    public function create(array $data)
    {
        $this->assertUnique($data['license_plate']);
        $stmt = $this->conn->prepare('INSERT INTO vehicles (license_plate, vehicle_type, brand, capacity, status) VALUES (:license_plate,:vehicle_type,:brand,:capacity,:status)');
        $stmt->execute($data);
        return $this->findById((string)$this->conn->lastInsertId());
    }

    public function change(string $id, ?array $data = null, bool $delete = false)
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT * FROM vehicles WHERE vehicle_id=? FOR UPDATE'); $stmt->execute([$id]); $vehicle = $stmt->fetch();
            if (!$vehicle) throw new DomainException('Không tìm thấy phương tiện.', 404);
            $stmt = $this->conn->prepare('SELECT assignment_id, status FROM assignments WHERE vehicle_id=? FOR UPDATE'); $stmt->execute([$id]); $assignments = $stmt->fetchAll();
            $stmt = $this->conn->prepare('SELECT id, status FROM trips WHERE vehicle_id=? FOR UPDATE'); $stmt->execute([$id]); $trips = $stmt->fetchAll();
            self::assertChangeAllowed($vehicle, $assignments, $trips, $data['status'] ?? null, $delete);
            if ($delete) {
                $stmt = $this->conn->prepare('DELETE FROM vehicles WHERE vehicle_id=?'); $stmt->execute([$id]); $result = [];
            } else {
                if ($data === null) throw new DomainException('Thiếu thông tin phương tiện.', 422);
                $this->assertUnique($data['license_plate'], $id);
                $stmt = $this->conn->prepare('UPDATE vehicles SET license_plate=:license_plate, vehicle_type=:vehicle_type, brand=:brand, capacity=:capacity, status=:status WHERE vehicle_id=:vehicle_id');
                $stmt->execute(array_merge($data, ['vehicle_id' => $id])); $result = $this->findById($id);
            }
            $this->conn->commit(); return $result;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) $this->conn->rollBack();
            throw $e;
        }
    }
}
