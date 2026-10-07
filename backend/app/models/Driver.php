<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';

class Driver
{
    private $conn;

    public function __construct(?PDO $connection = null)
    {
        $this->conn = $connection ?? (new Database())->connect();
    }

    private function selectSql()
    {
        return "SELECT d.*, EXISTS(SELECT 1 FROM assignments a WHERE a.driver_id=d.driver_id AND a.status='assigned') AS has_active_assignment, EXISTS(SELECT 1 FROM trips t WHERE t.driver_id=d.driver_id AND t.status IN ('PLANNED','IN_PROGRESS')) AS has_active_trip FROM drivers d";
    }

    private function normalize($driver)
    {
        if (!$driver) {
            return false;
        }
        // BIGINT UNSIGNED có thể vượt độ chính xác Number của JavaScript.
        $driver['driver_id'] = (string)$driver['driver_id'];
        $driver['user_id'] = $driver['user_id'] === null ? null : (string)$driver['user_id'];
        $driver['has_active_assignment'] = (bool)$driver['has_active_assignment'];
        $driver['has_active_trip'] = (bool)$driver['has_active_trip'];
        return $driver;
    }

    public function getAll($search, $status)
    {
        $sql = $this->selectSql() . ' WHERE 1=1';
        $params = [];
        if ($search !== '') {
            $sql .= " AND (d.full_name LIKE :name ESCAPE '!' OR d.phone LIKE :phone ESCAPE '!' OR d.license_number LIKE :license ESCAPE '!' OR d.driver_code LIKE :code ESCAPE '!')";
            foreach (['name', 'phone', 'license', 'code'] as $key) {
                $params[$key] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            }
        }
        if ($status !== '') {
            $sql .= ' AND d.status=:status';
            $params['status'] = $status;
        }
        $stmt = $this->conn->prepare($sql . ' ORDER BY d.driver_id DESC');
        $stmt->execute($params);
        return array_map([$this, 'normalize'], $stmt->fetchAll());
    }

    public function findById($id)
    {
        $stmt = $this->conn->prepare($this->selectSql() . ' WHERE d.driver_id=?');
        $stmt->execute([$id]);
        return $this->normalize($stmt->fetch());
    }

    private function validateUser($id)
    {
        if ($id === null) {
            return;
        }
        $stmt = $this->conn->prepare("SELECT id FROM users WHERE id=? AND role='DRIVER' AND status=1");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            throw new DomainException('Tài khoản liên kết phải tồn tại, đang hoạt động và có quyền DRIVER.', 422);
        }
    }

    public function create($data)
    {
        $this->validateUser($data['user_id']);
        $stmt = $this->conn->prepare('INSERT INTO drivers (driver_code, full_name, phone, address, license_number, license_class, license_expiry, status, user_id) VALUES (:driver_code, :full_name, :phone, :address, :license_number, :license_class, :license_expiry, :status, :user_id)');
        $stmt->execute($data);
        return $this->findById($this->conn->lastInsertId());
    }

    // Both records use this one PDO connection and one transaction.
    public function createWithAccount($profile, $account)
    {
        $hash = password_hash($account['password'], PASSWORD_DEFAULT);
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT id FROM users WHERE username=? LIMIT 1');
            $stmt->execute([$account['username']]);
            if ($stmt->fetch()) {
                throw new DomainException('Tên đăng nhập đã tồn tại.', 409);
            }
            try {
                $stmt = $this->conn->prepare("INSERT INTO users (username, password, full_name, role, status) VALUES (:username, :password, :full_name, 'DRIVER', 1)");
                $stmt->execute(['username' => $account['username'], 'password' => $hash, 'full_name' => $profile['full_name']]);
            } catch (PDOException $e) {
                // The UNIQUE username constraint also covers concurrent requests.
                if ((int)($e->errorInfo[1] ?? 0) === 1062) {
                    throw new DomainException('Tên đăng nhập đã tồn tại.', 409);
                }
                throw $e;
            }
            $profile['user_id'] = (string)$this->conn->lastInsertId();
            $driver = $this->create($profile);
            if (!$driver) {
                throw new RuntimeException('Không đọc được hồ sơ vừa tạo.');
            }
            $this->conn->commit();
            // Only the existing driver DTO is returned, never account credentials.
            return $driver;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public static function assertChangeAllowed($driver, $assignments, $targetStatus, $delete, $trips = [])
    {
        if ($delete && ($assignments || $trips || $driver['status'] === 'on_trip')) {
            throw new DomainException('Không thể xóa tài xế đang thực hiện chuyến hoặc có lịch sử phân công/chuyến. Hãy dùng trạng thái Ngừng làm việc khi phù hợp.', 409);
        }
        foreach ($trips as $trip) {
            if (in_array($trip['status'], ['PLANNED', 'IN_PROGRESS'], true) && $targetStatus !== null && $targetStatus !== $driver['status']) {
                throw new DomainException('Không thể đổi trạng thái thủ công khi tài xế còn chuyến PLANNED/IN_PROGRESS tham chiếu trực tiếp.', 409);
            }
        }
        foreach ($assignments as $assignment) {
            if ($assignment['status'] === 'assigned' && $targetStatus !== null && $targetStatus !== $driver['status']) {
                throw new DomainException('Không thể đổi trạng thái thủ công khi tài xế còn phân công assigned đang hiệu lực.', 409);
            }
        }
    }

    public function change($id, $data = null, $status = null, $delete = false)
    {
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare('SELECT * FROM drivers WHERE driver_id=? FOR UPDATE');
            $stmt->execute([$id]);
            $driver = $stmt->fetch();
            if (!$driver) {
                throw new DomainException('Không tìm thấy tài xế.', 404);
            }
            $stmt = $this->conn->prepare('SELECT assignment_id, status FROM assignments WHERE driver_id=? FOR UPDATE');
            $stmt->execute([$id]);
            $assignments = $stmt->fetchAll();
            // Bản full v2 giữ cả quan hệ trips trực tiếp; không bỏ sót lịch sử này.
            $stmt = $this->conn->prepare('SELECT id, status FROM trips WHERE driver_id=? FOR UPDATE');
            $stmt->execute([$id]);
            self::assertChangeAllowed($driver, $assignments, $data['status'] ?? $status, $delete, $stmt->fetchAll());
            if ($delete) {
                $stmt = $this->conn->prepare('DELETE FROM drivers WHERE driver_id=?');
                $stmt->execute([$id]);
                $result = [];
            } elseif ($data !== null) {
                $this->validateUser($data['user_id']);
                $stmt = $this->conn->prepare('UPDATE drivers SET driver_code=:driver_code, full_name=:full_name, phone=:phone, address=:address, license_number=:license_number, license_class=:license_class, license_expiry=:license_expiry, status=:status, user_id=:user_id WHERE driver_id=:driver_id');
                $stmt->execute(array_merge($data, ['driver_id' => $id]));
                $result = $this->findById($id);
            } else {
                $stmt = $this->conn->prepare('UPDATE drivers SET status=? WHERE driver_id=?');
                $stmt->execute([$status, $id]);
                $result = $this->findById($id);
            }
            $this->conn->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }
}
