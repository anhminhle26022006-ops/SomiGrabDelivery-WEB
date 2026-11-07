<?php
declare(strict_types=1);
require_once __DIR__.'/DeliveryApiError.php';

final class AuthRateLimiter
{
    public static function hit(PDO $db,string $bucket,int $limit=10,int $window=900): void
    {
        $q=$db->prepare("INSERT INTO auth_rate_limits(bucket_hash,attempts) VALUES (?,1) ON CONFLICT(bucket_hash) DO UPDATE SET attempts=CASE WHEN auth_rate_limits.window_started_at < now()-make_interval(secs=>?) THEN 1 ELSE auth_rate_limits.attempts+1 END,window_started_at=CASE WHEN auth_rate_limits.window_started_at < now()-make_interval(secs=>?) THEN now() ELSE auth_rate_limits.window_started_at END RETURNING attempts");
        $q->execute([hash('sha256',$bucket),$window,$window]);
        if((int)$q->fetchColumn()>$limit)throw new DeliveryApiError(429,'RATE_LIMITED','Too many attempts. Please try again later.');
    }
}
