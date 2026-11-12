<?php
declare(strict_types=1);
// Dữ liệu dùng chung cho các bài kiểm thử của Người 2. Mọi bảng là TEMP TABLE của phiên kết nối
// (search_path = pg_temp), nên chạy trên database nào cũng không ghi vào dữ liệu thật.
if (PHP_SAPI !== 'cli') exit(1);
require_once __DIR__ . '/../app/Config/database.php';
require_once __DIR__ . '/../app/Services/DeliveryApiError.php';

$checks = 0;
function check(bool $ok, string $label): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('FAILED: ' . $label); }
function value(PDO $db, string $sql, array $args = []) { $q = $db->prepare($sql); $q->execute($args); return $q->fetchColumn(); }
function tx(PDO $db, callable $fn) { $db->beginTransaction(); try { $r = $fn(); $db->commit(); return $r; } catch (Throwable $e) { $db->rollBack(); throw $e; } }
function rejects(callable $fn, string $code): void
{
    try { $fn(); } catch (DeliveryApiError $e) { check($e->errorCode === $code, "expected $code, got " . $e->errorCode); return; }
    throw new RuntimeException('Expected ' . $code);
}

const ADMIN = 1, CUSTOMER = 2, SHIPPER_A = 3, SHIPPER_B = 4, SHIPPER_PENDING = 5;
// Điểm lấy hàng mẫu ở TP. Hồ Chí Minh. A cách khoảng 0,5 km, B khoảng 1,5 km.
const PICKUP = ['10.7990000', '106.6800000'];
const NEAR = ['10.8035000', '106.6800000'];
const FARTHER = ['10.8125000', '106.6800000'];

