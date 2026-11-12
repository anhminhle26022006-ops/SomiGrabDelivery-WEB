<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
somi_run_api(['POST'],['CUSTOMER','SHIPPER','ADMIN'],'profile.update_own',true,static function(PDO $db,int $id,string $role):array{
    $in=somi_json_body();DeliveryInput::only($in,['current_password','password']);$current=$in['current_password']??null;$new=$in['password']??null;if(!is_string($current)||!is_string($new)||strlen($current)>72||strlen($new)<8||strlen($new)>72)DeliveryInput::invalid('password');
    $q=$db->prepare('SELECT password_hash FROM users WHERE user_id=? FOR UPDATE');$q->execute([$id]);if(!password_verify($current,$q->fetchColumn()))throw new DeliveryApiError(422,'PASSWORD_MISMATCH','Current password is incorrect.');
    $db->prepare('UPDATE users SET password_hash=?,password_changed_at=now(),updated_at=now() WHERE user_id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$id]);AuditService::record($db,$id,$role,'PASSWORD_CHANGED','USER',$id);return [200,['reauthenticate'=>true]];
});
