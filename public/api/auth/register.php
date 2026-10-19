<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../../../app/Services/IdentityDocumentVault.php';
require_once __DIR__ . '/../../../app/Services/PhoneVerificationService.php';

somi_api_require_post();
$input = somi_api_input();

$fullName = trim((string) ($input['full_name'] ?? ''));
$email = strtolower(trim((string) ($input['email'] ?? '')));
$phone = preg_replace('/[\s().-]+/', '', trim((string) ($input['phone'] ?? '')));
$password = (string) ($input['password'] ?? '');
$nationality = strtoupper(trim((string) ($input['nationality_code'] ?? '')));
$dateOfBirth = trim((string) ($input['date_of_birth'] ?? ''));
$gender = strtoupper(trim((string) ($input['gender'] ?? '')));
$language = strtolower(trim((string) ($input['app_language'] ?? 'en')));
$roleCode = strtoupper(trim((string) ($input['role'] ?? '')));
$documentType = strtoupper(trim((string) ($input['document_type'] ?? '')));
$documentCountry = strtoupper(trim((string) ($input['document_country_code'] ?? $nationality)));
$documentNumber = trim((string) ($input['document_number'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) > 150) {
    somi_api_json(422, ['error' => 'Enter a name up to 150 characters.']);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    somi_api_json(422, ['error' => 'Enter a valid email address.']);
}
if (!preg_match('/^\+[1-9]\d{7,14}$/', (string) $phone)) {
    somi_api_json(422, ['error' => 'Enter the phone number with country code, for example +84901234567.']);
}
if (strlen($password) < 8 || strlen($password) > 72) {
    somi_api_json(422, ['error' => 'Password must be 8–72 characters.']);
}
if (!preg_match('/^[A-Z]{2}$/', $nationality)) {
    somi_api_json(422, ['error' => 'Choose a valid nationality.']);
}
$birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateOfBirth);
if (!$birthDate || $birthDate->format('Y-m-d') !== $dateOfBirth || $birthDate > new DateTimeImmutable('today')) {
    somi_api_json(422, ['error' => 'Enter a valid date of birth.']);
}
if (!in_array($gender, ['M', 'F'], true)) {
    somi_api_json(422, ['error' => 'Choose a gender.']);
}
if (!in_array($language, ['vi', 'en'], true)) {
    somi_api_json(422, ['error' => 'Choose a supported app language.']);
}
if (!in_array($roleCode, ['CUSTOMER', 'SHIPPER'], true)) {
    somi_api_json(422, ['error' => 'Only Customer and Shipper accounts can be created here.']);
}
if (($nationality === 'VN' && $documentType !== 'NATIONAL_ID')
    || ($nationality !== 'VN' && !in_array($documentType, ['PASSPORT', 'FOREIGN_ID'], true))) {
    somi_api_json(422, ['error' => 'Choose the identity document type for your nationality.']);
}
if ($documentCountry !== $nationality) {
    somi_api_json(422, ['error' => 'Document issuing country must match nationality for this registration flow.']);
}
if (!preg_match('/^[A-Za-z0-9]{4,40}$/', $documentNumber)) {
    somi_api_json(422, ['error' => 'Identity document number must contain 4–40 letters or digits.']);
}
if (($input['terms_accepted'] ?? false) !== true) {
    somi_api_json(422, ['error' => 'Accept the Terms of Service and Privacy Policy to continue.']);
}

try {
    $db = somi_db();
    $db->beginTransaction();

    $userInsert = $db->prepare(
        "INSERT INTO users
            (full_name, email, phone, password_hash, nationality_code, date_of_birth, gender, app_language)
         VALUES
            (:full_name, :email, :phone, :password_hash, :nationality, :date_of_birth, :gender, :language)
         RETURNING user_id"
    );
    $userInsert->execute([
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'nationality' => $nationality,
        'date_of_birth' => $dateOfBirth,
        'gender' => $gender,
        'language' => $language,
    ]);
    $userId = (int) $userInsert->fetchColumn();

    $roleInsert = $db->prepare(
        'INSERT INTO user_roles (user_id, role_id)
         SELECT :user_id, role_id FROM roles WHERE role_code = :role_code'
    );
    $roleInsert->execute(['user_id' => $userId, 'role_code' => $roleCode]);
    if ($roleInsert->rowCount() !== 1) {
        throw new RuntimeException('Selected account role is unavailable.');
    }

    if ($roleCode === 'CUSTOMER') {
        $profileInsert = $db->prepare('INSERT INTO customer_profiles (user_id) VALUES (:user_id)');
        $profileInsert->execute(['user_id' => $userId]);
    }

    IdentityDocumentVault::storeForUser(
        $userId,
        $documentType,
        $documentCountry,
        $documentNumber,
        $roleCode === 'SHIPPER'
    );

    $db->commit();
    session_regenerate_id(true);
    $_SESSION['somi_user_id'] = $userId;
    $_SESSION['somi_role'] = strtolower($roleCode);

    $phoneCode = PhoneVerificationService::sendCode($userId);
    somi_api_json(201, [
        'status' => 'PHONE_VERIFICATION_REQUIRED',
        'delivery_mode' => 'DEMO',
        'demo_code' => $phoneCode,
    ]);
} catch (Throwable $error) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    somi_api_handle_error($error);
}
