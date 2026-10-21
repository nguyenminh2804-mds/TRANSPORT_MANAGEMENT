<?php

require_once __DIR__ . '/ResourceStatus.php';
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
        $display = ResourceStatus::sql('d','driver_id');
        return "SELECT d.*, ($display) AS display_status, EXISTS(SELECT 1 FROM assignments a WHERE a.driver_id=d.driver_id AND a.status='assigned') AS has_active_assignment, EXISTS(SELECT 1 FROM trips t WHERE t.driver_id=d.driver_id AND t.status='IN_PROGRESS') AS has_active_trip FROM drivers d";
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
        if($status==='assigned'){$sql.=' AND ('.ResourceStatus::sql('d','driver_id').") IN ('pending','accepted','on_trip')";}
        elseif ($status !== '') {
            $sql .= ' AND ('.ResourceStatus::sql('d','driver_id').')=:status';
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
        $data['status'] = 'available';
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
            throw new DomainException('Không thể xóa hồ sơ tài xế đang thực hiện chuyến hoặc có lịch sử chuyến/phân công, kể cả phân công đã hủy hoặc hoàn thành. Lịch sử phải được giữ nguyên.', 409);
        }
        foreach ($assignments as $assignment) {
            if ($assignment['status'] === 'assigned' && $targetStatus !== null && $targetStatus !== $driver['status']) {
                throw new DomainException('Không thể đổi trạng thái thủ công khi tài xế còn phân công assigned đang hiệu lực.', 409);
            }
        }
    }

    private function lockAccountForDeletion($userId, $driverId)
    {
        if($userId===null)return null;
        $stmt=$this->conn->prepare('SELECT id,role FROM users WHERE id=? FOR UPDATE');
        $stmt->execute([$userId]);$account=$stmt->fetch();
        if(!$account||(string)$account['id']!==(string)$userId||$account['role']!=='DRIVER')throw new DomainException('Không thể xóa an toàn: tài khoản liên kết không tồn tại hoặc không có role DRIVER. Hồ sơ được giữ nguyên.',409);
        $stmt=$this->conn->prepare('SELECT driver_id FROM drivers WHERE user_id=? AND driver_id<>? FOR UPDATE');
        $stmt->execute([$userId,$driverId]);
        if($stmt->fetch())throw new DomainException('Tài khoản còn liên kết với hồ sơ tài xế khác; không thể xóa.',409);
        // assigned_by is a logical user reference in the current schema, without a FK.
        $stmt=$this->conn->prepare('SELECT assignment_id FROM assignments WHERE assigned_by=? FOR UPDATE');
        $stmt->execute([$userId]);
        if($stmt->fetch())throw new DomainException('Tài khoản còn được ghi nhận là người phân công trong lịch sử; không thể xóa hồ sơ và tài khoản.',409);
        // Read live FK metadata so no account deletion can cascade or null unrelated data.
        $stmt=$this->conn->prepare("SELECT TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,(TABLE_SCHEMA=DATABASE()) AS is_local FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME='users' AND REFERENCED_COLUMN_NAME='id'");
        $stmt->execute();$references=$stmt->fetchAll();
        foreach($references as $reference){
            $table='`'.str_replace('`','``',$reference['TABLE_SCHEMA']).'`.`'.str_replace('`','``',$reference['TABLE_NAME']).'`';
            $column='`'.str_replace('`','``',$reference['COLUMN_NAME']).'`';
            $sql="SELECT $column FROM $table WHERE $column=?";$params=[$userId];
            if($reference['is_local']&&$reference['TABLE_NAME']==='drivers'){$sql.=' AND driver_id<>?';$params[]=$driverId;}
            $linked=$this->conn->prepare($sql.' FOR UPDATE');$linked->execute($params);
            if($linked->fetch())throw new DomainException('Không thể xóa tài khoản liên kết vì còn dữ liệu hoặc lịch sử được khóa ngoại tham chiếu. Cả hồ sơ và tài khoản được giữ nguyên.',409);
        }
        return (string)$account['id'];
    }

    public function change($id, $data = null, $status = null, $delete = false, $actor = null, $reason = null, $expected = null)
    {
        $this->conn->beginTransaction();
        try {
            if($actor!==null){$auth=$this->conn->prepare("SELECT id FROM users WHERE id=? AND role IN ('ADMIN','STAFF') AND status=1 FOR UPDATE");$auth->execute([$actor]);if(!$auth->fetch())throw new DomainException('Tài khoản không còn quyền hiệu chỉnh.',403);}
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
            $trips=$stmt->fetchAll();
            if($data!==null){
                if(isset($data['driver_code'])&&$data['driver_code']!==$driver['driver_code'])throw new DomainException('Không được thay đổi mã tài xế khi sửa hồ sơ.',422);
                $data['driver_code']=$driver['driver_code'];
                if(array_key_exists('user_id',$data)&&($data['user_id']===null?null:(string)$data['user_id'])!==($driver['user_id']===null?null:(string)$driver['user_id']))throw new DomainException('Không được thay đổi ID tài khoản tài xế khi sửa hồ sơ.',422);
                $data['user_id']=$driver['user_id'];
            }
            if($data!==null&&!array_key_exists('status',$data))$data['status']=$driver['status'];
            $target=$data['status']??$status;
            self::assertChangeAllowed($driver,$assignments,$target,$delete,$trips);
            if(!$delete&&$target!==$driver['status']){
                if(!in_array($driver['status'],['available','inactive'],true)||!in_array($target,['available','inactive'],true)||array_filter($trips,fn($t)=>$t['status']==='IN_PROGRESS'))throw new DomainException('Không được hiệu chỉnh trạng thái do chuyến quyết định.',409);
                if($expected!==$driver['status'])throw new DomainException('Trạng thái đã thay đổi. Hãy tải lại hồ sơ trước khi hiệu chỉnh.',409);
                if($actor===null||!is_string($reason)||trim($reason)===''||mb_strlen($reason)>2000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$reason))throw new DomainException('Hiệu chỉnh cần lý do hợp lệ và tài khoản nhân viên.',422);
                $audit=$this->conn->prepare('INSERT INTO driver_status_corrections (driver_id,old_status,new_status,reason,changed_by,changed_at) VALUES (?,?,?,?,?,NOW())');$audit->execute([$id,$driver['status'],$target,trim($reason),$actor]);
            }
            if ($delete) {
                foreach(['assignment_requests'=>'yêu cầu của tài xế','driver_status_corrections'=>'hiệu chỉnh trạng thái'] as $table=>$history){
                    $linked=$this->conn->prepare("SELECT driver_id FROM $table WHERE driver_id=? FOR UPDATE");
                    $linked->execute([$id]);
                    if($linked->fetch())throw new DomainException('Không thể xóa hồ sơ tài xế vì có lịch sử '.$history.'. Các bản ghi liên quan phải được giữ nguyên.',409);
                }
                $accountId=$this->lockAccountForDeletion($driver['user_id'],$id);
                // Both explicit deletes share this transaction; never delete history or other accounts.
                $stmt = $this->conn->prepare('DELETE FROM drivers WHERE driver_id=?');
                $stmt->execute([$id]);
                if($accountId!==null){
                    $stmt=$this->conn->prepare("DELETE FROM users WHERE id=? AND role='DRIVER'");
                    $stmt->execute([$accountId]);
                    if($stmt->rowCount()!==1)throw new DomainException('Không xóa được đúng tài khoản liên kết; toàn bộ thao tác đã được hoàn tác.',409);
                }
                $result = ['account_deleted'=>$accountId!==null];
            } elseif ($data !== null) {
                $this->validateUser($data['user_id']);
                if($data['user_id']!==null){
                    if(mb_strlen($data['full_name'])>100)throw new DomainException('Họ tên (full_name) tối đa 100 ký tự để đồng bộ tài khoản liên kết.',422);
                    $linked=$this->conn->prepare("SELECT id FROM users WHERE id=? AND role='DRIVER' AND status=1 FOR UPDATE");
                    $linked->execute([$data['user_id']]);
                    if(!$linked->fetch())throw new DomainException('Tài khoản liên kết không còn hợp lệ. Hãy tải lại hồ sơ.',409);
                }
                $stmt = $this->conn->prepare('UPDATE drivers SET driver_code=:driver_code, full_name=:full_name, phone=:phone, address=:address, license_number=:license_number, license_class=:license_class, license_expiry=:license_expiry, status=:status, user_id=:user_id WHERE driver_id=:driver_id');
                $stmt->execute(array_merge($data, ['driver_id' => $id]));
                if($data['user_id']!==null){
                    $linked=$this->conn->prepare('UPDATE users SET full_name=? WHERE id=?');
                    $linked->execute([$data['full_name'],$data['user_id']]);
                }
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
