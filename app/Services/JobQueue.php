<?php
declare(strict_types=1);

final class JobQueue
{
    public static function enqueue(PDO $db,string $kind,string $key,array $payload): void
    {
        if (!$db->inTransaction()) throw new LogicException('Jobs must be enqueued in the business transaction.');
        $q=$db->prepare('INSERT INTO background_jobs(kind,deduplication_key,payload) VALUES (?,?,CAST(? AS jsonb)) ON CONFLICT(deduplication_key) DO NOTHING');
        $q->execute([$kind,$key,json_encode($payload,JSON_THROW_ON_ERROR)]);
    }

    public static function claim(PDO $db): ?array
    {
        if ($db->inTransaction()) throw new LogicException('Worker claim owns its transaction.');
        $db->beginTransaction();
        try {
            $q=$db->query("SELECT * FROM background_jobs WHERE (status='PENDING' AND available_at<=now()) OR (status='RUNNING' AND locked_until<now()) ORDER BY job_id FOR UPDATE SKIP LOCKED LIMIT 1");
            $job=$q->fetch();
            if (!$job) {$db->commit();return null;}
            $token=bin2hex(random_bytes(16));
            $q=$db->prepare("UPDATE background_jobs SET status='RUNNING',lock_token=?,locked_until=now()+interval '90 seconds',attempts=attempts+1 WHERE job_id=?");$q->execute([$token,$job['job_id']]);
            $db->commit();$job['lock_token']=$token;$job['attempts']=(int)$job['attempts']+1;$job['payload']=json_decode($job['payload'],true,512,JSON_THROW_ON_ERROR);return $job;
        } catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }

    public static function finish(PDO $db,array $job,?string $error=null): void
    {
        $failed=$error!==null && $job['attempts']>=8;
        $status=$error===null?'DONE':($failed?'FAILED':'PENDING');
        $q=$db->prepare("UPDATE background_jobs SET status=:status,last_error_code=:error,available_at=now()+make_interval(secs=>:delay),lock_token=NULL,locked_until=NULL,completed_at=CASE WHEN :done THEN now() ELSE NULL END WHERE job_id=:id AND lock_token=:token");
        $q->execute(['status'=>$status,'error'=>$error,'delay'=>min(3600,30*(2**min(7,$job['attempts']))),'done'=>$status==='DONE'?'true':'false','id'=>$job['job_id'],'token'=>$job['lock_token']]);
    }
}
