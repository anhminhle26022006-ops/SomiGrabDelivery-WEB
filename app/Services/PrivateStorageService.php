<?php
declare(strict_types=1);
require_once __DIR__.'/InteractionService.php';

/** Optional private filesystem adapter. A shared persistent volume is required on hosting. */
final class PrivateStorageService
{
    public function __construct(private PDO $db) {}
    private static function root():string {
        $root=realpath(getenv('SOMI_STORAGE_ROOT')?:'');$project=realpath(__DIR__.'/../..');
        if(!$root||!getenv('SOMI_STORAGE_ROOT')||str_starts_with(strtolower(str_replace('\\','/',$root)).'/',strtolower(str_replace('\\','/',$project)).'/'))throw new DeliveryApiError(503,'STORAGE_NOT_CONFIGURED','Configure a private storage directory outside the web project.');
        return $root;
    }
    private static function secret():string {
        $key=getenv('SOMI_STORAGE_SIGNING_KEY')?:'';if(strlen($key)<32)throw new DeliveryApiError(503,'STORAGE_NOT_CONFIGURED','Storage signing key is missing.');return $key;
    }
    private function row(int $id):array {
        $q=$this->db->prepare('SELECT * FROM private_files WHERE file_id=? AND deleted_at IS NULL AND delete_after>now()');$q->execute([$id]);$r=$q->fetch();if(!$r)throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');return $r;
    }
    private function authorize(array $file,int $user,string $role):void {
        if($role==='ADMIN'){
            AccessControl::requirePermission($this->db,$user,'ADMIN','storage.read_support');
            if($file['purpose']==='KYC')AccessControl::requirePermission($this->db,$user,'ADMIN','shipper.verify');
            else {
                AccessControl::requirePermission($this->db,$user,'ADMIN','support.manage_all');
                if($file['ticket_id']){$ticket=(int)$file['ticket_id'];}
                else{$q=$this->db->prepare('SELECT ticket_id FROM support_tickets WHERE order_id=? ORDER BY ticket_id DESC LIMIT 1');$q->execute([$file['order_id']]);$ticket=(int)$q->fetchColumn();if(!$ticket)throw new DeliveryApiError(403,'RELATED_TICKET_REQUIRED','A related support ticket is required.');}
                if($file['purpose']==='CHAT')AccessControl::auditAdminChatRead($this->db,$user,$ticket,(int)$file['order_id']);
            }
            AuditService::record($this->db,$user,'ADMIN','PRIVATE_FILE_READ','PRIVATE_FILE',(int)$file['file_id']);return;
        }
        if($file['purpose']==='CHAT'){
            if((int)$file['owner_id']===$user)return;
            if(!$file['message_id'])throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');
            InteractionService::requireConversation($this->db,$user,$role,(int)$file['order_id']);return;
        }
        if((int)$file['owner_id']!==$user)throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');
    }
    public function listing(int $user,array $in):array {
        if(isset($in['ticket_id'])){
            AccessControl::requirePermission($this->db,$user,'ADMIN','storage.read_support');AccessControl::requirePermission($this->db,$user,'ADMIN','support.manage_all');
            $ticket=DeliveryInput::id($in['ticket_id']);$q=$this->db->prepare('SELECT order_id FROM support_tickets WHERE ticket_id=?');$q->execute([$ticket]);$row=$q->fetch();if(!$row)throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
            $q=$this->db->prepare("SELECT file_id,purpose,original_name,mime_type,byte_size FROM private_files WHERE (ticket_id=? OR (order_id=? AND purpose IN ('CHAT','EVIDENCE'))) AND deleted_at IS NULL AND delete_after>now() ORDER BY file_id DESC LIMIT 50");$q->execute([$ticket,$row['order_id']]);
            AuditService::record($this->db,$user,'ADMIN','TICKET_FILES_LIST','SUPPORT_TICKET',$ticket);return ['items'=>$q->fetchAll()];
        }
        $before=isset($in['before_id'])?DeliveryInput::id($in['before_id']):PHP_INT_MAX;
        $q=$this->db->prepare('SELECT file_id,order_id,ticket_id,purpose,original_name,mime_type,byte_size,created_at FROM private_files WHERE owner_id=? AND file_id<? AND deleted_at IS NULL AND delete_after>now() ORDER BY file_id DESC LIMIT 50');$q->execute([$user,$before]);$items=$q->fetchAll();return ['items'=>$items,'next_cursor'=>count($items)===50?(string)end($items)['file_id']:null];
    }
    public function upload(int $user,string $role,array $in,array $file):array {
        DeliveryInput::only($in,['purpose','order_id','ticket_id']);$purpose=DeliveryInput::text($in['purpose']??null,'purpose',20);
        if(!in_array($purpose,['CHAT','TICKET','KYC','EVIDENCE'],true))DeliveryInput::invalid('purpose');$order=null;$ticket=null;
        if(in_array($purpose,['CHAT','EVIDENCE'],true)){
            $order=DeliveryInput::id($in['order_id']??null);$q=$this->db->prepare('SELECT order_id FROM orders WHERE order_id=? FOR UPDATE');$q->execute([$order]);$o=AccessControl::requireOrder($this->db,$user,$role,$order);
            if(in_array($o['status'],['DELIVERED','RETURNED','CANCELLED'],true))throw new DeliveryApiError(409,'ORDER_CLOSED','Upload before closing the order.');
            if($purpose==='EVIDENCE'&&$role!=='SHIPPER')throw new DeliveryApiError(403,'SHIPPER_REQUIRED','Shipper required.');
        }elseif($purpose==='TICKET'){
            $ticket=DeliveryInput::id($in['ticket_id']??null);$q=$this->db->prepare("SELECT 1 FROM support_tickets WHERE ticket_id=? AND opened_by=? AND status<>'CLOSED'");$q->execute([$ticket,$user]);if(!$q->fetchColumn())throw new DeliveryApiError(404,'TICKET_NOT_FOUND','Ticket not found.');
        }elseif($role!=='SHIPPER')throw new DeliveryApiError(403,'SHIPPER_REQUIRED','Shipper required.');
        $root=self::root();self::secret();
        if(in_array($file['error']??null,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new DeliveryApiError(422,'FILE_SIZE_INVALID','Maximum file size is 5 MiB (also check PHP upload limits).');
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??''))DeliveryInput::invalid('file');
        $size=filesize($file['tmp_name']);if($size<1||$size>5*1024*1024)throw new DeliveryApiError(422,'FILE_SIZE_INVALID','Maximum file size is 5 MiB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if(!in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true))throw new DeliveryApiError(422,'FILE_TYPE_INVALID','Only JPEG, PNG, WebP and PDF are supported.');
        $name=DeliveryInput::text(basename((string)$file['name']),'name',255);$key=bin2hex(random_bytes(32));$hash=hash_file('sha256',$file['tmp_name']);$path=$root.DIRECTORY_SEPARATOR.$key;
        if(!move_uploaded_file($file['tmp_name'],$path))throw new RuntimeException('Private upload failed.');chmod($path,0600);
        try{$q=$this->db->prepare("INSERT INTO private_files(owner_id,order_id,ticket_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256,delete_after) VALUES(?,?,?,?,?,?,?,?,?,CASE WHEN ?='CHAT' THEN now()+interval '1 day' ELSE 'infinity'::timestamptz END) RETURNING file_id");$q->execute([$user,$order,$ticket,$purpose,$key,$name,$mime,$size,$hash,$purpose]);return ['file_id'=>(string)$q->fetchColumn(),'mime_type'=>$mime,'byte_size'=>$size,'checksum_sha256'=>$hash];}catch(Throwable $e){unlink($path);throw $e;}
    }
    /** Person 2 calls this in the delivery/KYC transaction before persisting a file reference. */
    public function reference(int $user,string $role,array $in):array {
        $file=$this->row(DeliveryInput::id($in['reference_file_id']??null));$purpose=DeliveryInput::text($in['purpose']??null,'purpose',20);
        if((int)$file['owner_id']!==$user||$file['purpose']!==$purpose||!in_array($purpose,['KYC','EVIDENCE'],true)||$role!=='SHIPPER')throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');
        if($purpose==='EVIDENCE')AccessControl::requireOrder($this->db,$user,$role,DeliveryInput::id($in['order_id']??null));
        if($purpose==='EVIDENCE'&&(int)$file['order_id']!==DeliveryInput::id($in['order_id']??null))throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');
        return ['file'=>array_intersect_key($file,array_flip(['file_id','owner_id','order_id','purpose','object_key','mime_type','byte_size','checksum_sha256','delete_after']))];
    }
    public function discard(int $user,int $id):array {
        $q=$this->db->prepare('SELECT file_id FROM private_files WHERE file_id=? FOR UPDATE');$q->execute([$id]);
        $file=$this->row($id);if((int)$file['owner_id']!==$user)throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');if($file['purpose']!=='CHAT'||$file['message_id']!==null)throw new DeliveryApiError(409,'FILE_IN_USE','Only an unused CHAT upload can be discarded.');
        $this->db->prepare('UPDATE private_files SET delete_after=now() WHERE file_id=?')->execute([$id]);return ['file_id'=>(string)$id,'deletion_queued'=>true];
    }
    public function signed(int $id,int $user,string $role):array {
        $file=$this->row($id);$this->authorize($file,$user,$role);$expires=time()+300;$signature=hash_hmac('sha256',"$id:$user:$expires",self::secret());
        return ['file_id'=>(string)$id,'download_query'=>http_build_query(['file_id'=>$id,'expires'=>$expires,'signature'=>$signature]),'expires_at'=>gmdate('c',$expires)];
    }
    public function download(int $id,int $user,string $role,array $in):array {
        $expiry=(string)($in['expires']??'');$signature=$in['signature']??'';
        if(!ctype_digit($expiry)||(int)$expiry<time()||(int)$expiry>time()+300||!is_string($signature)||!hash_equals(hash_hmac('sha256',"$id:$user:$expiry",self::secret()),$signature))throw new DeliveryApiError(403,'LINK_EXPIRED','Download link expired or invalid.');
        $file=$this->row($id);$this->authorize($file,$user,$role);$file['path']=self::root().DIRECTORY_SEPARATOR.$file['object_key'];if(!is_file($file['path']))throw new DeliveryApiError(404,'FILE_NOT_FOUND','File not found.');return $file;
    }
    public function retain(int $limit=100):int {
        $root=self::root();$q=$this->db->prepare('SELECT file_id,object_key FROM private_files WHERE deleted_at IS NULL AND delete_after<=now() ORDER BY file_id LIMIT ? FOR UPDATE SKIP LOCKED');$q->bindValue(1,$limit,PDO::PARAM_INT);$q->execute();$n=0;
        foreach($q->fetchAll() as $f){$path=$root.DIRECTORY_SEPARATOR.$f['object_key'];if(is_file($path)&&!unlink($path))throw new RuntimeException('Private file deletion failed.');$this->db->prepare('UPDATE private_files SET deleted_at=now() WHERE file_id=?')->execute([$f['file_id']]);$n++;}return $n;
    }
    /** Only enable for a dedicated SOMI volume. Never follow symlinks or remove other names. */
    public function pruneOrphans(int $limit=100):int {
        if(getenv('SOMI_STORAGE_PRUNE_ORPHANS')!=='1')return 0;$root=self::root();$n=0;$seen=0;
        foreach(new DirectoryIterator($root) as $entry){
            if($entry->isDot()||$entry->isLink()||!$entry->isFile()||!preg_match('/^[a-f0-9]{64}$/D',$entry->getFilename()))continue;
            if(++$seen>10000||$n>=$limit)break;if($entry->getMTime()>time()-86400)continue;
            $q=$this->db->prepare('SELECT 1 FROM private_files WHERE object_key=?');$q->execute([$entry->getFilename()]);if($q->fetchColumn())continue;
            $path=$root.DIRECTORY_SEPARATOR.$entry->getFilename();if(!unlink($path))throw new RuntimeException('Orphan cleanup failed.');$n++;
        }return $n;
    }
}
