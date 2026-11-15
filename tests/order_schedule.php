<?php
declare(strict_types=1);
require_once __DIR__.'/../app/Services/OrderSchedule.php';
$checks=0;
function verify(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function rejects(callable $fn,string $code):void{try{$fn();}catch(DeliveryApiError $e){verify($e->errorCode===$code,$code);return;}throw new RuntimeException('Expected '.$code);}
$now=new DateTimeImmutable('2026-11-14T12:00:00+07:00');
verify(OrderSchedule::normalize(null,$now)['mode']==='now','legacy immediate request');
verify(OrderSchedule::normalize(['mode'=>'now','at'=>null],$now)['dispatch_at']===null,'immediate has no dispatch gate');
$schedule=OrderSchedule::normalize(['mode'=>'later','at'=>'2026-11-14T13:00:00+07:00'],$now);
verify($schedule['at']==='2026-11-14T06:00:00+00:00','normalize appointment to UTC');
verify($schedule['dispatch_at']==='2026-11-14T05:45:00+00:00','start matching 15 minutes before pickup');
verify(OrderSchedule::fromOrder(['pricing_snapshot'=>json_encode(['schedule'=>$schedule])])===$schedule,'schedule round-trips through snapshot');
verify(OrderSchedule::fromOrder(['pricing_snapshot'=>'{}'])===null,'legacy order remains immediate');
foreach([1799,604801,-60] as $offset)rejects(fn()=>OrderSchedule::normalize(['mode'=>'later','at'=>gmdate(DATE_ATOM,$now->getTimestamp()+$offset)],$now),'SCHEDULE_OUT_OF_RANGE');
foreach([1800,604800] as $offset)verify(OrderSchedule::normalize(['mode'=>'later','at'=>gmdate(DATE_ATOM,$now->getTimestamp()+$offset)],$now)['mode']==='later','inclusive scheduling boundary');
foreach(['2026-11-14T13:00:00','tomorrow','2026-11-31T13:00:00+07:00',''] as $at)rejects(fn()=>OrderSchedule::normalize(['mode'=>'later','at'=>$at],$now),'VALIDATION_ERROR');
rejects(fn()=>OrderSchedule::normalize(['mode'=>'later','at'=>'2026-11-14T13:00:00+07:00','dispatch_at'=>'2020-01-01'],$now),'INVALID_FIELDS');
rejects(fn()=>OrderSchedule::normalize(['mode'=>'now','at'=>'2026-11-14T13:00:00+07:00'],$now),'VALIDATION_ERROR');
rejects(fn()=>OrderSchedule::normalize('later',$now),'VALIDATION_ERROR');
rejects(fn()=>OrderSchedule::assertNotExpired(['mode'=>'later','at'=>'2020-01-01T00:00:00Z']),'SCHEDULE_EXPIRED');
echo "Pickup schedule validation: $checks checks passed.\n";
