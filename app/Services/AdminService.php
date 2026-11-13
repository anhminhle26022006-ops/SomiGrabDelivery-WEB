<?php
declare(strict_types=1);
require_once __DIR__.'/AccessControl.php';
require_once __DIR__.'/ShipperApplicationService.php';

final class AdminService
{
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    private function guard(int $id,string $permission): void {AccessControl::requirePermission($this->db,$id,'ADMIN',$permission);}
    public function users(int $adminId,array $filter=[]): array
    {
        $this->guard($adminId,'account.read');$params=[];$where='TRUE';
        if(isset($filter['role'])){$role=DeliveryInput::text($filter['role'],'role',20);if(!in_array($role,['CUSTOMER','SHIPPER','ADMIN'],true))DeliveryInput::invalid('role');$where.=' AND EXISTS (SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id AND r.role_code=?)';$params[]=$role;}
        if(isset($filter['q'])){$term=DeliveryInput::text($filter['q'],'q',100);$where.=' AND (u.full_name ILIKE ? OR u.email ILIKE ? OR u.phone ILIKE ?)';array_push($params,'%'.$term.'%','%'.$term.'%','%'.$term.'%');}
        if(isset($filter['before_id'])){$where.=' AND u.user_id < ?';$params[]=DeliveryInput::id($filter['before_id']);}
        $q=$this->db->prepare('SELECT u.user_id,u.full_name,u.email,u.phone,u.nationality_code,u.app_language,u.account_status,u.created_at,(SELECT verification_status FROM shipper_profiles WHERE user_id=u.user_id) AS verification_status,(SELECT json_agg(r.role_code) FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id) AS roles FROM users u WHERE '.$where.' ORDER BY u.user_id DESC LIMIT 100');$q->execute($params);$items=$q->fetchAll();foreach($items as &$item)$item['roles']=json_decode($item['roles']??'[]',true);return $items;
    }
    public function accountStatus(int $adminId,int $userId,string $status,string $reason): array
    {
        $this->guard($adminId,'account.suspend');if(!in_array($status,['ACTIVE','SUSPENDED'],true))DeliveryInput::invalid('account_status');$reason=DeliveryInput::text($reason,'reason',1000);
        $this->db->exec('SELECT pg_advisory_xact_lock(20261106,10)');
        $q=$this->db->prepare('SELECT user_id,account_status,email_verified_at,phone_verified_at FROM users WHERE user_id=? FOR UPDATE');$q->execute([$userId]);$old=$q->fetch();
        if(!$old)throw new DeliveryApiError(404,'ACCOUNT_NOT_FOUND','Account not found.');
        if($adminId===$userId)throw new DeliveryApiError(409,'SELF_STATUS_CHANGE_FORBIDDEN','An administrator cannot change their own account status.');
        if($old['account_status']==='CLOSED')throw new DeliveryApiError(409,'ACCOUNT_CLOSED','A closed account cannot be reopened here.');
        if($status==='ACTIVE'&&($old['email_verified_at']===null||$old['phone_verified_at']===null))throw new DeliveryApiError(409,'VERIFICATION_REQUIRED','Both email and phone must be verified before activation.');
        if($status!==$old['account_status']){
            if($status==='SUSPENDED'){
                $q=$this->db->prepare("SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=? AND r.role_code='ADMIN'");$q->execute([$userId]);
                if($q->fetchColumn()){$n=$this->db->query("SELECT count(*) FROM users u WHERE account_status='ACTIVE' AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id AND r.role_code='ADMIN')")->fetchColumn();if((int)$n<=1)throw new DeliveryApiError(409,'LAST_ADMIN_REQUIRED','At least one active administrator must remain.');}
            }
            $this->db->prepare('UPDATE users SET account_status=?,updated_at=now() WHERE user_id=?')->execute([$status,$userId]);
            $this->db->prepare('INSERT INTO account_status_history(user_id,old_status,new_status,reason,changed_by) VALUES (?,?,?,?,?)')->execute([$userId,$old['account_status'],$status,$reason,$adminId]);
            AuditService::record($this->db,$adminId,'ADMIN','ACCOUNT_STATUS_CHANGED','USER',$userId,['account_status'=>$old['account_status']],['account_status'=>$status,'reason'=>$reason]);
        }
        return ['user_id'=>$userId,'account_status'=>$status];
    }
    public static function country(string $code): string
    {
        $codes=array_column(json_decode(file_get_contents(__DIR__.'/../../resources/countries.json'),true),'code');
        if(!in_array($code,$codes,true))DeliveryInput::invalid('nationality_code');return $code;
    }
    public function requestNationality(int $userId,string $country,string $reason): array
    {
        self::country($country);$reason=DeliveryInput::text($reason,'reason',1000);
        $q=$this->db->prepare('INSERT INTO nationality_change_requests(user_id,nationality_code,reason) VALUES (?,?,?) RETURNING *');$q->execute([$userId,$country,$reason]);return $q->fetch();
    }
    public function reviewNationality(int $adminId,int $requestId,string $decision,string $note): array
    {
        $this->guard($adminId,'account.nationality_review');if(!in_array($decision,['APPROVED','REJECTED'],true))DeliveryInput::invalid('decision');$note=DeliveryInput::text($note,'note',1000);
        $q=$this->db->prepare('SELECT * FROM nationality_change_requests WHERE request_id=? FOR UPDATE');$q->execute([$requestId]);$request=$q->fetch();
        if(!$request)throw new DeliveryApiError(404,'REQUEST_NOT_FOUND','Nationality request not found.');
        if($request['status']!=='PENDING')throw new DeliveryApiError(409,'REQUEST_ALREADY_REVIEWED','Request already reviewed.');
        $q=$this->db->prepare('SELECT nationality_code,app_language FROM users WHERE user_id=? FOR UPDATE');$q->execute([$request['user_id']]);$old=$q->fetch();
        if($decision==='APPROVED')$this->db->prepare('UPDATE users SET nationality_code=?,app_language=?,updated_at=now() WHERE user_id=?')->execute([$request['nationality_code'],$request['nationality_code']==='VN'?'vi':'en',$request['user_id']]);
        $this->db->prepare('UPDATE nationality_change_requests SET status=?,review_note=?,reviewed_by=?,reviewed_at=now() WHERE request_id=?')->execute([$decision,$note,$adminId,$requestId]);
        AuditService::record($this->db,$adminId,'ADMIN','NATIONALITY_REQUEST_'.$decision,'USER',(int)$request['user_id'],$old,['nationality_code'=>$request['nationality_code'],'request_id'=>$requestId,'decision'=>$decision,'note'=>$note]);
        return ['request_id'=>$requestId,'status'=>$decision];
    }
    public function reviewShipper(int $adminId,int $userId,string $decision,string $note): array
    {
        $this->guard($adminId,'shipper.verify');if(!in_array($decision,['APPROVED','REJECTED','NEEDS_INFO'],true))DeliveryInput::invalid('decision');$note=DeliveryInput::text($note,'note',1000,$decision!=='APPROVED')??'';
        $q=$this->db->prepare('SELECT verification_status FROM shipper_profiles WHERE user_id=? FOR UPDATE');$q->execute([$userId]);$old=$q->fetchColumn();
        if(!in_array($old,['PENDING','NEEDS_INFO'],true))throw new DeliveryApiError(409,'KYC_NOT_PENDING','KYC application is not pending.');
        $q=$this->db->prepare('SELECT review_status FROM user_identity_documents WHERE user_id=? FOR UPDATE');$q->execute([$userId]);if(!$q->fetchColumn())throw new DeliveryApiError(409,'KYC_IDENTITY_REQUIRED','Identity data is required.');
        if ($decision === 'APPROVED') (new ShipperApplicationService($this->db))->assertReviewable($userId);
        $this->db->prepare('UPDATE shipper_documents SET review_status=?,rejection_reason=?,reviewed_by=?,reviewed_at=now() WHERE shipper_id=? AND deleted_at IS NULL')->execute([$decision,$decision==='APPROVED'?null:$note,$adminId,$userId]);
        $q=$this->db->prepare("UPDATE shipper_profiles SET verification_status=?,availability=FALSE,approved_at=CASE WHEN ? THEN now() ELSE NULL END,approved_by=?,updated_at=now() WHERE user_id=?");$q->execute([$decision,$decision==='APPROVED'?'true':'false',$decision==='APPROVED'?$adminId:null,$userId]);
        $this->db->prepare('UPDATE user_identity_documents SET review_status=?,rejection_reason=?,reviewed_by=?,reviewed_at=now(),updated_at=now() WHERE user_id=?')->execute([$decision,$decision==='APPROVED'?null:$note,$adminId,$userId]);
        AuditService::record($this->db,$adminId,'ADMIN','SHIPPER_APPLICATION_'.$decision,'SHIPPER_PROFILE',$userId,['verification_status'=>$old],['verification_status'=>$decision,'note'=>$note]);
        return ['status'=>$decision];
    }
    public function category(int $adminId,array $input,?int $id=null): array
    {
        $this->guard($adminId,'parcel.manage');DeliveryInput::only($input,['category_code','name_vi','name_en','description','restriction_type','active']);
        $data=[];foreach(['category_code'=>60,'name_vi'=>120,'name_en'=>120]as $key=>$max)$data[$key]=DeliveryInput::text($input[$key]??null,$key,$max);
        if(!preg_match('/^[A-Z0-9_]+$/D',$data['category_code']))DeliveryInput::invalid('category_code');
        $data['description']=DeliveryInput::text($input['description']??null,'description',2000,false);$data['restriction_type']=$input['restriction_type']??null;
        if(!in_array($data['restriction_type'],['ALLOWED','RESTRICTED','PROHIBITED'],true)||!is_bool($input['active']??null))DeliveryInput::invalid('restriction_type/active');$data['active']=$input['active']?'true':'false';
        $old=[];
        if($id!==null){$q=$this->db->prepare('SELECT * FROM parcel_categories WHERE category_id=? FOR UPDATE');$q->execute([$id]);$old=$q->fetch();if(!$old)throw new DeliveryApiError(404,'CATEGORY_NOT_FOUND','Category not found.');}
        if($id===null)$sql='INSERT INTO parcel_categories('.implode(',',array_keys($data)).') VALUES (:'.implode(',:',array_keys($data)).') RETURNING *';
        else{$sql='UPDATE parcel_categories SET '.implode(',',array_map(static fn($k)=>$k.'=:'.$k,array_keys($data))).',updated_at=now() WHERE category_id=:id RETURNING *';$data['id']=$id;}
        $q=$this->db->prepare($sql);$q->execute($data);$row=$q->fetch();AuditService::record($this->db,$adminId,'ADMIN','PARCEL_CATEGORY_SAVED','PARCEL_CATEGORY',(int)$row['category_id'],$old,$row);return $row;
    }
}
