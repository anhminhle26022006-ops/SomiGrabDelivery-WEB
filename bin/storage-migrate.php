<?php
declare(strict_types=1);
// Copy verified filesystem objects and record their backend without deleting the source.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/SupabaseStorage.php';
try {
    $root=realpath(getenv('SOMI_STORAGE_ROOT')?:'');$project=realpath(__DIR__.'/..');
    if(!$root||!getenv('SOMI_STORAGE_ROOT')||str_starts_with(strtolower(str_replace('\\','/',$root)).'/',strtolower(str_replace('\\','/',$project)).'/'))throw new RuntimeException('Set the old private SOMI_STORAGE_ROOT outside the project.');
    $apply=in_array('--apply',$argv,true);$storage=$apply?new SupabaseStorage():null;if($storage)$storage->checkBucket();
    $db=somi_db();$rows=$db->query("SELECT file_id,object_key,byte_size,checksum_sha256,mime_type FROM private_files WHERE storage_backend='local' AND deleted_at IS NULL AND delete_after>now()")->fetchAll();$count=0;$copied=0;$updated=0;
    foreach($rows as $row){
        $key=$row['object_key'];if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Invalid object reference.');
        $path=$root.DIRECTORY_SEPARATOR.$key;
        if(is_link($path)||!is_file($path))throw new RuntimeException('Source object missing or unsafe.');
        $bytes=file_get_contents($path);
        if($bytes===false||strlen($bytes)>5242880||strlen($bytes)!==(int)$row['byte_size']||!hash_equals($row['checksum_sha256'],hash('sha256',$bytes)))throw new RuntimeException('Source integrity check failed.');
        $count++;
        if(!$apply)continue;
        try{$remote=$storage->get($key);if(!hash_equals($row['checksum_sha256'],hash('sha256',$remote)))throw new RuntimeException('Destination integrity mismatch.');}
        catch(DeliveryApiError $error){if($error->errorCode!=='FILE_NOT_FOUND')throw $error;$storage->put($key,$path,$row['mime_type']);$remote=$storage->get($key);if(!hash_equals($row['checksum_sha256'],hash('sha256',$remote)))throw new RuntimeException('Destination integrity mismatch.');$copied++;}
        $q=$db->prepare("UPDATE private_files SET storage_backend='supabase',storage_bucket=? WHERE file_id=? AND storage_backend='local' AND deleted_at IS NULL");$q->execute([$storage->bucket(),$row['file_id']]);$updated+=$q->rowCount();
    }
    echo json_encode(['apply'=>$apply,'verified_files'=>$count,'copied_files'=>$copied,'source_deleted'=>false,'metadata_updated'=>$updated],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Storage copy stopped; source files are preserved. Verified metadata updates may already have completed; retry to resume.'.PHP_EOL);exit(1);}
