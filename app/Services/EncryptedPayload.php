<?php
declare(strict_types=1);

final class EncryptedPayload
{
    private static function key(): string
    {
        $key=base64_decode(getenv('SOMI_MESSAGE_ENCRYPTION_KEY')?:getenv('SOMI_IDENTITY_ENCRYPTION_KEY')?:'',true);
        if($key===false||strlen($key)!==32)throw new RuntimeException('SOMI_MESSAGE_ENCRYPTION_KEY must be configured as a base64 32-byte key.');
        return $key;
    }
    public static function seal(array $payload): string
    {
        $nonce=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($payload,JSON_THROW_ON_ERROR),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$nonce,$tag,'SOMI-MESSAGE-v1',16);
        if($cipher===false)throw new RuntimeException('Message encryption failed.');
        return 'v1:'.base64_encode($nonce.$tag.$cipher);
    }
    public static function open(string $payload): array
    {
        if(!str_starts_with($payload,'v1:'))throw new RuntimeException('Unsupported message encryption version.');
        $bytes=base64_decode(substr($payload,3),true);
        if($bytes===false||strlen($bytes)<29)throw new RuntimeException('Invalid encrypted message.');
        $plain=openssl_decrypt(substr($bytes,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16),'SOMI-MESSAGE-v1');
        if($plain===false)throw new RuntimeException('Message decryption failed.');
        return json_decode($plain,true,32,JSON_THROW_ON_ERROR);
    }
}
