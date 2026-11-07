<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || getenv('SOMI_DB_NAME') !== 'somi_person1_test' || getenv('SOMI_DB_HOST') !== '127.0.0.1') exit('Test database required.');
require __DIR__ . '/../app/Config/database.php';
$db = somi_db();
$suffix = bin2hex(random_bytes(5));
$password = bin2hex(random_bytes(16));
$fixtures = ['password' => $password, 'users' => []];
foreach (['customer','other','admin','shipper','shipper_reject'] as $index => $kind) {
    $email = "$kind-$suffix@example.invalid";
    $phone = '+84' . substr((string) hexdec($suffix), 0, 8) . $index;
    $q = $db->prepare("INSERT INTO users (full_name,email,phone,password_hash,nationality_code,date_of_birth,gender,app_language,account_status,email_verified_at,phone_verified_at)
        VALUES (:name,:email,:phone,:hash,'VN','1990-01-01','M','vi','ACTIVE',now(),now()) RETURNING user_id");
    $q->execute(['name' => 'Person1 Test ' . $kind, 'email' => $email, 'phone' => $phone, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int) $q->fetchColumn();
    $role = $kind === 'other' ? 'CUSTOMER' : (str_starts_with($kind, 'shipper') ? 'SHIPPER' : strtoupper($kind));
    $q = $db->prepare('INSERT INTO user_roles(user_id,role_id) SELECT :id,role_id FROM roles WHERE role_code = :role');
    $q->execute(['id' => $id, 'role' => $role]);
    if ($role === 'CUSTOMER') $db->exec("INSERT INTO customer_profiles(user_id) VALUES ($id)");
    if ($role === 'SHIPPER') {
        $db->exec("INSERT INTO shipper_profiles(user_id,vehicle_type,vehicle_registration) VALUES ($id,'TEST','TEST-$suffix-$index')");
        $q = $db->prepare("INSERT INTO user_identity_documents(user_id,document_type,document_country_code,identity_number_ciphertext,review_status)
            VALUES (:id,'NATIONAL_ID','VN','TEST-ONLY-NOT-A-REAL-DOCUMENT','PENDING')");
        $q->execute(['id' => $id]);
    }
    $fixtures['users'][$kind] = ['id' => $id, 'email' => $email, 'phone' => $phone];
}
$q = $db->prepare("INSERT INTO parcel_categories(category_code,name_vi,name_en) VALUES (:code,'Test category','Test category') RETURNING category_id");
$q->execute(['code' => 'TEST-' . $suffix]);
$fixtures['category_id'] = (int) $q->fetchColumn();
if (!is_dir(__DIR__ . '/.runtime')) mkdir(__DIR__ . '/.runtime', 0700, true);
file_put_contents(__DIR__ . '/.runtime/person1-fixtures.json', json_encode($fixtures, JSON_THROW_ON_ERROR));
echo "Isolated test fixtures created.\n";
