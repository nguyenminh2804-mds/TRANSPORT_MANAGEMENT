<?php
require_once __DIR__.'/DriverPortal.php';
class AssignmentRequest extends DriverPortal {
    public static function reason($reason):string {
        if(!is_string($reason)||trim($reason)===''||mb_strlen($reason)>2000||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$reason))throw new DomainException('Cần nhập lý do hợp lệ, tối đa 2000 ký tự.',422);
        return trim($reason);
    }
    public function manager($actor,$lock=false):void {
        $id=Assignment::id($actor,true);
        if(!$this->rows("SELECT id FROM users WHERE id=? AND role IN ('ADMIN','STAFF') AND status=1".($lock?' FOR UPDATE':''),[$id]))throw new DomainException('Chỉ ADMIN/STAFF đang hoạt động được xử lý yêu cầu.',403);
    }
    public function listing($actor,bool $staff=false,string $status='',string $search=''):array {
        if(!in_array($status,['','pending','approved','rejected'],true))throw new DomainException('Bộ lọc yêu cầu không hợp lệ.',422);
        $driver=null;if($staff)$this->manager($actor);else $driver=$this->identity($actor);
        $sql='SELECT r.*,a.trip_id,d.driver_code,d.full_name AS driver_name,t.start_location,t.end_location,u.full_name AS reviewer_name FROM assignment_requests r JOIN assignments a ON a.assignment_id=r.assignment_id JOIN drivers d ON d.driver_id=r.driver_id LEFT JOIN trips t ON t.id=a.trip_id LEFT JOIN users u ON u.id=r.reviewed_by WHERE 1=1';$p=[];
        if($driver){$sql.=' AND r.driver_id=?';$p[]=$driver['driver_id'];}if($status!==''){$sql.=' AND r.request_status=?';$p[]=$status;}
        if(mb_strlen($search)>150)throw new DomainException('Tìm kiếm tối đa 150 ký tự.',422);
        $search=trim($search);
        if($search!==''){
            $sql.=" AND (d.full_name LIKE ? ESCAPE '!' OR d.driver_code LIKE ? ESCAPE '!' OR CAST(a.trip_id AS CHAR) LIKE ? ESCAPE '!' OR r.reason LIKE ? ESCAPE '!' OR r.review_reason LIKE ? ESCAPE '!' OR t.start_location LIKE ? ESCAPE '!' OR t.end_location LIKE ? ESCAPE '!')";
            $pattern='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
            for($i=0;$i<7;$i++)$p[]=$pattern;
        }
        $rows=$this->rows($sql.' ORDER BY r.requested_at DESC,r.request_id DESC',$p);
        foreach($rows as &$r)foreach(['request_id','assignment_id','driver_id','trip_id','reviewed_by'] as $k)$r[$k]=$r[$k]===null?null:(string)$r[$k];return $rows;
    }
    public static function validateType($type,array $a,array $t):void {
        $allowed=['decline_assignment'=>'pending','cancel_assignment'=>'accepted','cancel_trip'=>'running'];
        if(!is_string($type)||!isset($allowed[$type]))throw new DomainException('Loại yêu cầu không hợp lệ.',422);
        if($a['status']!=='assigned'||Assignment::phase($a+['trip_status'=>$t['status']])!==$allowed[$type]||(string)$t['driver_id']!==(string)$a['driver_id']||(string)$t['vehicle_id']!==(string)$a['vehicle_id'])throw new DomainException('Phân công/chuyến đã thay đổi; yêu cầu không còn phù hợp. Hãy tải lại.',409);
    }
    public function submit($actor,array $data):array {
        if(array_diff(array_keys($data),['assignment_id','request_type','reason']))throw new DomainException('Không được gửi mã tài xế hoặc trường ngoài yêu cầu.',422);
        $id=Assignment::id($data['assignment_id']??null);$reason=self::reason($data['reason']??null);
        $this->db->beginTransaction();try{
            $d=$this->identity($actor);$a=$this->rows('SELECT * FROM assignments WHERE assignment_id=? AND driver_id=?',[$id,$d['driver_id']])[0]??null;
            if(!$a)throw new DomainException('Không tìm thấy phân công thuộc tài xế đăng nhập.',404);
            $t=$this->rows('SELECT * FROM trips WHERE id=? FOR UPDATE',[Assignment::id((string)$a['trip_id'],true)])[0]??null;
            $d=$this->identity($actor,true);
            $a=$this->rows('SELECT * FROM assignments WHERE assignment_id=? AND driver_id=? FOR UPDATE',[$id,$d['driver_id']])[0]??null;
            if(!$t||!$a)throw new DomainException('Phân công/chuyến không còn tồn tại.',409);
            self::validateType($data['request_type']??null,$a,$t);
            if($this->rows("SELECT request_id FROM assignment_requests WHERE assignment_id=? AND request_status='pending' FOR UPDATE",[$id]))throw new DomainException('Phân công đã có yêu cầu đang chờ duyệt.',409);
            $this->write("INSERT INTO assignment_requests (assignment_id,driver_id,request_type,reason,request_status,requested_at) VALUES (?,?,?,?,'pending',NOW())",[$id,$d['driver_id'],$data['request_type'],$reason]);
            $this->db->commit();return ['message'=>'Đã gửi yêu cầu chờ nhân viên duyệt; tài nguyên vẫn được giữ.'];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
    public function review($actor,$id,array $data):array {
        $id=Assignment::id($id);$reason=self::reason($data['review_reason']??null);$decision=$data['decision']??null;
        if(!in_array($decision,['approved','rejected'],true)||array_diff(array_keys($data),['decision','review_reason']))throw new DomainException('Thông tin duyệt không hợp lệ.',422);
        $this->db->beginTransaction();try{
            $this->manager($actor,true);
            $r=$this->rows('SELECT * FROM assignment_requests WHERE request_id=?',[$id])[0]??null;if(!$r)throw new DomainException('Không tìm thấy yêu cầu.',404);
            $a=$this->rows('SELECT * FROM assignments WHERE assignment_id=?',[$r['assignment_id']])[0]??null;if(!$a)throw new DomainException('Phân công không còn tồn tại.',409);
            $t=$this->rows('SELECT * FROM trips WHERE id=? FOR UPDATE',[Assignment::id((string)$a['trip_id'],true)])[0]??null;
            $d=$this->rows('SELECT * FROM drivers WHERE driver_id=? FOR UPDATE',[$a['driver_id']])[0]??null;
            $v=$this->rows('SELECT * FROM vehicles WHERE vehicle_id=? FOR UPDATE',[$a['vehicle_id']])[0]??null;
            $a=$this->rows('SELECT * FROM assignments WHERE assignment_id=? FOR UPDATE',[$r['assignment_id']])[0]??null;
            $r=$this->rows('SELECT * FROM assignment_requests WHERE request_id=? FOR UPDATE',[$id])[0]??null;
            if(!$a||!$r||$r['request_status']!=='pending')throw new DomainException('Yêu cầu đã được xử lý hoặc dữ liệu đã thay đổi.',409);
            if($decision==='approved'){
                if(!$t||!$d||!$v||(string)$r['driver_id']!==(string)$a['driver_id'])throw new DomainException('Phân công/chuyến đã thay đổi.',409);
                self::validateType($r['request_type'],$a,$t);
                // Stop the current assignment, not the trip: it must remain eligible for reassignment.
                // Preserve the old assignment's actual_started_at and notes.
                // Only the current trip clock resets; orders/history/review records remain intact.
                $this->write("UPDATE trips SET status='PLANNED',actual_started_at=NULL WHERE id=?",[$a['trip_id']]);
                $this->write("UPDATE assignments SET status='cancelled',cancelled_at=GREATEST(NOW(),assigned_at) WHERE assignment_id=?",[$a['assignment_id']]);
                foreach(['drivers'=>'driver_id','vehicles'=>'vehicle_id'] as $table=>$key){
                    $active=$this->rows("SELECT assignment_id FROM assignments WHERE $key=? AND status='assigned' FOR UPDATE",[$a[$key]]);
                    $running=$this->rows("SELECT id FROM trips WHERE $key=? AND status='IN_PROGRESS' FOR UPDATE",[$a[$key]]);
                    if(!$active&&!$running)$this->write("UPDATE $table SET status='available' WHERE $key=? AND status='on_trip'",[$a[$key]]);
                }
            }
            $this->write('UPDATE assignment_requests SET request_status=?,reviewed_by=?,reviewed_at=GREATEST(NOW(),requested_at),review_reason=? WHERE request_id=?',[$decision,$actor,$reason,$id]);
            $this->db->commit();return ['message'=>'Đã xử lý yêu cầu.'];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }
}
