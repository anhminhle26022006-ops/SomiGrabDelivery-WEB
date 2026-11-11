<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/CustomerOrderService.php';
require_once __DIR__ . '/../app/Services/ApprovedPricingRelease.php';
require_once __DIR__ . '/../app/Services/ServiceRangeRelease.php';
require_once __DIR__ . '/../app/Services/DistanceWeightPricingRelease.php';

// All DDL, fixture rows, sequences and writes are session-local TEMP tables.
// pg_temp-only search_path prevents fallback to application tables. Roll back
// everything; never apply migrations or seed prices in the real somi schema.
$checks = 0;
function check(bool $ok, string $label): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException('FAILED: ' . $label);
}
function rejects(callable $fn, string $code): void {
    try { $fn(); } catch (DeliveryApiError $e) { check($e->errorCode === $code, $code . ' (actual ' . $e->errorCode . ')'); return; }
    throw new RuntimeException('Expected ' . $code);
}
function scalar(PDO $db, string $sql, array $params = []) {
    $q = $db->prepare($sql); $q->execute($params); return $q->fetchColumn();
}
final class FixturePricingContext implements PricingContextProvider {
    public bool $stale = false;
    public string $distance = '4.000';
    public string $weather = 'TEST_CLEAR';
    public function resolve(array $pickup, array $delivery, DateTimeImmutable $at): array {
        return ['distance_km' => $this->distance, 'duration_minutes' => 20, 'weather_condition' => $this->weather, 'provider' => 'test-fixture-only', 'observed_at' => $at->modify($this->stale ? '-1 hour' : '+0 seconds')->format(DATE_ATOM)];
    }
}