function shipper_test_db(): PDO
{
    $db = somi_db();
    $db->exec('SET search_path TO pg_temp, pg_catalog');
    $sql = file_get_contents(__DIR__ . '/../database/SOMI_database.sql');
    $sql = preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^CREATE SCHEMA[^;]+;\s*|^SET search_path[^;]+;\s*/m', '', $sql);
    $sql = preg_replace('/CREATE FUNCTION erase_identity_document_on_account_close\(\).*?(?=CREATE TABLE shipper_score_policies)/s', '', $sql);
    $db->exec(str_replace('CREATE TABLE ', 'CREATE TEMP TABLE ', $sql));
    foreach (['20261105_customer_orders', '20261106_person1_backend', '20261110_shipper_delivery', '20261111_person3', '20261111_person3_complete', '20261111_person3_supabase','20261112_shipper_policy_workflows'] as $migration) {
        $sql = file_get_contents(__DIR__ . '/../database/migrations/' . $migration . '.sql');
        $sql = preg_replace('/^BEGIN;\s*|^COMMIT;\s*|^SET LOCAL search_path[^;]+;\s*/m', '', $sql);
        $db->exec(str_replace('CREATE TABLE ', 'CREATE TEMP TABLE ', $sql));
    }
    check(value($db, "SELECT count(*) FROM pg_class WHERE relnamespace = pg_my_temp_schema() AND relname IN ('orders','order_match_offers','order_assignments','deliveries','shipper_locations')") == 5, 'all writes use temporary tables');

    $names = ['Admin', 'Customer', 'Shipper A', 'Shipper B', 'Shipper Pending'];
    foreach ($names as $i => $name) {
        $db->prepare("INSERT INTO users (full_name, email, phone, password_hash, nationality_code, date_of_birth, gender, app_language, account_status, email_verified_at, phone_verified_at)
                      VALUES (?, ?, ?, 'test', 'VN', '2000-01-01', 'F', 'vi', 'ACTIVE', now(), now())")
           ->execute([$name, 'user' . ($i + 1) . '@example.invalid', '09000000' . sprintf('%02d', $i + 1)]);
    }
    $db->exec("INSERT INTO user_roles (user_id, role_id) SELECT 1, role_id FROM roles WHERE role_code = 'ADMIN'");
    $db->exec("INSERT INTO user_roles (user_id, role_id) SELECT 2, role_id FROM roles WHERE role_code = 'CUSTOMER'");
    $db->exec("INSERT INTO user_roles (user_id, role_id) SELECT u, role_id FROM roles, unnest(ARRAY[3,4,5]) AS u WHERE role_code = 'SHIPPER'");
    $db->exec('INSERT INTO customer_profiles (user_id) VALUES (2)');
    $db->exec("INSERT INTO shipper_profiles (user_id, vehicle_type, vehicle_registration, verification_status, approved_at, approved_by) VALUES
               (3, 'Xe máy', '59X1-00003', 'APPROVED', now(), 1), (4, 'Xe máy', '59X1-00004', 'APPROVED', now(), 1), (5, 'Xe máy', '59X1-00005', 'NEEDS_INFO', NULL, NULL)");
    $db->exec("INSERT INTO user_identity_documents (user_id, document_type, document_country_code, identity_number_ciphertext, review_status, rejection_reason) VALUES
               (3, 'NATIONAL_ID', 'VN', 'TEST-NOT-REAL', 'APPROVED', NULL), (4, 'NATIONAL_ID', 'VN', 'TEST-NOT-REAL', 'APPROVED', NULL), (5, 'NATIONAL_ID', 'VN', 'TEST-NOT-REAL', 'NEEDS_INFO', 'Ảnh mờ')");
    $db->exec("INSERT INTO parcel_categories (category_code, name_vi, name_en) VALUES ('TEST_DOCS', 'Tài liệu', 'Documents')");
    putenv('SOMI_IDENTITY_ENCRYPTION_KEY=' . base64_encode(random_bytes(32)));
    return $db;
}

/** Giả lập kết quả của Người 1: một đơn đã thanh toán, đang SEARCHING_SHIPPER, kèm sự kiện OrderReadyForMatching. */
function paid_order(PDO $db): int
{
    static $n = 0; $n++;
    $q = $db->prepare("INSERT INTO orders (order_code, customer_id, status, currency_code, app_language_at_order, distance_km, pickup_address, pickup_latitude, pickup_longitude,
            sender_name, sender_phone, delivery_address, delivery_latitude, delivery_longitude, receiver_name, receiver_phone, category_id, estimated_weight_kg,
            distance_fee_minor, total_charge_minor, return_fee_minor, paid_at)
        VALUES (?, 2, 'SEARCHING_SHIPPER', 'VND', 'vi', 3.2, '12 Nguyễn Văn Trỗi', ?, ?, 'Người gửi', '0900000002', '241 Võ Văn Tần', 10.7760000, 106.6880000,
            'Người nhận', '0900000099', 1, 1.5, 30000, 30000, 10000, now()) RETURNING order_id");
    $q->execute(['SOMI-TEST-' . $n, PICKUP[0], PICKUP[1]]);
    $id = (int) $q->fetchColumn();
    $db->prepare("INSERT INTO payments (order_id, amount_minor, currency_code, method, provider, status, paid_at) VALUES (?, 30000, 'VND', 'GATEWAY', 'TEST', 'PAID', now())")->execute([$id]);
    $db->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, actor_kind) VALUES (?, 'PENDING_PAYMENT', 'SEARCHING_SHIPPER', 'PROVIDER')")->execute([$id]);
    $db->prepare("INSERT INTO order_events (order_id, event_type, deduplication_key, payload) VALUES (?, 'OrderReadyForMatching', ?, '{}')")->execute([$id, 'matching:paid:' . $id]);
    return $id;
}

/** Metadata fixture only: every insert remains in this connection's TEMP schema. */
function storage_file(PDO $db,int $owner,string $purpose,?int $order=null,string $mime='image/jpeg'):int {
    $q=$db->prepare("INSERT INTO private_files(owner_id,order_id,purpose,object_key,original_name,mime_type,byte_size,checksum_sha256) VALUES(?,?,?,?,?,?,?,?) RETURNING file_id");
    $key=bin2hex(random_bytes(32));$q->execute([$owner,$order,$purpose,$key,'fixture.jpg',$mime,512000,hash('sha256',$key)]);return (int)$q->fetchColumn();
}
