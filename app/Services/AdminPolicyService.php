<?php
declare(strict_types=1);
require_once __DIR__.'/AccessControl.php';
require_once __DIR__.'/PaymentService.php';

final class AdminPolicyService
{
    private PDO $db;
    public function __construct(PDO $db){$this->db=$db;}
    public function createPricing(int $adminId,array $in): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','pricing.manage');
        DeliveryInput::only($in,['policy_name','currency_code','base_fee_minor','included_distance_km','per_km_minor','included_weight_kg','per_kg_minor','max_weight_kg','max_distance_km','return_fee_minor','time_bands','weather_bands','cancellation_fees']);
        $currency=$in['currency_code']??null;if(!in_array($currency,['VND','USD'],true))DeliveryInput::invalid('currency_code');
        $data=['policy_name'=>DeliveryInput::text($in['policy_name']??null,'policy_name',120),'currency_code'=>$currency,'calculation_rule'=>'BASE_PLUS_DISTANCE_PLUS_WEIGHT','created_by'=>$adminId];
        foreach(['base_fee_minor','per_km_minor','per_kg_minor','return_fee_minor']as $key)$data[$key]=PaymentService::money($in[$key]??null);
        foreach(['included_distance_km','included_weight_kg','max_distance_km','max_weight_kg']as $key)$data[$key]=DeliveryInput::decimal($in[$key]??null,$key,3,'0','999999.999');
        if(bccomp($data['max_distance_km'],'40',3)>0||bccomp($data['max_weight_kg'],'30',3)>0)DeliveryInput::invalid('service_limits');
        if(bccomp($data['max_distance_km'],$data['included_distance_km'],3)<=0||bccomp($data['max_weight_kg'],$data['included_weight_kg'],3)<0||bccomp($data['max_weight_kg'],'0',3)<=0)DeliveryInput::invalid('policy_limits');
        $time=$in['time_bands']??null;$weather=$in['weather_bands']??null;$fees=$in['cancellation_fees']??[];
        if(!is_array($time)||count($time)<1||count($time)>100||!is_array($weather)||count($weather)<3||count($weather)>100||!is_array($fees)||count($fees)>20)DeliveryInput::invalid('bands');
        self::validateTimeBands($time);
        $seen=[];foreach($weather as $band){if(!is_array($band))DeliveryInput::invalid('weather_bands');DeliveryInput::only($band,['condition','label','multiplier']);$condition=DeliveryInput::text($band['condition']??null,'condition',80);if(isset($seen[$condition]))DeliveryInput::invalid('duplicate_weather');$seen[$condition]=true;DeliveryInput::text($band['label']??null,'label',120);DeliveryInput::decimal($band['multiplier']??null,'multiplier',4,'0','9999.9999');}
        foreach(['NORMAL','LIGHT_RAIN','RAIN']as $required)if(!isset($seen[$required]))DeliveryInput::invalid('missing_weather');
        $feeSeen=[];foreach($fees as $fee){if(!is_array($fee))DeliveryInput::invalid('cancellation_fees');DeliveryInput::only($fee,['after_status','fee_minor']);$status=$fee['after_status']??null;if(!in_array($status,['PENDING_PAYMENT','SEARCHING_SHIPPER','NO_SHIPPER_FOUND','SHIPPER_ASSIGNED','PICKED_UP','IN_DELIVERY'],true)||isset($feeSeen[$status]))DeliveryInput::invalid('after_status');$feeSeen[$status]=true;PaymentService::money($fee['fee_minor']??null);}
        $this->db->exec('SELECT pg_advisory_xact_lock(20261106,20)');$q=$this->db->prepare('SELECT COALESCE(MAX(config_version),0)+1 FROM pricing_policies WHERE currency_code=?');$q->execute([$currency]);$data['config_version']=$q->fetchColumn();
        $sql='INSERT INTO pricing_policies('.implode(',',array_keys($data)).',effective_from,active) VALUES (:'.implode(',:',array_keys($data)).',now(),FALSE) RETURNING *';$q=$this->db->prepare($sql);$q->execute($data);$policy=$q->fetch();$id=$policy['pricing_policy_id'];
        $q=$this->db->prepare('INSERT INTO time_price_bands(pricing_policy_id,day_of_week,starts_at_local,ends_at_local,multiplier) VALUES (?,?,?,?,?)');foreach($time as $band)$q->execute([$id,$band['day_of_week']??null,$band['start'],$band['end'],$band['multiplier']]);
        $q=$this->db->prepare('INSERT INTO weather_price_bands(pricing_policy_id,provider_condition_code,label,multiplier) VALUES (?,?,?,?)');foreach($weather as $band)$q->execute([$id,$band['condition'],$band['label'],$band['multiplier']]);
        $q=$this->db->prepare('INSERT INTO cancellation_fee_rules(pricing_policy_id,after_status,fee_minor) VALUES (?,?,?)');foreach($fees as $fee)$q->execute([$id,$fee['after_status'],$fee['fee_minor']]);
        AuditService::record($this->db,$adminId,'ADMIN','PRICING_POLICY_CREATED','PRICING_POLICY',(int)$id,[],$data);return $policy;
    }
    public static function validateTimeBands(array $bands): void
    {
        $segments=[];$week=604800;
        foreach($bands as $band){
            if(!is_array($band))DeliveryInput::invalid('time_bands');DeliveryInput::only($band,['day_of_week','start','end','multiplier']);
            $day=$band['day_of_week']??null;if($day!==null&&(!is_int($day)||$day<0||$day>6))DeliveryInput::invalid('day_of_week');
            $seconds=[];foreach(['start','end']as $field){$clock=DeliveryInput::text($band[$field]??null,$field,8);if(!preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/D',$clock,$m))DeliveryInput::invalid($field);$seconds[$field]=(int)$m[1]*3600+(int)$m[2]*60+(int)$m[3];}
            if($seconds['start']===$seconds['end'])DeliveryInput::invalid('time_interval');DeliveryInput::decimal($band['multiplier']??null,'multiplier',4,'0','9999.9999');
            foreach($day===null?range(0,6):[$day]as $d){$start=$d*86400+$seconds['start'];$end=$d*86400+$seconds['end'];if($end<=$start)$end+=86400;if($end>$week){$segments[]=[$start,$week];$segments[]=[0,$end-$week];}else $segments[]=[$start,$end];}
        }
        usort($segments,static fn($a,$b)=>$a[0]<=>$b[0]);$cursor=0;foreach($segments as [$start,$end]){if($start!==$cursor)DeliveryInput::invalid('overlapping_or_missing_time_band');$cursor=$end;}if($cursor!==$week)DeliveryInput::invalid('missing_time_band');
    }
    public function activate(int $adminId,int $id): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','pricing.manage');$this->db->exec('SELECT pg_advisory_xact_lock(20261106,20)');
        $q=$this->db->prepare('SELECT *,effective_from<=now() AND (effective_until IS NULL OR effective_until>now()) AS effective FROM pricing_policies WHERE pricing_policy_id=? FOR UPDATE');$q->execute([$id]);$p=$q->fetch();
        if(!$p)throw new DeliveryApiError(404,'POLICY_NOT_FOUND','Policy not found.');if(!$p['effective'])throw new DeliveryApiError(409,'POLICY_NOT_EFFECTIVE','Policy is outside its effective period.');
        $this->db->prepare('UPDATE pricing_policies SET active=FALSE WHERE currency_code=? AND active')->execute([$p['currency_code']]);$this->db->prepare('UPDATE pricing_policies SET active=TRUE WHERE pricing_policy_id=?')->execute([$id]);
        AuditService::record($this->db,$adminId,'ADMIN','PRICING_POLICY_ACTIVATED','PRICING_POLICY',$id,[],['config_version'=>$p['config_version']]);return ['pricing_policy_id'=>$id,'active'=>true];
    }
    public function earning(int $adminId,array $in): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','pricing.manage');DeliveryInput::only($in,['policy_type','currency_code','percentage_rate','fixed_amount_minor']);
        $type=$in['policy_type']??null;$currency=$in['currency_code']??null;if(!in_array($type,['SUCCESSFUL_DELIVERY_PERCENT','FAILED_RETURN_FIXED'],true)||!in_array($currency,['VND','USD'],true))DeliveryInput::invalid('earning_policy');
        $rate=$type==='SUCCESSFUL_DELIVERY_PERCENT'?DeliveryInput::decimal($in['percentage_rate']??null,'percentage_rate',4,'0','100'):null;$amount=$type==='FAILED_RETURN_FIXED'?PaymentService::money($in['fixed_amount_minor']??null):null;
        $this->db->exec('SELECT pg_advisory_xact_lock(20261106,21)');$at=$this->db->query('SELECT clock_timestamp()')->fetchColumn();$this->db->prepare('UPDATE shipper_earning_policies SET effective_until=? WHERE policy_type=? AND currency_code=? AND effective_until IS NULL')->execute([$at,$type,$currency]);
        $q=$this->db->prepare('INSERT INTO shipper_earning_policies(policy_type,currency_code,percentage_rate,fixed_amount_minor,effective_from,changed_by) VALUES (?,?,?,?,?,?) RETURNING *');$q->execute([$type,$currency,$rate,$amount,$at,$adminId]);$p=$q->fetch();AuditService::record($this->db,$adminId,'ADMIN','EARNING_POLICY_CREATED','EARNING_POLICY',(int)$p['earning_policy_id'],[],$p);return $p;
    }
    public function score(int $adminId,array $in): array
    {
        AccessControl::requirePermission($this->db,$adminId,'ADMIN','pricing.manage');DeliveryInput::only($in,['deduction_points','post_pickup_deduction_points','lock_threshold','recovery_points','max_score']);$values=[];foreach(['deduction_points','lock_threshold','recovery_points']as $field)$values[]=DeliveryInput::decimal($in[$field]??null,$field,2,'0','99999.99');
        $this->db->exec('SELECT pg_advisory_xact_lock(20261106,22)');
        $current=$this->db->query('SELECT post_pickup_deduction_points,max_score FROM shipper_score_policies WHERE effective_from<=now() AND (effective_until IS NULL OR effective_until>now()) ORDER BY effective_from DESC LIMIT 1')->fetch()?:[];
        $post=DeliveryInput::decimal($in['post_pickup_deduction_points']??$current['post_pickup_deduction_points']??$in['deduction_points'],'post_pickup_deduction_points',2,'0','99999.99');
        $max=DeliveryInput::decimal($in['max_score']??$current['max_score']??'100','max_score',2,'0.01','99999.99');
        if(bccomp((string)$values[1],$max,2)>0)DeliveryInput::invalid('lock_threshold');
        $at=$this->db->query('SELECT clock_timestamp()')->fetchColumn();$this->db->prepare('UPDATE shipper_score_policies SET effective_until=? WHERE effective_until IS NULL')->execute([$at]);
        $q=$this->db->prepare('INSERT INTO shipper_score_policies(deduction_points,lock_threshold,recovery_points,post_pickup_deduction_points,max_score,effective_from,changed_by) VALUES (?,?,?,?,?,?,?) RETURNING *');$q->execute([...$values,$post,$max,$at,$adminId]);$p=$q->fetch();AuditService::record($this->db,$adminId,'ADMIN','SCORE_POLICY_CREATED','SCORE_POLICY',(int)$p['policy_id'],[],$p);return $p;
    }
}
