<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/database.php';

/** Encrypts identity numbers before they are written to PostgreSQL. */
final class IdentityDocumentVault
{
    private const CIPHER = 'aes-256-gcm';
    private const KEY_VERSION = 1;

    /** Return the versioned encrypted representation for database storage. */
    public static function encrypt(string $identityNumber): string
    {
        $identityNumber = trim($identityNumber);
        if ($identityNumber === '') {
            throw new InvalidArgumentException('Identity document number is required.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $identityNumber,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'SOMI-IDENTITY-v' . self::KEY_VERSION,
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Could not encrypt the identity document number.');
        }

        return 'v' . self::KEY_VERSION . ':' . base64_encode($nonce . $tag . $ciphertext);
    }

    /** Store one encrypted identity number; Shippers start in manual review. */
    public static function storeForUser(
        int $userId,
        string $documentType,
        string $documentCountryCode,
        string $identityNumber,
        bool $requiresShipperReview
    ): void {
        $documentType = strtoupper(trim($documentType));
        $documentCountryCode = strtoupper(trim($documentCountryCode));
        if (!in_array($documentType, ['NATIONAL_ID', 'PASSPORT', 'FOREIGN_ID'], true)) {
            throw new InvalidArgumentException('Identity document type is invalid.');
        }
        if (!preg_match('/^[A-Z]{2}$/', $documentCountryCode)) {
            throw new InvalidArgumentException('Identity document country code must be ISO 3166-1 alpha-2.');
        }
        if (!preg_match('/^[A-Za-z0-9]{4,40}$/', trim($identityNumber))) {
            throw new InvalidArgumentException('Identity document number must contain 4–40 letters or digits.');
        }

        $statement = somi_db()->prepare(
            'INSERT INTO user_identity_documents
                (user_id, document_type, document_country_code, identity_number_ciphertext, review_status)
             VALUES (:user_id, :document_type, :country_code, :ciphertext, :review_status)'
        );
        $statement->execute([
            'user_id' => $userId,
            'document_type' => $documentType,
            'country_code' => $documentCountryCode,
            'ciphertext' => self::encrypt($identityNumber),
            'review_status' => $requiresShipperReview ? 'PENDING' : 'NOT_REQUIRED',
        ]);
    }

    /** Reveal a number only after checking the Admin permission and recording the access. */
    public static function revealForAdmin(int $adminUserId, int $targetUserId): string
    {
        $db = somi_db();
        $db->beginTransaction();
        try {
            $documentQuery = $db->prepare(
                "SELECT d.identity_document_id, d.identity_number_ciphertext
                 FROM user_identity_documents d
                 JOIN user_roles ur ON ur.user_id = :admin_user_id
                 JOIN roles r ON r.role_id = ur.role_id AND r.role_code = 'ADMIN'
                 JOIN role_permissions rp ON rp.role_id = r.role_id
                 JOIN permissions p ON p.permission_id = rp.permission_id
                                      AND p.permission_code = 'identity.read_sensitive'
                 WHERE d.user_id = :target_user_id
                 FOR UPDATE OF d"
            );
            $documentQuery->execute([
                'admin_user_id' => $adminUserId,
                'target_user_id' => $targetUserId,
            ]);
            $document = $documentQuery->fetch();
            if (!$document) {
                throw new RuntimeException('Identity document is unavailable or access is not permitted.');
            }

            $audit = $db->prepare(
                "INSERT INTO identity_document_access_logs
                    (identity_document_id, admin_user_id, target_user_id, action)
                 VALUES (:document_id, :admin_user_id, :target_user_id, 'VIEW_FULL')"
            );
            $audit->execute([
                'document_id' => $document['identity_document_id'],
                'admin_user_id' => $adminUserId,
                'target_user_id' => $targetUserId,
            ]);
            $plaintext = self::decrypt((string) $document['identity_number_ciphertext']);
            $db->commit();
            return $plaintext;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function decrypt(string $storedValue): string
    {
        if (!preg_match('/^v(\d+):([A-Za-z0-9+\/=]+)$/', $storedValue, $matches)
            || (int) $matches[1] !== self::KEY_VERSION) {
            throw new RuntimeException('Identity document encryption version is unsupported.');
        }

        $payload = base64_decode($matches[2], true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Stored identity document data is invalid.');
        }

        $nonce = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $ciphertext = substr($payload, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::encryptionKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'SOMI-IDENTITY-v' . self::KEY_VERSION
        );
        if ($plaintext === false) {
            throw new RuntimeException('Could not decrypt the identity document number.');
        }

        return $plaintext;
    }

    private static function encryptionKey(): string
    {
        $encodedKey = getenv('SOMI_IDENTITY_ENCRYPTION_KEY');
        $key = $encodedKey === false ? false : base64_decode($encodedKey, true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('SOMI_IDENTITY_ENCRYPTION_KEY must be a base64-encoded 32-byte key.');
        }
        return $key;
    }
}
