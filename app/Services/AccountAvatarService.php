<?php
declare(strict_types=1);
require_once __DIR__.'/AuditService.php';

/** Small profile images are stored privately; every read is bound to the current account. */
final class AccountAvatarService
{
    public function __construct(private PDO $db) {}

    public static function validate(string $bytes): string
    {
        if ($bytes === '' || strlen($bytes) > 1048576) throw new DeliveryApiError(422,'AVATAR_SIZE_INVALID','Avatar must be at most 1 MiB.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $size=@getimagesizefromstring($bytes);
        if (!in_array($mime,['image/jpeg','image/png','image/webp'],true) || !$size || ($size['mime']??'')!==$mime)
            throw new DeliveryApiError(422,'AVATAR_TYPE_INVALID','Choose a JPEG, PNG or WebP image.');
        if ($size[0]>1024 || $size[1]>1024) throw new DeliveryApiError(422,'AVATAR_DIMENSIONS_INVALID','Resize the avatar to at most 1024 by 1024 pixels.');
        return $mime;
    }

    public function metadata(int $user): array
    {
        $q=$this->db->prepare('SELECT version FROM account_avatars WHERE user_id=?');$q->execute([$user]);$version=$q->fetchColumn();
        return ['avatar_url'=>$version===false?null:'public/api/account/avatar.php?content=1&v='.rawurlencode((string)$version)];
    }

    public function save(int $user,string $role,string $bytes): array
    {
        $mime=self::validate($bytes);$version=bin2hex(random_bytes(12));
        $q=$this->db->prepare("INSERT INTO account_avatars(user_id,image_data,mime_type,version) VALUES(?,decode(?,'base64'),?,?) ON CONFLICT(user_id) DO UPDATE SET image_data=EXCLUDED.image_data,mime_type=EXCLUDED.mime_type,version=EXCLUDED.version,updated_at=now()");
        $q->execute([$user,base64_encode($bytes),$mime,$version]);
        AuditService::record($this->db,$user,$role,'AVATAR_UPDATED','USER',$user);
        return ['avatar_url'=>'public/api/account/avatar.php?content=1&v='.$version];
    }

    public function content(int $user): array
    {
        $q=$this->db->prepare("SELECT encode(image_data,'base64') AS bytes,mime_type FROM account_avatars WHERE user_id=?");$q->execute([$user]);$row=$q->fetch();
        if (!$row) throw new DeliveryApiError(404,'AVATAR_NOT_FOUND','No avatar uploaded.');
        return ['bytes'=>base64_decode($row['bytes'],true),'mime_type'=>$row['mime_type']];
    }

    public function remove(int $user,string $role): array
    {
        $q=$this->db->prepare('DELETE FROM account_avatars WHERE user_id=?');$q->execute([$user]);
        AuditService::record($this->db,$user,$role,'AVATAR_REMOVED','USER',$user);
        return ['avatar_url'=>null];
    }
}
