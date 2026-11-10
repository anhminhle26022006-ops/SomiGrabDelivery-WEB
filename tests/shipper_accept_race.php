<?php
declare(strict_types=1);
// Kiểm thử tranh chấp THẬT bằng nhiều tiến trình PHP chạy song song:
//   1) hai Shipper cùng nhận một đơn, 2) nhận đơn đua với Customer hủy đơn.
// Cần bảng thật (không dùng được TEMP TABLE vì mỗi tiến trình là một kết nối riêng), nên chỉ chạy
// trên database thử nghiệm cục bộ: SOMI_DB_NAME=somi_shipper_test, SOMI_DB_HOST=127.0.0.1.
if (PHP_SAPI !== 'cli' || getenv('SOMI_DB_NAME') !== 'somi_shipper_test' || getenv('SOMI_DB_HOST') !== '127.0.0.1') {
    exit("Local test database required (SOMI_DB_NAME=somi_shipper_test, SOMI_DB_HOST=127.0.0.1).\n");
}
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/OfferAcceptService.php';
require_once __DIR__ . '/../app/Services/CustomerOrderService.php';
$db = somi_db();

// ----- Tiến trình con: chờ hiệu lệnh rồi thực hiện đúng một thao tác trong một transaction -----
if (in_array($argv[1] ?? '', ['accept', 'cancel'], true)) {
    echo "READY\n"; flush(); fgets(STDIN);
    try {
        $db->beginTransaction();
        if ($argv[1] === 'accept') (new OfferAcceptService($db))->accept((int) $argv[2], (int) $argv[3]);
        else (new CustomerOrderService($db))->cancel((int) $argv[2], (int) $argv[3], 'Race test');
        $db->commit();
        echo "OK\n";
    } catch (DeliveryApiError $e) {
        $db->rollBack(); echo $e->errorCode . "\n";
    } catch (PDOException $e) {
        // Kernel API đổi các mã này thành 409 CONCURRENT_CHANGE; ở đây in ra để bài test nhìn thấy.
        $db->rollBack(); echo 'SQLSTATE_' . $e->getCode() . "\n";
    }
    exit;
}

// ----- Tiến trình chính -----
function one(PDO $db, string $sql, array $args = []) { $q = $db->prepare($sql); $q->execute($args); return $q->fetchColumn(); }

/** Chạy các tiến trình con cùng lúc và trả về kết quả của từng tiến trình theo thứ tự. */
function race(array $commands): array
{
    $procs = [];
    foreach ($commands as $args) {
        $proc = proc_open(array_merge([PHP_BINARY, __FILE__], array_map('strval', $args)), [['pipe', 'r'], ['pipe', 'w'], STDERR], $pipes);
        fgets($pipes[1]); // chờ READY: kết nối database đã mở xong
        $procs[] = [$proc, $pipes];
    }
    foreach ($procs as [, $pipes]) { fwrite($pipes[0], "\n"); fflush($pipes[0]); } // hiệu lệnh xuất phát
    $results = [];
    foreach ($procs as [$proc, $pipes]) { $results[] = trim((string) fgets($pipes[1])); fclose($pipes[0]); fclose($pipes[1]); proc_close($proc); }
    return $results;
}

