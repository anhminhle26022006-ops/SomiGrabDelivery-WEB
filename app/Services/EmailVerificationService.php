<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/ResendMailer.php';

/** Issues and verifies six-digit email codes for an existing SOMI account. */
final class EmailVerificationService
{
    private const CODE_TTL_MINUTES = 5;
    private const RESEND_COOLDOWN_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    /** Send a code to the account's current email address. */
    public static function sendCode(int $userId): void
    {
        $db = somi_db();
        $db->beginTransaction();

        try {
            $userQuery = $db->prepare(
                'SELECT email, nationality_code, email_verified_at
                 FROM users WHERE user_id = :user_id FOR UPDATE'
            );
            $userQuery->execute(['user_id' => $userId]);
            $user = $userQuery->fetch();
            if (!$user) {
                throw new RuntimeException('Account was not found.');
            }
            if ($user['email_verified_at'] !== null) {
                throw new RuntimeException('Email address is already verified.');
            }

            $cooldownQuery = $db->prepare(
                "SELECT created_at
                 FROM email_verifications
                 WHERE user_id = :user_id AND purpose = 'VERIFY_EMAIL'
                 ORDER BY created_at DESC
                 LIMIT 1"
            );
            $cooldownQuery->execute(['user_id' => $userId]);
            $lastIssuedAt = $cooldownQuery->fetchColumn();
            if ($lastIssuedAt !== false) {
                $cooldownCheck = $db->prepare(
                    'SELECT EXTRACT(EPOCH FROM (now() - CAST(:created_at AS timestamptz))) < :cooldown'
                );
                $cooldownCheck->execute([
                    'created_at' => $lastIssuedAt,
                    'cooldown' => self::RESEND_COOLDOWN_SECONDS,
                ]);
                if ($cooldownCheck->fetchColumn()) {
                    throw new RuntimeException('Please wait before requesting another email code.');
                }
            }

            // Invalidate any earlier unconsumed code before issuing a replacement.
            $invalidate = $db->prepare(
                "UPDATE email_verifications
                 SET consumed_at = now()
                 WHERE user_id = :user_id AND purpose = 'VERIFY_EMAIL' AND consumed_at IS NULL"
            );
            $invalidate->execute(['user_id' => $userId]);

            $code = (string) random_int(100000, 999999);
            $insert = $db->prepare(
                "INSERT INTO email_verifications
                    (user_id, email, token_hash, purpose, attempt_count, expires_at)
                 VALUES
                    (:user_id, :email, :token_hash, 'VERIFY_EMAIL', 0,
                     now() + (CAST(:ttl_minutes AS integer) * interval '1 minute'))"
            );
            $insert->execute([
                'user_id' => $userId,
                'email' => $user['email'],
                'token_hash' => password_hash($code, PASSWORD_DEFAULT),
                'ttl_minutes' => self::CODE_TTL_MINUTES,
            ]);

            $isVietnamese = strtoupper(trim((string) $user['nationality_code'])) === 'VN';
            $subject = $isVietnamese ? 'Mã xác minh email SOMI' : 'Your SOMI email verification code';
            $safeCode = htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html = $isVietnamese
                ? '<p>Mã xác minh email SOMI của bạn là:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . $safeCode . '</p><p>Mã có hiệu lực trong 5 phút. Nếu bạn không yêu cầu mã này, hãy bỏ qua email.</p>'
                : '<p>Your SOMI email verification code is:</p><p style="font-size:28px;font-weight:bold;letter-spacing:6px">' . $safeCode . '</p><p>This code expires in 5 minutes. If you did not request it, you can ignore this email.</p>';
            $text = $isVietnamese
                ? "Mã xác minh email SOMI: {$code}. Mã có hiệu lực trong 5 phút."
                : "Your SOMI email verification code is {$code}. It expires in 5 minutes.";

            ResendMailer::send((string) $user['email'], $subject, $html, $text);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    /** Verify the latest code and update the account's verification status. */
    public static function verifyCode(int $userId, string $code): void
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            throw new InvalidArgumentException('Enter the six-digit email code.');
        }

        $db = somi_db();
        $db->beginTransaction();

        try {
            $query = $db->prepare(
                "SELECT verification_id, token_hash, expires_at, attempt_count
                 FROM email_verifications
                 WHERE user_id = :user_id AND purpose = 'VERIFY_EMAIL'
                   AND consumed_at IS NULL
                 ORDER BY created_at DESC
                 LIMIT 1 FOR UPDATE"
            );
            $query->execute(['user_id' => $userId]);
            $verification = $query->fetch();

            if (!$verification) {
                throw new RuntimeException('No active email code was found. Request a new code.');
            }
            if (strtotime((string) $verification['expires_at']) <= time()) {
                throw new RuntimeException('Email code has expired. Request a new code.');
            }
            if ((int) $verification['attempt_count'] >= self::MAX_ATTEMPTS) {
                throw new RuntimeException('Too many incorrect attempts. Request a new code.');
            }

            if (!password_verify($code, (string) $verification['token_hash'])) {
                $wrongAttempt = $db->prepare(
                    'UPDATE email_verifications
                     SET attempt_count = LEAST(attempt_count + 1, :max_attempts)
                     WHERE verification_id = :verification_id'
                );
                $wrongAttempt->execute([
                    'max_attempts' => self::MAX_ATTEMPTS,
                    'verification_id' => $verification['verification_id'],
                ]);
                $db->commit();
                throw new RuntimeException('Email code is incorrect.');
            }

            $consume = $db->prepare(
                'UPDATE email_verifications SET consumed_at = now() WHERE verification_id = :verification_id'
            );
            $consume->execute(['verification_id' => $verification['verification_id']]);

            $activate = $db->prepare(
                "UPDATE users
                 SET email_verified_at = now(),
                     account_status = CASE
                         WHEN phone_verified_at IS NOT NULL AND account_status = 'PENDING_VERIFICATION'
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
