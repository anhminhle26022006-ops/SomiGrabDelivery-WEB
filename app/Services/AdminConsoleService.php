<?php
declare(strict_types=1);
require_once __DIR__.'/AdminService.php';
require_once __DIR__.'/CustomerOrderService.php';

/** Live Admin queries and audited mutations; never returns password hashes or encrypted payloads. */
final class AdminConsoleService
{
    public const TICKET_NEXT=['NEW'=>'IN_PROGRESS','IN_PROGRESS'=>'RESOLVED','RESOLVED'=>'CLOSED'];
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    private function guard(int $admin,string $permission):void{AccessControl::requirePermission($this->db,$admin,'ADMIN',$permission);}
    private function rows(string $sql,array $params=[]):array{$q=$this->db->prepare($sql);$q->execute($params);return $q->fetchAll();}
    private function page(string $sql,array $params,string $id):array{
        $items=$this->rows($sql.' LIMIT 51',$params);$more=count($items)>50;if($more)array_pop($items);
        return ['items'=>$items,'next_before_id'=>$more?(string)$items[count($items)-1][$id]:null];
    }
    public function overview(int $admin):array{
        foreach(['account.read','order.read_all','payment.reconcile','support.manage_all','audit.read']as $p)$this->guard($admin,$p);
        $stats=$this->rows("SELECT (SELECT count(*) FROM users) AS accounts,(SELECT count(*) FROM orders) AS orders,
          (SELECT count(*) FROM orders WHERE status NOT IN ('DELIVERED','RETURNED','CANCELLED')) AS active_orders,
          (SELECT count(*) FROM shipper_profiles WHERE verification_status IN ('PENDING','NEEDS_INFO')) AS pending_shippers,
          (SELECT count(*) FROM support_tickets WHERE status IN ('NEW','IN_PROGRESS')) AS open_tickets,
          (SELECT count(*) FROM background_jobs WHERE status='FAILED') AS failed_jobs")[0];
        $money=$this->rows("SELECT currency_code,COALESCE(sum(amount_minor),0) AS collected_minor,count(*) AS payments FROM payments WHERE status IN ('PAID','PARTIALLY_REFUNDED','REFUNDED') GROUP BY currency_code ORDER BY currency_code");
        $days=$this->rows("SELECT d::date AS day,count(o.order_id) AS orders FROM generate_series((now() AT TIME ZONE 'Asia/Ho_Chi_Minh')::date-6,(now() AT TIME ZONE 'Asia/Ho_Chi_Minh')::date,interval '1 day')d LEFT JOIN orders o ON (o.created_at AT TIME ZONE 'Asia/Ho_Chi_Minh')::date=d::date GROUP BY d ORDER BY d");
        return ['stats'=>$stats,'collected'=>$money,'days'=>$days,
            'recent_orders'=>$this->rows('SELECT order_id,order_code,status,currency_code,total_charge_minor,created_at FROM orders ORDER BY order_id DESC LIMIT 6'),
            'recent_audit'=>$this->rows('SELECT audit_id,action,entity_type,entity_id,created_at FROM audit_logs ORDER BY audit_id DESC LIMIT 6')];
    }
    public function accounts(int $admin,array $in):array{
        $this->guard($admin,'account.read');$where='TRUE';$params=[];
        if(isset($in['role'])){if(!in_array($in['role'],['CUSTOMER','SHIPPER','ADMIN'],true))DeliveryInput::invalid('role');$where.=' AND EXISTS(SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id AND r.role_code=?)';$params[]=$in['role'];}
        if(!empty($in['q'])){$term=DeliveryInput::text($in['q'],'q',100);$where.=' AND (u.full_name ILIKE ? OR u.email ILIKE ? OR u.phone ILIKE ?)';array_push($params,'%'.$term.'%','%'.$term.'%','%'.$term.'%');}
        if(!empty($in['status'])){if(!in_array($in['status'],['ACTIVE','SUSPENDED','PENDING_VERIFICATION','CLOSED'],true))DeliveryInput::invalid('status');$where.=' AND u.account_status=?';$params[]=$in['status'];}
        if(!empty($in['verification'])){if(!in_array($in['verification'],['PENDING','NEEDS_INFO','APPROVED','REJECTED'],true))DeliveryInput::invalid('verification');$where.=' AND sp.verification_status=?';$params[]=$in['verification'];}
        if(isset($in['before_id'])){$where.=' AND u.user_id<?';$params[]=DeliveryInput::id($in['before_id']);}
        $data=$this->page("SELECT u.user_id,u.full_name,u.email,u.phone,u.nationality_code,u.account_status,u.created_at,
          sp.verification_status,sp.vehicle_type,sp.vehicle_registration,sp.reliability_score,sp.availability,
          (SELECT json_agg(r.role_code) FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id=u.user_id) AS roles
          FROM users u LEFT JOIN shipper_profiles sp ON sp.user_id=u.user_id WHERE ".$where.' ORDER BY u.user_id DESC',$params,'user_id');
        foreach($data['items']as &$row)$row['roles']=json_decode($row['roles']??'[]',true);unset($row);
        return $data;
    }
    public function account(int $admin,int $id):array{
        $this->guard($admin,'account.read');
        $row=$this->rows('SELECT user_id,full_name,email,phone,nationality_code,date_of_birth,gender,account_status,email_verified_at,phone_verified_at,created_at FROM users WHERE user_id=?',[$id])[0]??null;
        if(!$row)throw new DeliveryApiError(404,'ACCOUNT_NOT_FOUND','Account not found.');
        $row['shipper']=$this->rows('SELECT user_id,vehicle_type,vehicle_registration,verification_status,reliability_score,customer_rating_average,customer_rating_count,availability FROM shipper_profiles WHERE user_id=?',[$id])[0]??null;
        $row['identity']=$this->rows('SELECT document_type,document_country_code,review_status,rejection_reason,reviewed_at FROM user_identity_documents WHERE user_id=?',[$id])[0]??null;
        $row['history']=$this->rows('SELECT old_status,new_status,reason,changed_by,created_at FROM account_status_history WHERE user_id=? ORDER BY history_id DESC LIMIT 50',[$id]);
        AuditService::record($this->db,$admin,'ADMIN','ACCOUNT_READ','USER',$id);return ['account'=>$row];
    }
    public function tickets(int $admin,array $in):array{
        $this->guard($admin,'support.manage_all');$where='TRUE';$params=[];
        if(isset($in['id'])){
            $id=DeliveryInput::id($in['id']);$ticket=$this->rows('SELECT t.*,o.order_code,u.full_name AS reporter_name FROM support_tickets t LEFT JOIN orders o USING(order_id) JOIN users u ON u.user_id=t.opened_by WHERE ticket_id=?',[$id])[0]??null;
            if(!$ticket)throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
            $ticket['events']=$this->rows('SELECT old_status,new_status,note,changed_by,created_at FROM support_ticket_events WHERE ticket_id=? ORDER BY ticket_event_id DESC LIMIT 100',[$id]);
            AuditService::record($this->db,$admin,'ADMIN','SUPPORT_TICKET_READ','SUPPORT_TICKET',$id);return ['ticket'=>$ticket];
        }
        if(!empty($in['status'])){if(!in_array($in['status'],['NEW','IN_PROGRESS','RESOLVED','CLOSED'],true))DeliveryInput::invalid('status');$where.=' AND t.status=?';$params[]=$in['status'];}
        if(!empty($in['q'])){$where.=' AND (t.ticket_code ILIKE ? OR t.subject ILIKE ? OR o.order_code ILIKE ?)';$q='%'.DeliveryInput::text($in['q'],'q',100).'%';array_push($params,$q,$q,$q);}
        if(isset($in['before_id'])){$where.=' AND t.ticket_id<?';$params[]=DeliveryInput::id($in['before_id']);}
        return $this->page('SELECT t.ticket_id,t.ticket_code,t.order_id,t.category,t.subject,t.status,t.created_at,o.order_code,u.full_name AS reporter_name FROM support_tickets t LEFT JOIN orders o USING(order_id) JOIN users u ON u.user_id=t.opened_by WHERE '.$where.' ORDER BY t.ticket_id DESC',$params,'ticket_id');
    }
    public function ticketStatus(int $admin,array $in):array{
        $this->guard($admin,'support.manage_all');DeliveryInput::only($in,['ticket_id','status','note']);
        $id=DeliveryInput::id($in['ticket_id']??null);$status=DeliveryInput::text($in['status']??null,'status',20);$note=DeliveryInput::text($in['note']??null,'note',2000);
        $old=$this->rows('SELECT ticket_id,ticket_code,order_id,opened_by,status FROM support_tickets WHERE ticket_id=? FOR UPDATE',[$id])[0]??null;
        if(!$old)throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
        if((self::TICKET_NEXT[$old['status']]??null)!==$status)throw new DeliveryApiError(409,'TICKET_STATE_CONFLICT','Refresh the ticket before changing its status.');
        $q=$this->db->prepare("UPDATE support_tickets SET status=?,resolution=CASE WHEN ?='RESOLVED' THEN ? ELSE resolution END,resolved_by=CASE WHEN ?='RESOLVED' THEN ? ELSE resolved_by END,resolved_at=CASE WHEN ?='RESOLVED' THEN now() ELSE resolved_at END,closed_at=CASE WHEN ?='CLOSED' THEN now() ELSE closed_at END,updated_at=now() WHERE ticket_id=?");
        $q->execute([$status,$status,$note,$status,$admin,$status,$status,$id]);
        $this->db->prepare('INSERT INTO support_ticket_events(ticket_id,old_status,new_status,note,changed_by) VALUES(?,?,?,?,?)')->execute([$id,$old['status'],$status,$note,$admin]);
        if($status==='CLOSED')$this->db->prepare("UPDATE support_attachments SET delete_after=now()+interval '3 months' WHERE ticket_id=? AND deleted_at IS NULL")->execute([$id]);
        $this->db->prepare("INSERT INTO notifications(user_id,order_id,notification_type,title_vi,message_vi,title_en,message_en,in_app_status,email_status) VALUES(?,?,'TICKET_STATUS_CHANGED',?,?,?,?,'SENT','SKIPPED')")->execute([$old['opened_by'],$old['order_id'],'Cập nhật ticket hỗ trợ',$old['ticket_code'].' → '.$status,'Support ticket updated',$old['ticket_code'].' → '.$status]);
        AuditService::record($this->db,$admin,'ADMIN','SUPPORT_TICKET_UPDATED','SUPPORT_TICKET',$id,$old,['status'=>$status,'note'=>$note]);return ['ticket_id'=>$id,'status'=>$status];
    }
    public function chat(int $admin,int $ticketId):array{
        $this->guard($admin,'support.manage_all');$ticket=$this->rows('SELECT order_id FROM support_tickets WHERE ticket_id=?',[$ticketId])[0]??null;
        if(!$ticket)throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
        if(!$ticket['order_id'])throw new DeliveryApiError(409,'ORDER_TICKET_REQUIRED','Account tickets have no order conversation.');
        AccessControl::auditAdminChatRead($this->db,$admin,$ticketId,(int)$ticket['order_id']);
        return ['items'=>$this->rows("SELECT m.message_id,m.sender_id,u.full_name AS sender_name,m.message_type,m.body,m.sent_at FROM chat_messages m JOIN users u ON u.user_id=m.sender_id WHERE m.order_id=? AND m.deleted_at IS NULL AND m.delete_after>now() ORDER BY m.message_id DESC LIMIT 100",[$ticket['order_id']])];
    }
    public function reviews(int $admin,array $in):array{
        $this->guard($admin,'order.read_all');$params=[];$where='TRUE';
        if(isset($in['before_id'])){$where.=' AND r.review_id<?';$params[]=DeliveryInput::id($in['before_id']);}
        if(!empty($in['rating'])){$rating=DeliveryInput::id($in['rating'],'rating');if($rating>5)DeliveryInput::invalid('rating');$where.=' AND r.rating=?';$params[]=$rating;}
        return $this->page('SELECT r.review_id,r.order_id,r.rating,r.comment,r.created_at,o.order_code,c.full_name AS customer_name,s.full_name AS shipper_name FROM order_reviews r JOIN orders o USING(order_id) JOIN users c ON c.user_id=r.customer_id JOIN users s ON s.user_id=r.shipper_id WHERE '.$where.' ORDER BY r.review_id DESC',$params,'review_id');
    }
    public function notifications(int $admin,array $in):array{
        $this->guard($admin,'account.read');$params=[$admin];$where='user_id=?';
        if(isset($in['before_id'])){$where.=' AND notification_id<?';$params[]=DeliveryInput::id($in['before_id']);}
        return $this->page('SELECT notification_id,order_id,title_vi,message_vi,title_en,message_en,notification_type,read_at,created_at FROM notifications WHERE '.$where.' ORDER BY notification_id DESC',$params,'notification_id');
    }
    public function markRead(int $admin,int $id):array{
        $this->guard($admin,'account.read');$q=$this->db->prepare('UPDATE notifications SET read_at=COALESCE(read_at,now()) WHERE notification_id=? AND user_id=? RETURNING notification_id');$q->execute([$id,$admin]);
        if(!$q->fetchColumn())throw new DeliveryApiError(404,'NOTIFICATION_NOT_FOUND','Notification not found.');return ['read'=>true];
    }
    public function restoreScore(int $admin,array $in):array {
        $this->guard($admin,'shipper.verify');DeliveryInput::only($in,['shipper_id','score','reason']);
        $id=DeliveryInput::id($in['shipper_id']??null);$score=DeliveryInput::decimal($in['score']??null,'score',2,'0','99999.99');$reason=DeliveryInput::text($in['reason']??null,'reason',1000);
        $old=$this->rows('SELECT reliability_score FROM shipper_profiles WHERE user_id=? FOR UPDATE',[$id])[0]??null;
        if(!$old)throw new DeliveryApiError(404,'SHIPPER_NOT_FOUND','Shipper not found.');
        if(bccomp($score,(string)$old['reliability_score'],2)<=0)throw new DeliveryApiError(422,'SCORE_MUST_INCREASE','Restoration must increase reliability.');
        $policies=$this->rows('SELECT lock_threshold,max_score FROM shipper_score_policies WHERE effective_from<=now() AND (effective_until IS NULL OR effective_until>now())');
        if(count($policies)!==1||bccomp($score,(string)$policies[0]['lock_threshold'],2)<0||bccomp($score,(string)$policies[0]['max_score'],2)>0)throw new DeliveryApiError(422,'SCORE_POLICY_REQUIRED','Restore within the configured threshold and maximum score.');
        $this->db->prepare('UPDATE shipper_profiles SET reliability_score=?,availability=FALSE,updated_at=now() WHERE user_id=?')->execute([$score,$id]);
        $this->db->prepare("INSERT INTO shipper_score_events(shipper_id,event_type,points_delta,score_after,reason,actor_id) VALUES(?,'ADMIN_ADJUSTMENT',?,?,?,?)")->execute([$id,bcsub($score,(string)$old['reliability_score'],2),$score,$reason,$admin]);
        AuditService::record($this->db,$admin,'ADMIN','SHIPPER_RELIABILITY_RESTORED','SHIPPER_PROFILE',$id,$old,['reliability_score'=>$score,'reason'=>$reason]);return ['shipper_id'=>$id,'reliability_score'=>$score];
    }
    public function cancel(int $admin,int $id,string $reason):array{
        $this->guard($admin,'order.cancel_admin');$reason=DeliveryInput::text($reason,'reason',1000);$order=OrderLifecycleService::lock($this->db,$id);
        if(!in_array($order['status'],['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED'],true))throw new DeliveryApiError(409,'ORDER_NOT_CANCELLABLE','Only orders before pickup can be cancelled here.');
        if($this->rows('SELECT 1 FROM deliveries d JOIN order_assignments a USING(assignment_id) WHERE a.order_id=? AND d.picked_up_at IS NOT NULL LIMIT 1',[$id]))throw new DeliveryApiError(409,'ORDER_NOT_CANCELLABLE','Pickup evidence already exists.');
        $snapshot=json_decode($order['pricing_snapshot'],true,512,JSON_THROW_ON_ERROR);$fee=$order['status']==='NO_SHIPPER_FOUND'?'0':(string)($snapshot['cancellation_fees'][$order['status']]??'0');
        $q=$this->db->prepare("UPDATE orders SET status='CANCELLED',cancellation_fee_minor=?,cancelled_at=now(),closed_at=now(),updated_at=now() WHERE order_id=?");$q->execute([$fee,$id]);
        $this->db->prepare("UPDATE order_match_offers SET status='CANCELLED',responded_at=now() WHERE order_id=? AND status='PENDING'")->execute([$id]);
        $this->db->prepare("UPDATE order_assignments SET assignment_status='ADMIN_CANCELLED',ended_at=now(),ended_reason='ADMIN_CANCELLATION' WHERE order_id=? AND assignment_status='ACTIVE'")->execute([$id]);
        $payment=$this->rows('SELECT status FROM payments WHERE order_id=? FOR UPDATE',[$id])[0]??[];$paid=$order['paid_at']!==null||($payment['status']??'')==='PAID';
        OrderLifecycleService::history($this->db,$id,$order['status'],'CANCELLED',$admin,'ADMIN',$reason);
        OrderLifecycleService::event($this->db,$order,'OrderCancelled','cancelled:'.$id,['reason'=>'CUSTOMER_CANCELLATION','admin_id'=>(string)$admin,'refund_required_hint'=>$paid]);
        if($paid)OrderLifecycleService::event($this->db,$order,'RefundRequested','refund:cancel:'.$id,['reason'=>'CUSTOMER_CANCELLATION','deducted_fee_minor'=>$fee]);
        AuditService::record($this->db,$admin,'ADMIN','ORDER_CANCELLED','ORDER',$id,['status'=>$order['status']],['status'=>'CANCELLED','reason'=>$reason,'cancellation_fee_minor'=>$fee]);return ['order_id'=>$id,'status'=>'CANCELLED'];
    }
}