if (!one($db, "SELECT to_regclass('somi.users')")) {
    $db->exec(file_get_contents(__DIR__ . '/../database/SOMI_database.sql'));
    $db->exec('SET search_path TO somi, public');
    foreach (['20261105_customer_orders', '20261106_person1_backend', '20261110_shipper_delivery'] as $migration) {
        $db->exec(file_get_contents(__DIR__ . '/../database/migrations/' . $migration . '.sql'));
    }
}
$run = bin2hex(random_bytes(4));
$user = function (string $role) use ($db, $run): int {
    static $n = 0; $n++;
    $id = (int) one($db, "INSERT INTO users (full_name, email, phone, password_hash, nationality_code, date_of_birth, gender, app_language, account_status, email_verified_at, phone_verified_at)
        VALUES ('Race $role', ?, ?, 'test', 'VN', '2000-01-01', 'F', 'vi', 'ACTIVE', now(), now()) RETURNING user_id", ["race-$run-$n@example.invalid", substr((string) hexdec($run), 0, 8) . sprintf('%02d', $n)]);
    $db->prepare('INSERT INTO user_roles (user_id, role_id) SELECT ?, role_id FROM roles WHERE role_code = ?')->execute([$id, $role]);
    if ($role === 'CUSTOMER') $db->prepare('INSERT INTO customer_profiles (user_id) VALUES (?)')->execute([$id]);
    else {
        $db->prepare("INSERT INTO shipper_profiles (user_id, vehicle_type, vehicle_registration, verification_status, availability, last_latitude, last_longitude, last_location_at) VALUES (?, 'Xe máy', ?, 'APPROVED', TRUE, 10.8, 106.68, now())")->execute([$id, "RACE-$run-$n"]);
        $db->prepare("INSERT INTO user_identity_documents (user_id, document_type, document_country_code, identity_number_ciphertext, review_status) VALUES (?, 'NATIONAL_ID', 'VN', 'TEST-NOT-REAL', 'APPROVED')")->execute([$id]);
    }
    return $id;
};
$category = one($db, 'SELECT category_id FROM parcel_categories LIMIT 1') ?: one($db, "INSERT INTO parcel_categories (category_code, name_vi, name_en) VALUES ('RACE_TEST', 'Thử nghiệm', 'Test') RETURNING category_id");
/** Đơn đã thanh toán kèm offer đang chờ cho từng Shipper. */
$order = function (int $customer, array $shippers) use ($db, $run, $category): array {
    static $n = 0; $n++;
    $id = (int) one($db, "INSERT INTO orders (order_code, customer_id, status, currency_code, app_language_at_order, distance_km, pickup_address, pickup_latitude, pickup_longitude, sender_name, sender_phone,
            delivery_address, delivery_latitude, delivery_longitude, receiver_name, receiver_phone, category_id, estimated_weight_kg, distance_fee_minor, total_charge_minor, paid_at)
        VALUES (?, ?, 'SEARCHING_SHIPPER', 'VND', 'vi', 3, 'A', 10.799, 106.68, 'S', '0900000000', 'B', 10.776, 106.688, 'R', '0900000001', ?, 1, 30000, 30000, now()) RETURNING order_id", ["RACE-$run-$n", $customer, $category]);
    $db->prepare("INSERT INTO payments (order_id, amount_minor, currency_code, method, provider, status, paid_at) VALUES (?, 30000, 'VND', 'GATEWAY', 'TEST', 'PAID', now())")->execute([$id]);
    $offers = [];
    foreach ($shippers as $shipper) $offers[$shipper] = (int) one($db, "INSERT INTO order_match_offers (order_id, shipper_id, expires_at) VALUES (?, ?, now() + interval '60 seconds') RETURNING offer_id", [$id, $shipper]);
    return [$id, $offers];
};

$checks = 0;
$assert = function (bool $ok, string $label) use (&$checks): void { $checks++; if (!$ok) { fwrite(STDERR, "FAILED: $label\n"); exit(1); } };
$customer = $user('CUSTOMER');

// 1) Hai Shipper cùng nhận: lặp nhiều vòng để tăng khả năng hai transaction thật sự chồng nhau.
for ($round = 1; $round <= 8; $round++) {
    $a = $user('SHIPPER'); $b = $user('SHIPPER');
    [$orderId, $offers] = $order($customer, [$a, $b]);
    $results = race([['accept', $a, $offers[$a]], ['accept', $b, $offers[$b]]]);
    sort($results);
    $assert($results === ['OFFER_LOST_RACE', 'OK'], "round $round: exactly one winner, got " . implode('/', $results));
    $assert((int) one($db, "SELECT count(*) FROM order_assignments WHERE order_id = ? AND assignment_status = 'ACTIVE'", [$orderId]) === 1, "round $round: one active assignment");
    $assert((int) one($db, 'SELECT count(*) FROM deliveries WHERE order_id = ?', [$orderId]) === 1, "round $round: one delivery record");
    $assert(one($db, "SELECT string_agg(status, ',' ORDER BY status) FROM order_match_offers WHERE order_id = ?", [$orderId]) === 'ACCEPTED,LOST_RACE', "round $round: offers settled");
}

// 2) Nhận đơn đua với hủy đơn: bên nào thắng cũng được, miễn dữ liệu nhất quán.
$outcomes = [];
for ($round = 1; $round <= 8; $round++) {
    $a = $user('SHIPPER');
    [$orderId, $offers] = $order($customer, [$a]);
    [$accept, $cancel] = race([['accept', $a, $offers[$a]], ['cancel', $customer, $orderId]]);
    $status = one($db, 'SELECT status FROM orders WHERE order_id = ?', [$orderId]);
    $assignments = (int) one($db, 'SELECT count(*) FROM order_assignments WHERE order_id = ?', [$orderId]);
    if ($accept === 'OK') $assert($cancel === 'ORDER_NOT_CANCELLABLE' && $status === 'SHIPPER_ASSIGNED' && $assignments === 1, "round $round: accept won but state is $status/$cancel");
    else $assert($cancel === 'OK' && $status === 'CANCELLED' && $assignments === 0 && in_array($accept, ['OFFER_NOT_PENDING', 'ORDER_NOT_AVAILABLE'], true), "round $round: cancel won but state is $status/$accept");
    $outcomes[] = $accept === 'OK' ? 'accept' : 'cancel';
}
echo "Shipper race checks passed: $checks (accept-vs-cancel winners: " . implode(',', $outcomes) . ")\n";