$db = null;
try {
    $db = somi_db();
    $db->beginTransaction();
    $db->exec('SET LOCAL search_path TO pg_temp, pg_catalog');
    $sql = file_get_contents(__DIR__ . '/../database/SOMI_database.sql');
    $sql = preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^CREATE SCHEMA[^;]+;\s*|^SET search_path[^;]+;\s*/m', '', $sql);
    // Identity erasure trigger is unrelated and refers explicitly to somi.
    $sql = preg_replace('/CREATE FUNCTION erase_identity_document_on_account_close\(\).*?(?=CREATE TABLE shipper_score_policies)/s', '', $sql);
    $sql = str_replace('CREATE TABLE ', 'CREATE TEMP TABLE ', $sql);
    $db->exec($sql);
    $migration = file_get_contents(__DIR__ . '/../database/migrations/20261105_customer_orders.sql');
    $migration = preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m', '', $migration);
    $db->exec(str_replace('CREATE TABLE ', 'CREATE TEMP TABLE ', $migration));
    check((string) scalar($db, "SELECT current_setting('search_path')") === 'pg_temp, pg_catalog', 'isolated search path');
    check((int) scalar($db, "SELECT count(*) FROM pg_class WHERE relnamespace = pg_my_temp_schema() AND relname IN ('users','orders','payments','order_quotes','order_events')") === 5, 'all business writes target temporary tables');

    $db->exec("INSERT INTO users (full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status) VALUES
        ('Fixture A','a@example.invalid','0900000001','test-only','VN','2000-01-01','M','vi','ACTIVE'),
        ('Fixture B','b@example.invalid','0900000002','test-only','US','2000-01-01','F','en','ACTIVE'),
        ('Fixture Shipper','s@example.invalid','0900000003','test-only','VN','2000-01-01','M','vi','ACTIVE');
        INSERT INTO customer_profiles (user_id) VALUES (1),(2);
        INSERT INTO shipper_profiles (user_id,vehicle_type,vehicle_registration,verification_status) VALUES (3,'TEST','TEST-ONLY','APPROVED');
        INSERT INTO parcel_categories (category_code,name_vi,name_en,restriction_type) VALUES ('TEST_ALLOWED','Test','Test','ALLOWED'),('TEST_BANNED','Test','Test','PROHIBITED'),('TEST_RESTRICTED','Test','Test','RESTRICTED');
        INSERT INTO pricing_policies (policy_name,currency_code,return_fee_minor,active,effective_from,calculation_rule) VALUES
        ('TEST ONLY VND','VND',17,TRUE,now()-interval '1 day','DISTANCE_BAND_X_TIME_X_WEATHER'),
        ('TEST ONLY USD','USD',9,TRUE,now()-interval '1 day','DISTANCE_BAND_X_TIME_X_WEATHER');
        INSERT INTO distance_price_bands (pricing_policy_id,min_km,max_km,amount_minor) VALUES (1,0,5,101),(1,5,NULL,999),(2,0,NULL,53);
        INSERT INTO time_price_bands (pricing_policy_id,starts_at_local,ends_at_local,multiplier) VALUES (1,'00:00','12:00',1.0001),(1,'12:00','00:00',1.0001),(2,'00:00','12:00',1),(2,'12:00','00:00',1);
        INSERT INTO weather_price_bands (pricing_policy_id,provider_condition_code,label,multiplier) VALUES (1,'TEST_CLEAR','Test',1.0001),(2,'TEST_CLEAR','Test',1);");
    $addresses = new CustomerAddressService($db);
    $address = ['label' => 'Original', 'address_detail' => 'Test street', 'province' => 'Test province', 'latitude' => '10.1234567', 'longitude' => '106.1234567', 'is_default' => true];
    $a = $addresses->save(1, $address);
    $b = $addresses->save(1, array_replace($address, ['label' => 'Second']));
    check(count($addresses->all(1)) === 2, 'address list');
    check(!(bool) $addresses->get(1, (int) $a['address_id'])['is_default'] && (bool) $b['is_default'], 'single default');
    rejects(fn() => $addresses->get(2, (int) $a['address_id']), 'ADDRESS_NOT_FOUND');
    rejects(fn() => $addresses->save(2, $address, (int) $a['address_id']), 'ADDRESS_NOT_FOUND');
    rejects(fn() => $addresses->archive(2, (int) $a['address_id']), 'ADDRESS_NOT_FOUND');
    rejects(fn() => $addresses->save(1, array_replace($address, ['latitude' => '91'])), 'VALIDATION_ERROR');
    rejects(fn() => $addresses->save(1, array_replace($address, ['is_default' => 'false'])), 'VALIDATION_ERROR');
    $provider = new FixturePricingContext();
    $quotes = new OrderQuoteService($db, $provider);
    $orders = new CustomerOrderService($db);
    $input = ['pickup' => ['saved_address_id' => $a['address_id'], 'contact_name' => 'Sender', 'contact_phone' => '0900000001'],
        'delivery' => ['address' => array_diff_key($address, ['is_default' => true]), 'contact_name' => 'Receiver', 'contact_phone' => '0900000002'],
        'parcel' => ['category_id' => 1, 'weight_kg' => '1.250'], 'customer_note' => 'Test only'];
    $overweight = $input;
    $overweight['parcel']['weight_kg'] = '30.001';
    rejects(fn() => $quotes->create(1, $overweight), 'WEIGHT_NOT_SUPPORTED');
    $provider->distance = '40.000';
    rejects(fn() => $quotes->create(1, $input), 'OUTSIDE_SERVICE_AREA');
    $provider->distance = '4.000';
    $quote = $quotes->create(1, $input);
    check($quote['snapshot']['components']['total_charge_minor'] === '102', 'exact ceiling of composed multipliers');
    check(OrderQuoteService::total('9007199254740993','1.0000','1.0000') === '9007199254740993', 'money beyond JS precision remains exact');
    $night = ['starts_at_local' => '22:00:00', 'ends_at_local' => '02:00:00', 'day_of_week' => 1];
    check(OrderQuoteService::timeMatches($night, new DateTimeImmutable('2026-11-03T01:00:00+07:00')), 'overnight uses previous weekday');
    check(!OrderQuoteService::timeMatches($night, new DateTimeImmutable('2026-11-03T02:00:00+07:00')), 'time interval end excluded');
    rejects(fn() => $quotes->create(2, $input), 'ADDRESS_NOT_FOUND');
    rejects(fn() => $quotes->create(1, $input + ['total_charge_minor' => 1]), 'INVALID_FIELDS');
    foreach ([2,3] as $category) { $bad = $input; $bad['parcel']['category_id'] = $category; rejects(fn() => $quotes->create(1, $bad), 'PARCEL_NOT_ALLOWED'); }
    $provider->stale = true; rejects(fn() => $quotes->create(1, $input), 'PRICING_CONTEXT_UNAVAILABLE'); $provider->stale = false;
    $db->exec("UPDATE pricing_policies SET calculation_rule = NULL WHERE pricing_policy_id = 1");
    rejects(fn() => $quotes->create(1, $input), 'PRICING_NOT_CONFIGURED');
    $db->exec("UPDATE pricing_policies SET calculation_rule = 'DISTANCE_BAND_X_TIME_X_WEATHER' WHERE pricing_policy_id = 1");
    $db->exec("INSERT INTO time_price_bands (pricing_policy_id,starts_at_local,ends_at_local,multiplier) VALUES (1,'00:00','23:59:59',2)");
    rejects(fn() => $quotes->create(1, $input), 'PRICING_NOT_CONFIGURED');
    $db->exec('DELETE FROM time_price_bands WHERE multiplier = 2');
    $addresses->save(1, array_replace($address, ['address_detail' => 'Changed street']), (int) $a['address_id']);
    $addresses->archive(1, (int) $a['address_id']);
    // A quote freezes the accepted price before creation, even if the policy changes.
    $db->exec('UPDATE distance_price_bands SET amount_minor = 777 WHERE pricing_policy_id = 1');
    $created = $orders->create(1, $quote['quote_id'], 'test-create-0001');
    $id = (int) $created['order']['order_id'];
    check($created['created'] && $created['order']['status'] === 'PENDING_PAYMENT', 'initial unpaid order');
    check(str_contains($created['order']['pickup_address'], 'Test street') && !str_contains($created['order']['pickup_address'], 'Changed'), 'immutable address and contact snapshot');
    check($created['order']['pricing_snapshot'] == $quote['snapshot'], 'entire accepted quote persists unchanged');
    check((string) $created['order']['total_charge_minor'] === $quote['snapshot']['components']['total_charge_minor'], 'policy edit before create does not reprice quote');
    check($created['order']['pickup_latitude'] === $quote['snapshot']['pickup']['latitude'] && $created['order']['sender_phone'] === '0900000001', 'coordinates and contacts are frozen');
    check(!array_key_exists('request_key', $created['order']), 'detail does not expose idempotency key');
    check(count($created['order']['status_history']) === 1 && $created['order']['status_history'][0]['old_status'] === null && $created['order']['status_history'][0]['new_status'] === 'PENDING_PAYMENT', 'initial timeline records unpaid creation');
    check((string) $orders->detail(1, $id)['total_charge_minor'] === '102', 'policy edits do not change existing order');
    check(!$orders->create(1, $quote['quote_id'], 'test-create-0001')['created'], 'create retry idempotent');
    rejects(fn() => $orders->create(1, $quote['quote_id'], 'test-create-0002'), 'QUOTE_ALREADY_USED');
    rejects(fn() => $orders->create(2, $quote['quote_id'], 'test-create-0003'), 'QUOTE_NOT_FOUND');
    rejects(fn() => $orders->detail(2, $id), 'ORDER_NOT_FOUND');
    rejects(fn() => $orders->cancel(2, $id, 'Test'), 'ORDER_NOT_FOUND');
    check(count($orders->list(2, null, 20, null)['items']) === 0, 'list ownership');
    $input['pickup'] = $input['delivery'];
    $other = $quotes->create(1, $input);
    rejects(fn() => $orders->create(1, $other['quote_id'], 'test-create-0001'), 'IDEMPOTENCY_CONFLICT');
    $db->prepare("UPDATE order_quotes SET created_at = now()-interval '2 hours', expires_at = now()-interval '1 hour' WHERE quote_id = ?")->execute([$other['quote_id']]);
    rejects(fn() => $orders->create(1, $other['quote_id'], 'test-expired-0001'), 'QUOTE_EXPIRED');
    $q2 = $quotes->create(1, $input); $o2 = $orders->create(1, $q2['quote_id'], 'test-create-0004')['order']; $id2 = (int) $o2['order_id'];
    $usd = $quotes->create(2, $input);
    check($usd['snapshot']['currency_code'] === 'USD' && $usd['snapshot']['components']['total_charge_minor'] === '53', 'currency derived from stored account language');
    $chosenUsd = $quotes->create(1, array_replace($input, ['currency_code'=>'USD']));
    check($chosenUsd['snapshot']['currency_code']==='USD' && $chosenUsd['snapshot']['app_language']==='vi' && $chosenUsd['snapshot']['components']['total_charge_minor']==='53', 'Vietnamese account can select the independent USD price policy');
    $chosenVnd = $quotes->create(2, array_replace($input, ['currency_code'=>'VND']));
    check($chosenVnd['snapshot']['currency_code']==='VND' && $chosenVnd['snapshot']['app_language']==='en' && $chosenVnd['snapshot']['components']['total_charge_minor']===$q2['snapshot']['components']['total_charge_minor'], 'English account can select VND without changing account language');
    foreach(['EUR','vnd','',null,[],100] as $invalidCurrency) rejects(fn()=>$quotes->create(1,array_replace($input,['currency_code'=>$invalidCurrency])), 'VALIDATION_ERROR');
    check($orders->detail(1,$id2)['currency_code']==='VND','new currency selection preserves existing order currency');
    check($orders->list(1, null, 1, null)['items'][0]['receiver_name'] === 'Receiver', 'list includes owned recipient name for search');
    check($orders->list(1, null, 1, null)['next_before_id'] === (string) $id2, 'keyset cursor');
    check((int) $orders->list(1, $id2, 1, null)['items'][0]['order_id'] === $id, 'keyset next page');
    check($orders->list(1, $id, 1, null)['items'] === [] && $orders->list(1, $id, 1, null)['next_before_id'] === null, 'pagination stops without duplicates');
    rejects(fn() => $orders->list(1, null, 101, null), 'VALIDATION_ERROR');
    rejects(fn() => $orders->list(1, null, 20, 'NOT_A_STATUS'), 'VALIDATION_ERROR');

    $db->prepare("INSERT INTO payments (order_id, amount_minor,currency_code,method,status) VALUES (?,102,'VND','GATEWAY','PENDING')")->execute([$id]);
    $paymentId = (int) scalar($db, 'SELECT payment_id FROM payments WHERE order_id = ?', [$id]);
    rejects(fn() => OrderLifecycleService::paymentConfirmed($db, $id, $paymentId), 'PAYMENT_NOT_CONFIRMED');
    $db->prepare("UPDATE payments SET status = 'PAID', paid_at = now() WHERE payment_id = ?")->execute([$paymentId]);
    check(OrderLifecycleService::paymentConfirmed($db, $id, $paymentId) === 'SEARCHING_SHIPPER', 'verified payment starts matching');
    OrderLifecycleService::paymentConfirmed($db, $id, $paymentId);
    check((int) scalar($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderReadyForMatching'", [$id]) === 1, 'callback replay emits once');
    $db->prepare("INSERT INTO order_match_offers (order_id,shipper_id,expires_at) VALUES (?,3,now()+interval '2 minutes')")->execute([$id]);
    $orders->cancel(1, $id, 'No longer needed'); $orders->cancel(1, $id, 'Retry');
    check(scalar($db, 'SELECT status FROM order_match_offers WHERE order_id = ?', [$id]) === 'CANCELLED', 'cancel invalidates pending offers');
    check((int) scalar($db, "SELECT count(*) FROM order_status_history WHERE order_id = ? AND new_status = 'CANCELLED'", [$id]) === 1, 'cancel retry creates one history entry');
    check((int) scalar($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'RefundRequested'", [$id]) === 1, 'paid cancellation requests refund');
    $cancelled = $orders->detail(1, $id);
    check(array_column($cancelled['status_history'], 'new_status') === ['PENDING_PAYMENT','SEARCHING_SHIPPER','CANCELLED'], 'timeline is ordered from creation through cancellation');
    check($cancelled['status_history'][2]['note'] === 'No longer needed', 'cancel retry preserves original reason');
    check($cancelled['pricing_snapshot'] == $quote['snapshot'] && (string)$cancelled['total_charge_minor'] === '102', 'cancellation preserves original address and price snapshot');
    check(count($orders->list(1, null, 20, 'CANCELLED')['items']) === 1, 'cancelled orders remain in filtered history');
    $orders->cancel(1, $id2, 'Before payment');
    $db->prepare("INSERT INTO payments (order_id,amount_minor,currency_code,method,status,paid_at) VALUES (?,?,'VND','CARD','PAID',now())")->execute([$id2, $o2['total_charge_minor']]);
    $late = (int) scalar($db, 'SELECT payment_id FROM payments WHERE order_id = ?', [$id2]);
    check(OrderLifecycleService::paymentConfirmed($db, $id2, $late) === 'CANCELLED', 'late capture never reopens cancelled order');
    OrderLifecycleService::paymentConfirmed($db, $id2, $late);
    check((int) scalar($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'RefundRequested'", [$id2]) === 1, 'late capture requests refund exactly once');
    check((int) scalar($db, "SELECT count(*) FROM order_events WHERE order_id = ? AND event_type = 'OrderReadyForMatching'", [$id2]) === 0, 'late capture never starts matching');

    $q3 = $quotes->create(1, $input); $o3 = $orders->create(1, $q3['quote_id'], 'test-assigned-0001')['order']; $id3 = (int) $o3['order_id'];
    $db->prepare('INSERT INTO order_assignments (order_id,shipper_id) VALUES (?,3)')->execute([$id3]);
    rejects(fn() => $orders->cancel(1, $id3, 'Test'), 'ORDER_NOT_CANCELLABLE');
    $db->prepare("UPDATE orders SET status = 'SHIPPER_ASSIGNED' WHERE order_id = ?")->execute([$id3]);
    rejects(fn() => $orders->cancel(1, $id3, 'Test'), 'ORDER_NOT_CANCELLABLE');
    check((int) scalar($db, "SELECT count(*) FROM order_status_history WHERE order_id=? AND new_status='CANCELLED'", [$id3]) === 0, 'rejected cancellation does not append history');
    $db->prepare("UPDATE orders SET status = 'DELIVERED' WHERE order_id = ?")->execute([$id3]);
    rejects(fn() => $orders->cancel(1, $id3, 'Test'), 'ORDER_NOT_CANCELLABLE');
    $q4 = $quotes->create(1, $input);
    $db->exec("UPDATE parcel_categories SET restriction_type = 'PROHIBITED' WHERE category_id = 1");
    rejects(fn() => $orders->create(1, $q4['quote_id'], 'test-banned-0001'), 'PARCEL_NOT_ALLOWED');
    $migration = file_get_contents(__DIR__ . '/../database/migrations/20261105_approved_pricing.sql');
    $db->exec(preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m', '', $migration));
    ApprovedPricingRelease::apply($db);
    check((int) scalar($db, 'SELECT count(*) FROM pricing_policies WHERE active') === 2, 'one approved policy per currency');
    check((int) scalar($db, "SELECT count(*) FROM audit_logs WHERE action = 'APPROVED_PRICING_RELEASE'") === 2, 'pricing release audited');
    check((string) $orders->detail(1, $id)['total_charge_minor'] === '102', 'pricing release preserves historical order snapshot');
    $db->exec("UPDATE parcel_categories SET restriction_type = 'ALLOWED' WHERE category_id = 1");
    $provider->weather = 'NORMAL';
    $approvedQuote = $quotes->create(1, $input);
    check($approvedQuote['snapshot']['components']['distance_fee_minor'] === '25000', 'approved VND distance band');
    check($approvedQuote['snapshot']['components']['return_fee_minor'] === '10000', 'approved VND return fee');
    $provider->distance = '3.000';
    check($quotes->create(2, $input)['snapshot']['components']['distance_fee_minor'] === '129', '3 km switches band; USD independent');
    $provider->distance = '20.000';
    rejects(fn() => $quotes->create(1, $input), 'DISTANCE_PRICE_NOT_CONFIGURED');
    $provider->distance = '40.000';
    rejects(fn() => $quotes->create(1, $input), 'OUTSIDE_SERVICE_AREA');
    ServiceRangeRelease::apply($db);
    check((int) scalar($db, 'SELECT count(*) FROM pricing_policies WHERE active AND max_distance_km = 40') === 2, '40 km active policies');
    check((int) scalar($db, "SELECT count(*) FROM audit_logs WHERE action = 'SERVICE_RANGE_40KM'") === 2, 'range change audited');
    check((string) $orders->detail(1, $id)['total_charge_minor'] === '102', '40 km release preserves order snapshots');
    check(OrderQuoteService::total('25000','1.2000','1.1000') === '33000', 'approved peak and light rain VND example');
    check(OrderQuoteService::total('129','1.2000','1.1000') === '171', 'approved peak and light rain USD ceiling');
    $migration = file_get_contents(__DIR__ . '/../database/migrations/20261105_distance_weight_pricing.sql');
    $db->exec(preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m', '', $migration));
    DistanceWeightPricingRelease::apply($db);
    $policies = $db->query('SELECT * FROM pricing_policies WHERE active ORDER BY currency_code')->fetchAll();
    check(count($policies) === 2 && $policies[0]['calculation_rule'] === 'BASE_PLUS_DISTANCE_PLUS_WEIGHT', 'new rule active in both currencies');
    $byCurrency = array_column($policies, null, 'currency_code');
    foreach ([['15.000','1.000','68000','339'],['20.000','1.000','88000','439'],['15.000','5.000','73000','359'],['20.000','5.000','93000','459'],['39.000','5.000','169000','839'],['4.500','3.500','27250','134']] as [$km,$kg,$vnd,$usd]) {
        foreach (['VND'=>$vnd,'USD'=>$usd] as $currency=>$expected) {
            $parts = DistanceWeightPrice::components($byCurrency[$currency], $km, $kg);
            check(OrderQuoteService::total($parts['subtotal_minor'],'1','1') === $expected, 'continuous distance and weight ' . $currency . ' ' . $km . '/' . $kg);
        }
    }
    $parts = DistanceWeightPrice::components($byCurrency['USD'],'4.500','3.500');
    check(OrderQuoteService::total($parts['subtotal_minor'],'1.2000','1.1000') === '177', 'fractional distance and weight round once');
    $parts = DistanceWeightPrice::components($byCurrency['VND'],'4.500','3.500');
    check(OrderQuoteService::total($parts['subtotal_minor'],'1.2000','1.1000') === '35970', 'fractional VND example');
    check(OrderQuoteService::total('99.001','1.0001','1.0001') === '100', 'retain fractional minor units through all multipliers');
    $parts = DistanceWeightPrice::components($byCurrency['VND'],'3.000','3.000');
    check($parts['extra_distance_km'] === '0.000' && $parts['extra_weight_kg'] === '0.000', 'included km and kg boundary');
    $provider->distance = '39.999'; $input['parcel']['weight_kg'] = '30.000';
    $newQuote = $quotes->create(1,$input);
    check($newQuote['snapshot']['components']['weight_component_minor'] === '67500.000', '30 kg allowed with exact weight snapshot');
    check($newQuote['snapshot']['components']['extra_distance_km'] === '36.999', 'distance snapshot exact to meter');
    $newOrder = $orders->create(1,$newQuote['quote_id'],'test-weight-policy-001')['order'];
    check($newOrder['pricing_snapshot']['components']['per_kg_minor'] === '2500', 'new order persists unit rates');
    check((string) $orders->detail(1,$id)['total_charge_minor'] === '102', 'old paid order unchanged by new rule');
    $input['parcel']['weight_kg'] = '30.001';
    rejects(fn()=>$quotes->create(1,$input),'WEIGHT_NOT_SUPPORTED');
    $input['parcel']['weight_kg'] = '0';
    rejects(fn()=>$quotes->create(1,$input),'VALIDATION_ERROR');
    $input['parcel']['weight_kg'] = '1.000'; $provider->distance = '40.000';
    rejects(fn()=>$quotes->create(1,$input),'OUTSIDE_SERVICE_AREA');
    check((int) scalar($db,"SELECT count(*) FROM audit_logs WHERE action = 'DISTANCE_WEIGHT_PRICING_RELEASE'") === 2, 'new policy release audit');
    // Model an existing deployment constraint, then verify the versioned migration twice.
    $db->exec("ALTER TABLE orders ADD CONSTRAINT legacy_language_currency CHECK ((app_language_at_order='vi' AND currency_code='VND') OR (app_language_at_order='en' AND currency_code='USD'))");
    $currencyMigration=file_get_contents(__DIR__.'/../database/migrations/20261111_customer_currency_choice.sql');
    $currencyMigration=preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m','',$currencyMigration);
    $db->exec($currencyMigration);$db->exec($currencyMigration);
    check((int)scalar($db,"SELECT count(*) FROM pg_constraint WHERE conrelid='orders'::regclass AND conname='legacy_language_currency'")===0,'currency choice migration removes only legacy coupling and is repeatable');
    $mixedUsd=$orders->create(1,$chosenUsd['quote_id'],'test-mixed-language-usd')['order'];
    $mixedVnd=$orders->create(2,$chosenVnd['quote_id'],'test-mixed-language-vnd')['order'];
    check($mixedUsd['currency_code']==='USD' && $mixedUsd['app_language_at_order']==='vi','Vietnamese account creates USD order with Vietnamese email language');
    check($mixedVnd['currency_code']==='VND' && $mixedVnd['app_language_at_order']==='en','English account creates VND order with English email language');
    $db->rollBack();
    echo json_encode(['result' => 'PASS', 'checks' => $checks, 'isolation' => 'temporary tables only; rolled back', 'persistent_business_writes' => 0], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
    fwrite(STDERR, get_class($e) . ': ' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL);
    exit(1);
}
