<?php
declare(strict_types=1);
// Copy existing filesystem objects without deleting the source or changing database references.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../app/Config/database.php';
require_once __DIR__.'/../app/Services/SupabaseObjectStorage.php';
try {
    $root=realpath(getenv('SOMI_STORAGE_ROOT')?:'');$project=realpath(__DIR__.'/..');
    if(!$root||!getenv('SOMI_STORAGE_ROOT')||str_starts_with(strtolower(str_replace('\\','/',$root)).'/',strtolower(str_replace('\\','/',$project)).'/'))throw new RuntimeException('Set the old private SOMI_STORAGE_ROOT outside the project.');
    $apply=in_array('--apply',$argv,true);$storage=$apply?new SupabaseObjectStorage():null;if($storage)$storage->check();
    $db=somi_db();$rows=$db->query('SELECT object_key,byte_size,checksum_sha256,mime_type FROM private_files WHERE deleted_at IS NULL AND delete_after>now()');$count=0;$copied=0;
    foreach($rows as $row){
        $key=$row['object_key'];if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Invalid object reference.');
        $path=$root.DIRECTORY_SEPARATOR.$key;
        if(is_link($path)||!is_file($path))throw new RuntimeException('Source object missing or unsafe.');
        $bytes=file_get_contents($path);
        if($bytes===false||strlen($bytes)>5242880||strlen($bytes)!==(int)$row['byte_size']||!hash_equals($row['checksum_sha256'],hash('sha256',$bytes)))throw new RuntimeException('Source integrity check failed.');
        $count++;
        if(!$apply)continue;
        try{$remote=$storage->get($key);if(!hash_equals($row['checksum_sha256'],hash('sha256',$remote)))throw new RuntimeException('Destination integrity mismatch.');}
        catch(DeliveryApiError $error){if($error->errorCode!=='FILE_NOT_FOUND')throw $error;$storage->put($key,$bytes,$row['mime_type']);$remote=$storage->get($key);if(!hash_equals($row['checksum_sha256'],hash('sha256',$remote)))throw new RuntimeException('Destination integrity mismatch.');$copied++;}
    }
    echo json_encode(['apply'=>$apply,'verified_files'=>$count,'copied_files'=>$copied,'source_deleted'=>false,'database_writes'=>0],JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Storage copy failed; source files and database references are unchanged. Check configuration, connectivity and object integrity.'.PHP_EOL);exit(1);}
