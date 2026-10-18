<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/ResendMailer.php';

final class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 30;
    private const REQUEST_COOLDOWN_SECONDS = 60;

    public static function request(string $email, string $language = 'en'): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }

        $db = somi_db();
        $userQuery = $db->prepare(
            "SELECT user_id, email FROM users
             WHERE lower(email) = lower(:email) AND account_status = 'ACTIVE'
               AND email_verified_at IS NOT NULL
             LIMIT 1"
        );
        $userQuery->execute(['email' => $email]);
        $user = $userQuery->fetch();
        if (!$user) {
            return;
        }

        $db->beginTransaction();
        try {
            $latestQuery = $db->prepare(
                'SELECT created_at FROM password_reset_tokens
                 WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1'
            );
            $latestQuery->execute(['user_id' => $user['user_id']]);
            $latest = $latestQuery->fetchColumn();
            if ($latest !== false) {
                $cooldownQuery = $db->prepare(
                    'SELECT EXTRACT(EPOCH FROM (now() - CAST(:created_at AS timestamptz))) < :cooldown'
                );
                $cooldownQuery->execute([
                    'created_at' => $latest,
                    'cooldown' => self::REQUEST_COOLDOWN_SECONDS,
                ]);
                $isCoolingDown = $cooldownQuery->fetchColumn();
                if ($isCoolingDown === true || $isCoolingDown === 't' || $isCoolingDown === '1') {
                    $db->commit();
                    return;
                }
            }

            $invalidate = $db->prepare(
                'UPDATE password_reset_tokens SET consumed_at = now()
                 WHERE user_id = :user_id AND consumed_at IS NULL'
            );
            $invalidate->execute(['user_id' => $user['user_id']]);

            $token = bin2hex(random_bytes(32));
            $insert = $db->prepare(
                "INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:user_id, :token_hash, now() + make_interval(mins => CAST(:ttl AS integer)))"
            );
            $insert->execute([
                'user_id' => $user['user_id'],
                'token_hash' => hash('sha256', $token),
                'ttl' => (string) self::TOKEN_TTL_MINUTES,
            ]);

            $url = self::baseUrl() . '/?page=reset-password&token=' . rawurlencode($token);
            $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $isVietnamese = $language === 'vi';
            $subject = $isVietnamese ? 'Đặt lại mật khẩu SOMI' : 'Reset your SOMI password';
            $html = $isVietnamese
                ? '<p>Nhấn nút bên dưới để đặt mật khẩu mới cho tài khoản SOMI. Liên kết có hiệu lực trong 30 phút.</p><p><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#14284b;color:#fff;border-radius:8px;text-decoration:none">Đặt lại mật khẩu</a></p><p>Nếu bạn không yêu cầu thao tác này, hãy bỏ qua email.</p>'
                : '<p>Use the button below to choose a new password for your SOMI account. This link expires in 30 minutes.</p><p><a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#14284b;color:#fff;border-radius:8px;text-decoration:none">Reset password</a></p><p>If you did not request this, you can ignore this email.</p>';
            $text = $isVietnamese
                ? "Đặt lại mật khẩu SOMI tại đây: {$url}\nLiên kết có hiệu lực trong 30 phút."
                : "Reset your SOMI password here: {$url}\nThis link expires in 30 minutes.";

            ResendMailer::send((string) $user['email'], $subject, $html, $text);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function reset(string $token, string $password): void
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw new InvalidArgumentException('This reset link is invalid or has expired. Request a new one.');
        }
        if (strlen($password) < 8 || strlen($password) > 72) {
            throw new InvalidArgumentException('Password must be 8–72 characters.');
        }

        $db = somi_db();
        $db->beginTransaction();
        try {
            $lookup = $db->prepare(
                'SELECT reset_token_id, user_id FROM password_reset_tokens
                 WHERE token_hash = :token_hash AND consumed_at IS NULL AND expires_at > now()
                 FOR UPDATE'
            );
            $lookup->execute(['token_hash' => hash('sha256', $token)]);
            $row = $lookup->fetch();
            if (!$row) {
                throw new InvalidArgumentException('This reset link is invalid or has expired. Request a new one.');
            }

            $updatePassword = $db->prepare(
                'UPDATE users SET password_hash = :password_hash, password_changed_at = now(), updated_at = now()
                 WHERE user_id = :user_id'
            );
            $updatePassword->execute([
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'user_id' => $row['user_id'],
            ]);
            $consume = $db->prepare(
                'UPDATE password_reset_tokens SET consumed_at = now()
                 WHERE user_id = :user_id AND consumed_at IS NULL'
            );
            $consume->execute(['user_id' => $row['user_id']]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function baseUrl(): string
    {
        $configured = getenv('SOMI_APP_URL');
        if ($configured !== false && trim($configured) !== '') {
            return rtrim(trim($configured), '/');
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        if (preg_match('/^(localhost|127\.0\.0\.1)(:\d{1,5})?$/', $host)) {
            $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
            return $scheme . '://' . $host;
        }
        return 'https://somigrabdelivery-website.onrender.com';
    }
}
