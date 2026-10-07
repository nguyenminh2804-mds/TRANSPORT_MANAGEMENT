<?php
require_once __DIR__ . '/../../config/database.php';
class Assignment
{
    private $db;
    public function __construct(?PDO $db=null) { $this->db=$db??new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); }
    public static function id($id, bool $trip=false): string {
        if ((!is_string($id)&&!is_int($id))||!preg_match('/^[1-9][0-9]{0,19}$/D',(string)$id)||strlen((string)$id)>strlen($trip?'2147483647':'18446744073709551615')||(strlen((string)$id)===strlen($trip?'2147483647':'18446744073709551615')&&strcmp((string)$id,$trip?'2147483647':'18446744073709551615')>0)) throw new DomainException('ID không hợp lệ hoặc vượt giới hạn.',422);
        return (string)$id;
    }
    public static function validate(array $data): array {
        $out=[];foreach(['trip_id','driver_id','vehicle_id'] as $k)$out[$k]=self::id($data[$k]??null,$k==='trip_id');
        $notes=$data['notes']??null;if($notes!==null&&(!is_string($notes)||strlen($notes)>65535||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$notes)))throw new DomainException('Ghi chú không hợp lệ hoặc vượt 65535 byte.',422);
        $out['notes']=$notes===null||trim($notes)===''?null:trim($notes);return $out;
    }
    private function rows(string $sql,array $params=[]):array { $s=$this->db->prepare($sql);$s->execute($params);return $s->fetchAll(); }
    private function write(string $sql,array $params=[]):void { $s=$this->db->prepare($sql);$s->execute($params); }
    private function base():string { return 'SELECT a.assignment_id,a.trip_id,a.driver_id,a.vehicle_id,a.assigned_by,a.assigned_at,a.completed_at,a.cancelled_at,a.status,a.notes,t.start_location,t.end_location,t.start_time,t.end_time,t.status AS trip_status,d.driver_code,d.full_name AS driver_name,v.license_plate,u.full_name AS assigned_by_name FROM assignments a LEFT JOIN trips t ON t.id=a.trip_id LEFT JOIN drivers d ON d.driver_id=a.driver_id LEFT JOIN vehicles v ON v.vehicle_id=a.vehicle_id LEFT JOIN users u ON u.id=a.assigned_by'; }
    private function dto(array $rows):array {foreach($rows as &$r)foreach(['assignment_id','trip_id','driver_id','vehicle_id','assigned_by'] as $k)$r[$k]=$r[$k]===null?null:(string)$r[$k];return $rows;}
    public function manager($id,bool $lock=false):bool {return (bool)$this->rows("SELECT id FROM users WHERE id=? AND role IN ('ADMIN','STAFF') AND status=1".($lock?' FOR UPDATE':''),[$id]);}
    public function all(string $search='',string $status=''):array {
        $sql=$this->base().' WHERE 1=1';$p=[];
        if($search!==''){$sql.=" AND (d.full_name LIKE ? ESCAPE '!' OR d.driver_code LIKE ? ESCAPE '!' OR v.license_plate LIKE ? ESCAPE '!' OR t.start_location LIKE ? ESCAPE '!' OR t.end_location LIKE ? ESCAPE '!' OR CAST(a.trip_id AS CHAR) LIKE ? ESCAPE '!')";$q='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$p=array_fill(0,6,$q);}
        if($status!==''){$sql.=' AND a.status=?';$p[]=$status;}return $this->dto($this->rows($sql.' ORDER BY a.assignment_id DESC',$p));
    }
    public function find(string $id) {return $this->dto($this->rows($this->base().' WHERE a.assignment_id=?',[$id]))[0]??false;}
    public function options():array {
        $result=['trips'=>$this->rows("SELECT t.id,t.start_location,t.end_location,t.start_time,t.status FROM trips t WHERE t.status IN ('PLANNED','IN_PROGRESS') ORDER BY t.id DESC"),
        'drivers'=>$this->rows("SELECT d.driver_id,d.driver_code,d.full_name,d.status,d.license_expiry FROM drivers d WHERE d.status IN ('available','on_trip') ORDER BY d.full_name"),
        'vehicles'=>$this->rows("SELECT vehicle_id,license_plate,vehicle_type,capacity,status FROM vehicles WHERE status IN ('available','on_trip') ORDER BY license_plate"),
        'active'=>$this->rows("SELECT assignment_id,trip_id,driver_id,vehicle_id FROM assignments WHERE status='assigned'")];
        foreach($result as &$rows)foreach($rows as &$row)foreach(['id','assignment_id','trip_id','driver_id','vehicle_id'] as $key)if(isset($row[$key]))$row[$key]=(string)$row[$key];
        return $result;
    }
    public static function eligible(array $trip,array $driver,array $vehicle,bool $sameDriver=false,bool $sameVehicle=false):void {
        if(!in_array($trip['status'],['PLANNED','IN_PROGRESS'],true))throw new DomainException('Chuyến đã kết thúc/hủy hoặc trạng thái không hợp lệ.',409);
        if($driver['status']!=='available'&&!($sameDriver&&$driver['status']==='on_trip'))throw new DomainException('Tài xế không khả dụng.',409);
        if($vehicle['status']!=='available'&&!($sameVehicle&&$vehicle['status']==='on_trip'))throw new DomainException('Phương tiện không khả dụng.',409);
        if($driver['license_expiry']!==null&&$driver['license_expiry']<date('Y-m-d'))throw new DomainException('GPLX tài xế đã hết hạn.',409);
    }
    private function release(array $a):void {
        foreach(['drivers'=>'driver_id','vehicles'=>'vehicle_id'] as $table=>$key){
            $busy=$this->rows("SELECT assignment_id FROM assignments WHERE $key=? AND status='assigned' FOR UPDATE",[$a[$key]]);
            if(!$busy)$this->write("UPDATE $table SET status='available' WHERE $key=? AND status='on_trip'",[$a[$key]]);
        }
    }
    public function mutate(string $action,string $actor,?array $data=null,?string $id=null) {
        $this->db->beginTransaction();
        try {
            if(!$this->manager($actor,true))throw new DomainException('Tài khoản không còn quyền quản lý phân công.',403);
            $old=null;
            // Read the trip ID, then serialize operations for this trip. Recheck assignment under lock.
            if($id!==null){$old=$this->rows('SELECT * FROM assignments WHERE assignment_id=?',[$id])[0]??null;if(!$old)throw new DomainException('Không tìm thấy phân công.',404);}
            $tripId=$data['trip_id']??self::id((string)$old['trip_id'],true);
            $tripIds=array_unique([$tripId,$old?self::id((string)$old['trip_id'],true):$tripId]);sort($tripIds,SORT_STRING);$lockedTrips=[];
            foreach($tripIds as $key){$lockedTrips[$key]=$this->rows('SELECT * FROM trips WHERE id=? FOR UPDATE',[$key])[0]??null;if(!$lockedTrips[$key])throw new DomainException('Chuyến không tồn tại; không thể cập nhật liên kết.',409);}
            $trip=$lockedTrips[$tripId];
            // Lock all affected resources in stable order, before locking assignments (same order as Driver/Vehicle).
            $driverIds=array_unique(array_filter([$old['driver_id']??null,$data['driver_id']??null]));sort($driverIds,SORT_STRING);
            $vehicleIds=array_unique(array_filter([$old['vehicle_id']??null,$data['vehicle_id']??null]));sort($vehicleIds,SORT_STRING);
            $drivers=[];$vehicles=[];
            foreach($driverIds as $key){$drivers[(string)$key]=$this->rows('SELECT * FROM drivers WHERE driver_id=? FOR UPDATE',[(string)$key])[0]??null;}
            foreach($vehicleIds as $key){$vehicles[(string)$key]=$this->rows('SELECT * FROM vehicles WHERE vehicle_id=? FOR UPDATE',[(string)$key])[0]??null;}
            if($old){$old=$this->rows('SELECT * FROM assignments WHERE assignment_id=? FOR UPDATE',[$id])[0]??null;if(!$old||$old['status']!=='assigned')throw new DomainException('Chỉ phân công đang hiệu lực được đổi/hoàn tất/hủy.',409);}
            if(in_array($action,['complete','cancel'],true)){
                $column=$action==='complete'?'completed_at':'cancelled_at';$status=$action==='complete'?'completed':'cancelled';
                $this->write("UPDATE assignments SET status=?, $column=GREATEST(NOW(),assigned_at) WHERE assignment_id=?",[$status,$id]);$this->release($old);$result=$this->find($id);
            }else{
                $driver=$drivers[$data['driver_id']]??null;$vehicle=$vehicles[$data['vehicle_id']]??null;
                if(!$driver||!$vehicle)throw new DomainException('Tài xế hoặc phương tiện không tồn tại.',422);
                self::eligible($trip,$driver,$vehicle,$old&&(string)$old['driver_id']===$data['driver_id'],$old&&(string)$old['vehicle_id']===$data['vehicle_id']);
                $active=$this->rows("SELECT assignment_id FROM assignments WHERE status='assigned' AND (trip_id=? OR driver_id=? OR vehicle_id=?) FOR UPDATE",[$tripId,$data['driver_id'],$data['vehicle_id']]);
                foreach($active as $a)if(!$old||(string)$a['assignment_id']!==$id)throw new DomainException('Chuyến, tài xế hoặc xe đã có phân công đang hiệu lực.',409);
                if($old)$this->write("UPDATE assignments SET status='cancelled',cancelled_at=GREATEST(NOW(),assigned_at) WHERE assignment_id=?",[$id]);
                $this->write("INSERT INTO assignments (trip_id,driver_id,vehicle_id,assigned_by,assigned_at,status,notes) VALUES (?,?,?,?,NOW(),'assigned',?)",[$tripId,$data['driver_id'],$data['vehicle_id'],$actor,$data['notes']]);$newId=(string)$this->db->lastInsertId();
                $this->write('UPDATE trips SET driver_id=?,vehicle_id=? WHERE id=?',[$data['driver_id'],$data['vehicle_id'],$tripId]);
                $this->write("UPDATE drivers SET status='on_trip' WHERE driver_id=?",[$data['driver_id']]);$this->write("UPDATE vehicles SET status='on_trip' WHERE vehicle_id=?",[$data['vehicle_id']]);
                if($old)$this->release($old);$result=$this->find($newId);
            }
            $this->db->commit();return $result;
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
