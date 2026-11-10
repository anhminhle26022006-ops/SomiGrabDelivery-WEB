<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryInput.php';
require_once __DIR__.'/AccessControl.php';

/** Caller owns the transaction; identity is supplied by the shared API kernel. */
final class InteractionService
{
    public function __construct(private PDO $db) {}
    private function rows(string $sql,array $args=[]):array {$q=$this->db->prepare($sql);$q->execute($args);return $q->fetchAll();}
    public static function key(array $in):string {
        $key=DeliveryInput::text($in['client_key']??null,'client_key',100);
        if(!preg_match('/^[A-Za-z0-9_.:-]{8,100}$/D',$key))DeliveryInput::invalid('client_key');return $key;
    }
    private function cursor(array $in):int {return isset($in['before_id'])?DeliveryInput::id($in['before_id']):PHP_INT_MAX;}
    public static function requireConversation(PDO $db,int $user,string $role,int $id,bool $write=false):array {
        if($role!=='SHIPPER'||$write)return AccessControl::requireOrder($db,$user,$role,$id);
        $q=$db->prepare("SELECT o.* FROM orders o WHERE o.order_id=? AND EXISTS(SELECT 1 FROM order_assignments a WHERE a.order_id=o.order_id AND a.shipper_id=? AND a.assignment_status IN ('ACTIVE','COMPLETED','RETURNED'))");$q->execute([$id,$user]);$row=$q->fetch();if(!$row)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');return $row;
    }
    public function orders(int $user,string $role,array $in):array {
        $before=$this->cursor($in);$q=$this->rows("SELECT o.order_id,o.order_code,o.status FROM orders o WHERE o.order_id<? AND ((?='CUSTOMER' AND o.customer_id=?) OR (?='SHIPPER' AND EXISTS(SELECT 1 FROM order_assignments a WHERE a.order_id=o.order_id AND a.shipper_id=? AND a.assignment_status IN ('ACTIVE','COMPLETED','RETURNED')))) ORDER BY o.order_id DESC LIMIT 50",[$before,$role,$user,$role,$user]);return ['items'=>$q,'next_cursor'=>count($q)===50?(string)end($q)['order_id']:null];
    }
    public function chat(int $user,string $role,array $in,bool $write):array {
        $id=DeliveryInput::id($in['order_id']??null,'order_id');
        // Serialize with cancellation/assignment changes using the shared order-first lock.
        $this->rows('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE',[$id]);
        $order=self::requireConversation($this->db,$user,$role,$id,$write&&!isset($in['read_through_id']));
        if($write){
            DeliveryInput::only($in,['order_id','body','client_key','read_through_id','file_ids']);
            if(isset($in['read_through_id'])){
                DeliveryInput::only($in,['order_id','read_through_id']);
                $through=DeliveryInput::id($in['read_through_id']);
                $this->db->prepare('UPDATE chat_messages SET read_at=COALESCE(read_at,now()) WHERE order_id=? AND sender_id<>? AND message_id<=? AND deleted_at IS NULL AND delete_after>now()')->execute([$id,$user,$through]);
                return ['read'=>true];
            }
            $files=$in['file_ids']??[];if(!is_array($files)||count($files)>5||($files!==[]&&array_keys($files)!==range(0,count($files)-1)))DeliveryInput::invalid('file_ids');
            $files=array_map(fn($v)=>DeliveryInput::id($v,'file_ids'),$files);sort($files,SORT_NUMERIC);if(count(array_unique($files))!==count($files))DeliveryInput::invalid('file_ids');
            $body=DeliveryInput::text($in['body']??'','body',4000,!$files)??'';$key=self::key($in);
            $old=$this->rows('SELECT message_id,order_id,body FROM chat_messages WHERE sender_id=? AND client_key=?',[$user,$key])[0]??null;
            if($old){$existing=$this->rows('SELECT file_id FROM private_files WHERE message_id=? ORDER BY file_id',[$old['message_id']]);if((int)$old['order_id']!==$id||$old['body']!==$body||array_map(fn($f)=>(int)$f['file_id'],$existing)!==$files)throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return ['message'=>$old];}
            if(in_array($order['status'],['DELIVERED','RETURNED','CANCELLED'],true))throw new DeliveryApiError(409,'CHAT_CLOSED','This conversation is closed.');
            foreach($files as $file){if(!$this->rows("SELECT file_id FROM private_files WHERE file_id=? AND owner_id=? AND order_id=? AND purpose='CHAT' AND message_id IS NULL AND deleted_at IS NULL AND delete_after>now() FOR UPDATE",[$file,$user,$id]))throw new DeliveryApiError(422,'FILE_NOT_ATTACHABLE','Upload an unused CHAT file for this order.');}
            $message=$this->rows("INSERT INTO chat_messages(order_id,sender_id,body,delete_after,client_key) VALUES(?,?,?,'infinity',?) RETURNING message_id,sender_id,body,sent_at,read_at",[$id,$user,$body,$key])[0];
            foreach($files as $file)$this->db->prepare("UPDATE private_files SET message_id=?,delete_after='infinity' WHERE file_id=?")->execute([$message['message_id'],$file]);
            $recipients=$this->rows("SELECT customer_id AS user_id FROM orders WHERE order_id=? UNION SELECT shipper_id AS user_id FROM order_assignments WHERE order_id=? AND assignment_status='ACTIVE'",[$id,$id]);
            foreach($recipients as $r)if((int)$r['user_id']!==$user)self::notify($this->db,(int)$r['user_id'],$id,'ChatMessage','Tin nhắn mới','New message','Bạn có tin nhắn mới trong đơn hàng.','You have a new order message.','chat:'.$message['message_id']);
            return ['message'=>$message];
        }
        $items=$this->rows('SELECT message_id,sender_id,body,sent_at,read_at FROM chat_messages WHERE order_id=? AND message_id<? AND deleted_at IS NULL AND delete_after>now() ORDER BY message_id DESC LIMIT 50',[$id,$this->cursor($in)]);
        foreach($items as &$item)$item['attachments']=$this->rows('SELECT file_id,original_name,mime_type,byte_size FROM private_files WHERE message_id=? AND deleted_at IS NULL AND delete_after>now() ORDER BY file_id',[$item['message_id']]);unset($item);
        return ['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['message_id']:null];
    }
    public function tickets(int $user,string $role,array $in,bool $write):array {
        if($write){
            DeliveryInput::only($in,['order_id','category','subject','description','client_key']);
            $id=isset($in['order_id'])?DeliveryInput::id($in['order_id']):null;
            if($id!==null){
                if($role==='SHIPPER'){$allowed=$this->rows("SELECT 1 FROM order_assignments WHERE order_id=? AND shipper_id=? AND assignment_status IN ('ACTIVE','COMPLETED','RETURNED')",[$id,$user]);if(!$allowed)throw new DeliveryApiError(404,'ORDER_NOT_FOUND','Order not found.');}
                else AccessControl::requireOrder($this->db,$user,$role,$id);
            }
            $category=DeliveryInput::text($in['category']??null,'category',60);
            if(!in_array($category,['DELIVERY','PAYMENT','ACCOUNT','OTHER'],true))DeliveryInput::invalid('category');
            $subject=DeliveryInput::text($in['subject']??null,'subject',200);$body=DeliveryInput::text($in['description']??null,'description',8000);$key=self::key($in);
            $old=$this->rows('SELECT * FROM support_tickets WHERE opened_by=? AND client_key=?',[$user,$key])[0]??null;
            if($old){if((string)$old['order_id']!==(string)$id||$old['description']!==$body||$old['subject']!==$subject||$old['category']!==$category)throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return ['ticket'=>$old];}
            $ticket=$this->rows('INSERT INTO support_tickets(ticket_code,order_id,opened_by,category,subject,description,client_key) VALUES(?,?,?,?,?,?,?) RETURNING *',['TK-'.strtoupper(bin2hex(random_bytes(10))),$id,$user,$category,$subject,$body,$key])[0];
            $this->db->prepare("INSERT INTO support_ticket_events(ticket_id,new_status,note,changed_by) VALUES(?,'NEW',?,?)")->execute([$ticket['ticket_id'],$body,$user]);return ['ticket'=>$ticket];
        }
        if(isset($in['id'])){
            $ticket=$this->rows('SELECT * FROM support_tickets WHERE ticket_id=? AND opened_by=?',[DeliveryInput::id($in['id']),$user])[0]??null;
            if(!$ticket)throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
            $events=$this->rows('SELECT ticket_event_id,old_status,new_status,note,created_at FROM support_ticket_events WHERE ticket_id=? AND ticket_event_id<? ORDER BY ticket_event_id DESC LIMIT 50',[$ticket['ticket_id'],$this->cursor($in)]);
            return ['ticket'=>$ticket,'events'=>$events,'next_cursor'=>count($events)===50?(string)end($events)['ticket_event_id']:null];
        }
        $items=$this->rows('SELECT ticket_id,ticket_code,order_id,category,subject,status,created_at FROM support_tickets WHERE opened_by=? AND ticket_id<? ORDER BY ticket_id DESC LIMIT 50',[$user,$this->cursor($in)]);
        return ['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['ticket_id']:null];
    }
    public function replies(int $user,string $role,array $in,bool $write):array {
        $id=DeliveryInput::id($in['ticket_id']??null,'ticket_id');$ticket=$this->rows('SELECT * FROM support_tickets WHERE ticket_id=? FOR UPDATE',[$id])[0]??null;
        if($role==='ADMIN')AccessControl::requirePermission($this->db,$user,$role,'support.manage_all');
        if(!$ticket||($role!=='ADMIN'&&(int)$ticket['opened_by']!==$user))throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
        if(!$write){$items=$this->rows('SELECT reply_id,sender_id,sender_role,body,created_at FROM support_replies WHERE ticket_id=? AND reply_id<? ORDER BY reply_id DESC LIMIT 50',[$id,$this->cursor($in)]);if($role==='ADMIN')AuditService::record($this->db,$user,'ADMIN','TICKET_REPLIES_READ','SUPPORT_TICKET',$id);return ['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['reply_id']:null];}
        DeliveryInput::only($in,['ticket_id','body','client_key']);$body=DeliveryInput::text($in['body']??null,'body',8000);$key=self::key($in);
        $old=$this->rows('SELECT * FROM support_replies WHERE sender_id=? AND client_key=?',[$user,$key])[0]??null;
        if($old){if((int)$old['ticket_id']!==$id||$old['body']!==$body)throw new DeliveryApiError(409,'IDEMPOTENCY_CONFLICT','Key already used.');return ['reply'=>$old];}
        if($ticket['status']==='CLOSED')throw new DeliveryApiError(409,'TICKET_CLOSED','Ticket is closed.');
        $reply=$this->rows('INSERT INTO support_replies(ticket_id,sender_id,sender_role,body,client_key) VALUES(?,?,?,?,?) RETURNING *',[$id,$user,$role,$body,$key])[0];
        $this->db->prepare('UPDATE support_tickets SET updated_at=now() WHERE ticket_id=?')->execute([$id]);
        $recipients=$role==='ADMIN'?[['user_id'=>$ticket['opened_by']]]:$this->rows("SELECT DISTINCT u.user_id FROM users u JOIN user_roles ur USING(user_id) JOIN roles r USING(role_id) JOIN role_permissions rp USING(role_id) JOIN permissions p USING(permission_id) WHERE r.role_code='ADMIN' AND p.permission_code='support.manage_all' AND u.account_status='ACTIVE'");
        foreach($recipients as $r)if((int)$r['user_id']!==$user)self::notify($this->db,(int)$r['user_id'],$ticket['order_id']===null?null:(int)$ticket['order_id'],'TicketReply','Phản hồi hỗ trợ mới','New support reply',$ticket['ticket_code'],$ticket['ticket_code'],'reply:'.$reply['reply_id']);
        AuditService::record($this->db,$user,$role,'TICKET_REPLY','SUPPORT_TICKET',$id,[],['reply_id'=>$reply['reply_id']]);return ['reply'=>$reply];
    }
    public function notifications(int $user,array $in,bool $write):array {
        if($write&&isset($in['all'])){
            DeliveryInput::only($in,['all','read','through_id']);if($in['all']!==true||!is_bool($in['read']??null))DeliveryInput::invalid('all/read');$through=DeliveryInput::id($in['through_id']??null);
            $q=$this->db->prepare('UPDATE notifications SET read_at=CASE WHEN ?::boolean THEN now() ELSE NULL END WHERE user_id=? AND notification_id<=?');$q->execute([$in['read']?'true':'false',$user,$through]);return ['updated_count'=>$q->rowCount()];
        }
        if($write){DeliveryInput::only($in,['notification_id','read']);$id=DeliveryInput::id($in['notification_id']??null);if(!is_bool($in['read']??null))DeliveryInput::invalid('read');
            $r=$this->rows('UPDATE notifications SET read_at=CASE WHEN ?::boolean THEN now() ELSE NULL END WHERE notification_id=? AND user_id=? RETURNING notification_id,read_at',[$in['read']?'true':'false',$id,$user]);
            if(!$r)throw new DeliveryApiError(404,'NOTIFICATION_NOT_FOUND','Notification not found.');return ['notification'=>$r[0]];}
        $items=$this->rows("SELECT notification_id,order_id,notification_type,title_vi,title_en,message_vi,message_en,read_at,created_at FROM notifications WHERE user_id=? AND notification_id<? ORDER BY notification_id DESC LIMIT 50",[$user,$this->cursor($in)]);
        return ['items'=>$items,'unread_count'=>(int)$this->rows('SELECT count(*) AS n FROM notifications WHERE user_id=? AND read_at IS NULL',[$user])[0]['n'],'next_cursor'=>count($items)===50?(string)end($items)['notification_id']:null];
    }
    public static function notify(PDO $db,int $user,?int $order,string $type,string $vi,string $en,string $bodyVi,string $bodyEn,string $key):void {
        $db->prepare("INSERT INTO notifications(user_id,order_id,notification_type,title_vi,title_en,message_vi,message_en,in_app_status,email_status,dedup_key) VALUES(?,?,?,?,?,?,?,'SENT','SKIPPED',?) ON CONFLICT(user_id,dedup_key) DO NOTHING")->execute([$user,$order,$type,$vi,$en,$bodyVi,$bodyEn,$key]);
    }
}
