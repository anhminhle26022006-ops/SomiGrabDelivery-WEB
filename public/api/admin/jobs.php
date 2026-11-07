<?php
declare(strict_types=1);
require_once __DIR__.'/../_kernel.php';
$method=$_SERVER['REQUEST_METHOD']??'';
somi_run_api(['GET','POST'],['ADMIN'],'payment.reconcile',$method==='POST',static function(PDO $db,int $admin)use($method):array{
    if($method==='GET')return [200,['items'=>$db->query('SELECT job_id,kind,status,attempts,available_at,last_error_code,created_at,completed_at FROM background_jobs ORDER BY job_id DESC LIMIT 100')->fetchAll()]];
    $in=somi_json_body();DeliveryInput::only($in,['job_id']);$id=DeliveryInput::id($in['job_id']??null);$q=$db->prepare("UPDATE background_jobs SET status='PENDING',attempts=0,available_at=now(),last_error_code=NULL WHERE job_id=? AND status='FAILED'");$q->execute([$id]);if(!$q->rowCount())throw new DeliveryApiError(409,'JOB_NOT_RETRYABLE','Only failed jobs can be retried.');AuditService::record($db,$admin,'ADMIN','BACKGROUND_JOB_RETRY','JOB',$id);return [202,['queued'=>true]];
});
