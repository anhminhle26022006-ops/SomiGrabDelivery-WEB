<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__ . '/../app/Config/database.php';
$db=somi_db();$db->beginTransaction();$db->exec('SET TRANSACTION READ ONLY');
$tables=$db->query("SELECT table_name FROM information_schema.tables WHERE table_schema='somi' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
$required=['users','roles','permissions','role_permissions','user_roles','customer_profiles','customer_addresses','parcel_categories','orders','order_status_history','order_assignments','order_match_offers','payments','audit_logs','account_status_history','pricing_policies','distance_price_bands','time_price_bands','weather_price_bands','cancellation_fee_rules','shipper_earning_policies','shipper_score_policies','user_identity_documents'];
$missing=array_values(array_diff($required,$tables));
$columns=$db->query("SELECT table_name,column_name FROM information_schema.columns WHERE table_schema='somi'")->fetchAll();
$expected=[
 'users'=>['user_id','full_name','email','phone','password_hash','nationality_code','app_language','account_status','password_changed_at','email_verified_at','phone_verified_at','date_of_birth','gender','last_login_at','created_at','updated_at'],
 'customer_addresses'=>['address_id','customer_id','label','country_code','province','district','ward','address_detail','latitude','longitude','is_default','archived_at','created_at','updated_at'],
 'orders'=>['order_id','order_code','customer_id','status','currency_code','total_charge_minor','pricing_snapshot','pickup_saved_address_id','delivery_saved_address_id','paid_at','cancelled_at','closed_at','created_at','updated_at'],
 'order_assignments'=>['order_id','shipper_id','assignment_status'],
 'order_match_offers'=>['order_id','status','responded_at'],
 'order_status_history'=>['order_id','old_status','new_status','actor_id','actor_kind','note','status_history_id','created_at'],
 'shipper_profiles'=>['user_id','verification_status','availability','approved_at','approved_by','updated_at'],
 'user_identity_documents'=>['identity_document_id','user_id','review_status','rejection_reason','reviewed_by','reviewed_at','updated_at'],
 'audit_logs'=>['actor_id','actor_kind','action','entity_type','entity_id','old_value','new_value'],
 'account_status_history'=>['user_id','old_status','new_status','reason','changed_by','created_at'],
];
$missingColumns=[];$checks=0;
foreach($expected as $table=>$names){$actual=array_column(array_filter($columns,fn($row)=>$row['table_name']===$table),'column_name');foreach($names as $name){$checks++;if(!in_array($name,$actual,true))$missingColumns[]="$table.$name";}}
$result=['read_only'=>true,'required_tables'=>count($required),'missing'=>$missing,'required_columns'=>$checks,'missing_columns'=>$missingColumns,'new_migration_applied'=>in_array('pricing_configuration_drafts',$tables,true)&&in_array('nationality_change_requests',$tables,true),'result'=>($missing||$missingColumns)?'FAIL':'PASS'];
$db->rollBack();
echo json_encode($result,JSON_PRETTY_PRINT);exit(($missing||$missingColumns)?1:0);
