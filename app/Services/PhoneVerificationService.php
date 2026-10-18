<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/database.php';

/** Demo phone OTP service. Real SMS delivery requires an SMS provider. */
final class PhoneVerificationService
{
    private const CODE_TTL_MINUTES = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public static function sendCode(int $userId): string
    {
        if (!self::demoModeEnabled()) {
            throw new RuntimeException('SMS delivery is not configured. Enable demo OTP mode or connect an SMS provider.');
        }

        $db = somi_db();
        $db->beginTransaction();
        try {
            $userQuery = $db->prepare(
                'SELECT phone, phone_verified_at FROM users WHERE user_id = :user_id FOR UPDATE'
            );
            $userQuery->execute(['user_id' => $userId]);
            $user = $userQuery->fetch();
            if (!$user) {
                throw new RuntimeException('Account was not found.');
            }
            if ($user['phone_verified_at'] !== null) {
                throw new RuntimeException('Phone number is already verified.');
            }

            $latestQuery = $db->prepare(
                "SELECT created_at FROM phone_verifications
                 WHERE user_id = :user_id AND purpose = 'VERIFY_PHONE'
                 ORDER BY created_at DESC LIMIT 1"
            );
            $latestQuery->execute(['user_id' => $userId]);
            $latest = $latestQuery->fetchColumn();
            if ($latest !== false) {
                $cooldown = $db->prepare(
                    'SELECT EXTRACT(EPOCH FROM (now() - CAST(:created_at AS timestamptz))) < :seconds'
                );
                $cooldown->execute(['created_at' => $latest, 'seconds' => self::RESEND_COOLDOWN_SECONDS]);
                if ($cooldown->fetchColumn()) {
                    throw new RuntimeException('Please wait before requesting another phone code.');
                }
            }

            $invalidate = $db->prepare(
                "UPDATE phone_verifications SET consumed_at = now()
                 WHERE user_id = :user_id AND purpose = 'VERIFY_PHONE' AND consumed_at IS NULL"
            );
            $invalidate->execute(['user_id' => $userId]);

            $code = (string) random_int(100000, 999999);
            $insert = $db->prepare(
                "INSERT INTO phone_verifications (user_id, phone, code_hash, purpose, expires_at)
                 VALUES (:user_id, :phone, :code_hash, 'VERIFY_PHONE',
                         now() + (CAST(:ttl_minutes AS integer) * interval '1 minute'))"
            );
            $insert->execute([
                'user_id' => $userId,
                'phone' => $user['phone'],
                'code_hash' => password_hash($code, PASSWORD_DEFAULT),
                'ttl_minutes' => self::CODE_TTL_MINUTES,
            ]);

            $db->commit();
            return $code;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function demoModeEnabled(): bool
    {
        if (strtolower(trim((string) getenv('SOMI_PHONE_OTP_MODE'))) === 'demo') {
            return true;
        }

        // Make local development usable without a separate environment setting.
        $host = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function verifyCode(int $userId, string $code): void
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException('Enter the six-digit phone code.');
        }

        $db = somi_db();
        $db->beginTransaction();
        try {
            $query = $db->prepare(
                "SELECT verification_id, code_hash, expires_at, attempt_count
                 FROM phone_verifications
                 WHERE user_id = :user_id AND purpose = 'VERIFY_PHONE' AND consumed_at IS NULL
                 ORDER BY created_at DESC LIMIT 1 FOR UPDATE"
            );
            $query->execute(['user_id' => $userId]);
            $verification = $query->fetch();
            if (!$verification) {
                throw new RuntimeException('No active phone code was found. Request a new code.');
            }
            if (strtotime((string) $verification['expires_at']) <= time()) {
                throw new RuntimeException('Phone code has expired. Request a new code.');
            }
            if ((int) $verification['attempt_count'] >= self::MAX_ATTEMPTS) {
                throw new RuntimeException('Too many incorrect attempts. Request a new code.');
            }

            if (!password_verify($code, (string) $verification['code_hash'])) {
                $wrongAttempt = $db->prepare(
                    'UPDATE phone_verifications SET attempt_count = LEAST(attempt_count + 1, :max_attempts)
                     WHERE verification_id = :verification_id'
                );
                $wrongAttempt->execute([
                    'max_attempts' => self::MAX_ATTEMPTS,
                    'verification_id' => $verification['verification_id'],
                ]);
                $db->commit();
                throw new RuntimeException('Phone code is incorrect.');
            }

            $consume = $db->prepare(
                'UPDATE phone_verifications SET consumed_at = now() WHERE verification_id = :verification_id'
            );
            $consume->execute(['verification_id' => $verification['verification_id']]);
            $activate = $db->prepare(
                "UPDATE users
                 SET phone_verified_at = now(),
                     account_status = CASE
                         WHEN email_verified_at IS NOT NULL AND account_status = 'PENDING_VERIFICATION'
                         THEN 'ACTIVE' ELSE account_status END,
                     updated_at = now()
                 WHERE user_id = :user_id"
            );
            $activate->execute(['user_id' => $userId]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
