<?php
require_once __DIR__.'/Assignment.php';
class DriverPortal
{
    protected $db;
    public function __construct($db=null) { $this->db=$db??new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
    protected function rows($sql,$params=[]) { $s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(); }
    protected function write($sql,$params) { $s=$this->db->prepare($sql);$s->execute($params); }
    public function identity($userId,$lock=false) {
        $id=Assignment::id($userId,true);
        $u=$this->rows("SELECT id FROM users WHERE id=? AND role='DRIVER' AND status=1".($lock?' FOR UPDATE':''),[$id]);
        if(!$u)throw new DomainException('Chỉ tài khoản DRIVER đang hoạt động được truy cập.',403);
        $d=$this->rows("SELECT driver_id,driver_code,full_name,phone,address,license_number,license_class,license_expiry,status,EXISTS(SELECT 1 FROM assignments a WHERE a.driver_id=drivers.driver_id AND a.status='assigned') AS has_active_assignment,EXISTS(SELECT 1 FROM trips t WHERE t.driver_id=drivers.driver_id AND t.status='IN_PROGRESS') AS has_active_trip FROM drivers WHERE user_id=?".($lock?' FOR UPDATE':''),[$id])[0]??null;
        // A valid DRIVER account without its own profile cannot access any Driver data.
        // Never fall back to another profile or accept a driver ID supplied by the client.
        if(!$d)throw new DomainException('Tài khoản không có hồ sơ tài xế liên kết; không thể truy cập chức năng Driver. Vui lòng liên hệ nhân viên.',403);
        $d['driver_id']=(string)$d['driver_id'];$d['has_active_assignment']=(bool)($d['has_active_assignment']??false);$d['has_active_trip']=(bool)($d['has_active_trip']??false);return $d;
    }
    public static function phase(array $a):string { return Assignment::phase($a); }
    public static function dates($from,$to):array {
        foreach([$from,$to] as $date)if($date!==''&&(!is_string($date)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date)||!checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4))))throw new DomainException('Ngày lọc không hợp lệ.',422);
        if($from!==''&&$to!==''&&$from>$to)throw new DomainException('Ngày bắt đầu phải trước hoặc bằng ngày kết thúc.',422);
        return [$from,$to];
    }
    public function schedule($driverId,$from='',$to=''):array {
        self::dates($from,$to);
        $sql='SELECT a.assignment_id,a.trip_id,a.status,a.accepted_at,a.assigned_at,a.completed_at,a.cancelled_at,a.notes,t.status AS trip_status,t.start_time,t.actual_started_at,t.end_time,t.start_location,t.end_location,v.license_plate,EXISTS(SELECT 1 FROM assignment_requests r WHERE r.assignment_id=a.assignment_id AND r.request_status=\'pending\') AS has_pending_request,(SELECT COUNT(*) FROM trip_orders x WHERE x.trip_id=a.trip_id) AS order_count FROM assignments a LEFT JOIN trips t ON t.id=a.trip_id LEFT JOIN vehicles v ON v.vehicle_id=a.vehicle_id WHERE a.driver_id=?';$p=[$driverId];
        if($from!==''){$sql.=' AND t.start_time>=?';$p[]=$from.' 00:00:00';}
        if($to!==''){$sql.=' AND t.start_time<?';$p[]=(new DateTimeImmutable($to))->modify('+1 day')->format('Y-m-d').' 00:00:00';}
        $rows=$this->rows($sql.' ORDER BY t.start_time DESC,a.assignment_id DESC',$p);
        foreach($rows as &$a){$a['assignment_id']=(string)$a['assignment_id'];$a['trip_id']=(string)$a['trip_id'];$a['order_count']=(int)$a['order_count'];$a['has_pending_request']=(bool)$a['has_pending_request'];$a['phase']=self::phase($a);}return $rows;
    }
    public function clock():DateTimeImmutable {
        // Use the database wall clock for DATE/DATETIME statistics, not the PHP host timezone.
        $value=$this->rows("SELECT DATE_FORMAT(CURRENT_TIMESTAMP, '%Y-%m-%d %H:%i:%s') AS server_now")[0]['server_now']??null;
        if(!is_string($value))throw new RuntimeException('Database clock unavailable');
        return new DateTimeImmutable($value,new DateTimeZone('UTC'));
    }
    public static function summary(array $rows,DateTimeImmutable $now):array {
        $today=$now->format('Y-m-d');$week=$now->modify('monday this week')->format('Y-m-d');$weekEnd=$now->modify('monday next week')->format('Y-m-d');
        $next=[];$running=[];$pending=[];$todayCount=0;$completed=0;
        foreach($rows as $a){$phase=self::phase($a);if($phase==='running')$running[]=$a;if($phase==='pending')$pending[]=$a;
            if(in_array($phase,['pending','accepted'],true)&&!empty($a['start_time'])&&$a['start_time']>=$now->format('Y-m-d H:i:s'))$next[]=$a;
            if($phase!=='cancelled'&&substr($a['start_time']??'',0,10)===$today)$todayCount++;
            $end=substr($a['completed_at']??'',0,10);if($phase==='completed'&&$end>=$week&&$end<$weekEnd)$completed++;
        }
        usort($next,fn($a,$b)=>strcmp($a['start_time'],$b['start_time']));return ['next'=>$next[0]??null,'running'=>$running,'today_count'=>$todayCount,'completed_week'=>$completed,'pending'=>$pending];
    }
    public static function profileData(array $data):array {
        if(array_diff(array_keys($data),['full_name','phone','address']))throw new DomainException('Chỉ được sửa họ tên, điện thoại và địa chỉ.',422);
        foreach(['full_name','phone','address'] as $key)if(!array_key_exists($key,$data)||(!is_string($data[$key])&& !($key==='address'&&$data[$key]===null)))throw new DomainException('Thông tin hồ sơ không hợp lệ.',422);
        $name=trim($data['full_name']);$phone=trim($data['phone']);$address=trim($data['address']??'');
        if($name===''||mb_strlen($name)>150||preg_match('/[\x00-\x1F\x7F]/u',$name))throw new DomainException('Họ tên phải từ 1 đến 150 ký tự.',422);
        if(!preg_match('/^\+?[0-9][0-9 .()-]{7,19}$/D',$phone))throw new DomainException('Số điện thoại không hợp lệ.',422);
        if(mb_strlen($address)>255||preg_match('/[\x00-\x1F\x7F]/u',$address))throw new DomainException('Địa chỉ không hợp lệ hoặc vượt 255 ký tự.',422);
        return ['full_name'=>$name,'phone'=>$phone,'address'=>$address===''?null:$address];
    }
    public function updateProfile($userId,array $data) {
        $data=self::profileData($data);$this->db->beginTransaction();try{$d=$this->identity($userId,true);$this->write('UPDATE drivers SET full_name=?,phone=?,address=? WHERE driver_id=?',[$data['full_name'],$data['phone'],$data['address'],$d['driver_id']]);$r=$this->identity($userId);$this->db->commit();return $r;}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function availability($userId,array $data) {
        if(array_keys($data)!==['status']||!in_array($data['status'],['available','inactive'],true))throw new DomainException('Chỉ được chọn Sẵn sàng hoặc Chưa sẵn sàng.',422);
        $this->db->beginTransaction();try{
            $d=$this->identity($userId,true);
            $active=$this->rows("SELECT assignment_id FROM assignments WHERE driver_id=? AND status='assigned' FOR UPDATE",[$d['driver_id']]);
            $running=$this->rows("SELECT id FROM trips WHERE driver_id=? AND status='IN_PROGRESS' FOR UPDATE",[$d['driver_id']]);
            if($active||$running)throw new DomainException('Trạng thái do phân công/chuyến quyết định; chưa thể đổi trạng thái làm việc.',409);
            $this->write('UPDATE drivers SET status=? WHERE driver_id=?',[$data['status'],$d['driver_id']]);
            $r=$this->identity($userId);$this->db->commit();return $r;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public static function checkAction($action,array $a,array $trip,array $d,array $v):void {
        if(!in_array($action,['accept','start','complete'],true))throw new DomainException('Thao tác không hợp lệ.',422);
        if($a['status']!=='assigned'||$trip['status']==='CANCELLED'||(string)$trip['driver_id']!==(string)$a['driver_id']||(string)$trip['vehicle_id']!==(string)$a['vehicle_id'])throw new DomainException('Phân công đã hủy, kết thúc hoặc được thay đổi. Hãy tải lại lịch chạy.',409);
        $phase=self::phase($a+['trip_status'=>$trip['status']]);
        if(($action==='accept'&&$phase!=='pending')||($action==='start'&&$phase!=='accepted')||($action==='complete'&&($phase!=='running'||empty($a['accepted_at']))))throw new DomainException('Trạng thái hiện tại không cho phép thao tác này.',409);
        if(in_array($action,['accept','start'],true)&&($d['status']!=='available'||$v['status']!=='available'))throw new DomainException('Tài xế hoặc phương tiện chưa sẵn sàng. Vui lòng liên hệ nhân viên.',409);
        if(in_array($action,['accept','start'],true)&&$d['license_expiry']!==null&&$d['license_expiry']<date('Y-m-d'))throw new DomainException('GPLX đã hết hạn. Vui lòng liên hệ nhân viên.',409);

    }
    public function action($userId,$assignmentId,$action) {
        $id=Assignment::id($assignmentId);$this->db->beginTransaction();try{
            // Read-only identity first; recheck under locks before any write.
            $d=$this->identity($userId);
            $a=$this->rows('SELECT * FROM assignments WHERE assignment_id=? AND driver_id=?',[$id,$d['driver_id']])[0]??null;
            if(!$a)throw new DomainException('Không tìm thấy phân công thuộc tài xế đăng nhập.',404);
            $trip=$this->rows('SELECT * FROM trips WHERE id=? FOR UPDATE',[Assignment::id((string)$a['trip_id'],true)])[0]??null;
            if(!$trip)throw new DomainException('Không tìm thấy chuyến của phân công.',409);
            $d=$this->identity($userId,true);
            $v=$this->rows('SELECT * FROM vehicles WHERE vehicle_id=? FOR UPDATE',[$a['vehicle_id']])[0]??null;
            $a=$this->rows('SELECT * FROM assignments WHERE assignment_id=? AND driver_id=? FOR UPDATE',[$id,$d['driver_id']])[0]??null;
            if(!$a||!$v)throw new DomainException('Phân công đã thay đổi. Hãy tải lại lịch chạy.',409);
            $requests=$this->rows("SELECT request_id FROM assignment_requests WHERE assignment_id=? AND request_status='pending' FOR UPDATE",[$id]);
            if($requests)throw new DomainException('Yêu cầu đang chờ nhân viên xử lý; chưa thể chuyển bước tiếp theo. Hãy tải lại lịch chạy sau khi có quyết định.',409);
            self::checkAction($action,$a,$trip,$d,$v);
            if($action==='accept')$this->write('UPDATE assignments SET accepted_at=GREATEST(NOW(),assigned_at) WHERE assignment_id=?',[$id]);
            if($action==='start'){
                // start_time is planned departure, never an actual movement timestamp.
                $this->write("UPDATE trips SET status='IN_PROGRESS',actual_started_at=COALESCE(actual_started_at,NOW()) WHERE id=? AND status='PLANNED'",[$a['trip_id']]);
                $this->write("UPDATE drivers SET status='on_trip' WHERE driver_id=?",[$a['driver_id']]);$this->write("UPDATE vehicles SET status='on_trip' WHERE vehicle_id=?",[$a['vehicle_id']]);
            }
            if($action==='complete'){
                $this->write("UPDATE trips SET status='COMPLETED',end_time=NOW() WHERE id=?",[$a['trip_id']]);
                $this->write("UPDATE assignments SET status='completed',completed_at=GREATEST(NOW(),assigned_at,accepted_at) WHERE assignment_id=?",[$id]);
                foreach(['drivers'=>'driver_id','vehicles'=>'vehicle_id'] as $table=>$key){$busy=$this->rows("SELECT assignment_id FROM assignments WHERE $key=? AND status='assigned' FOR UPDATE",[$a[$key]]);if(!$busy)$this->write("UPDATE $table SET status='available' WHERE $key=? AND status='on_trip'",[$a[$key]]);}
            }
            $result=$this->schedule($d['driver_id']);$this->db->commit();return $result;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
