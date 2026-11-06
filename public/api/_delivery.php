<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../app/Services/SessionGuard.php';
require_once __DIR__ . '/../../app/Services/CustomerOrderService.php';

function delivery_body(): array
{
    if (!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/iD', trim($_SERVER['CONTENT_TYPE'] ?? ''))) {
        throw new DeliveryApiError(415, 'JSON_REQUIRED', 'Content-Type must be application/json.');
    }
    $stream = fopen('php://input', 'rb');
    $body = stream_get_contents($stream, 65537);
    fclose($stream);
    if (strlen($body) > 65536) throw new DeliveryApiError(413, 'BODY_TOO_LARGE', 'JSON body exceeds 64 KiB.');
    try { $data = json_decode($body, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new DeliveryApiError(400, 'INVALID_JSON', 'Request body must be valid JSON.'); }
    if (!is_array($data) || !str_starts_with(ltrim($body), '{')) throw new DeliveryApiError(400, 'INVALID_JSON', 'Request body must be a JSON object.');
    return $data;
}

function delivery_query_id(string $name = 'id', bool $optional = false): ?int
{
    if ($optional && !isset($_GET[$name])) return null;
    return DeliveryInput::id($_GET[$name] ?? null, $name);
}

function delivery_wire(array $data): array
{
    foreach ($data as $key => &$value) {
        if (is_array($value)) $value = delivery_wire($value);
        elseif ($value !== null && is_string($key) && (str_ends_with($key, '_id') || str_ends_with($key, '_minor'))) $value = (string) $value;
    }
    unset($value);
    return $data;
}

/** Handler returns [HTTP status, payload]; commit before sending a response. */
function delivery_run(array $methods, string $permission, bool $write, callable $handler, bool $repeatableRead = false): void
{
    $db = null;
    try {
        $method = $_SERVER['REQUEST_METHOD'] ?? '';
        if (!in_array($method, $methods, true)) {
            header('Allow: ' . implode(', ', $methods));
            throw new DeliveryApiError(405, 'METHOD_NOT_ALLOWED', 'Method not allowed.');
        }
        if ($write) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
            if (($origin !== null && !somi_origin_allowed($origin)) || ($origin === null && ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') !== 'same-origin')) {
                throw new DeliveryApiError(403, 'ORIGIN_REQUIRED', 'A same-origin request is required.');
            }
        }
        $id = $_SESSION['somi_user_id'] ?? null;
        if (!is_int($id) || $id < 1 || empty($_SESSION['somi_authenticated_at'])) throw new DeliveryApiError(401, 'AUTHENTICATION_REQUIRED', 'Please sign in again.');
        $db = somi_db();
        $user = null;
        if (!somi_require_active_session($user)) throw new DeliveryApiError(401, 'AUTHENTICATION_REQUIRED', 'Please sign in again.');
        $roles = json_decode($user['roles'], true, 512, JSON_THROW_ON_ERROR);
        if (($_SESSION['somi_role'] ?? '') !== 'customer' || !in_array('CUSTOMER', $roles, true)) throw new DeliveryApiError(403, 'CUSTOMER_REQUIRED', 'Switch to an authorized Customer workspace.');
        $q = $db->prepare("SELECT 1 FROM customer_profiles cp JOIN user_roles ur ON ur.user_id = cp.user_id JOIN roles r ON r.role_id = ur.role_id JOIN role_permissions rp ON rp.role_id = r.role_id JOIN permissions p ON p.permission_id = rp.permission_id WHERE cp.user_id = :id AND r.role_code = 'CUSTOMER' AND p.permission_code = :permission");
        $q->execute(['id' => $id, 'permission' => $permission]);
        if (!$q->fetchColumn()) throw new DeliveryApiError(403, 'PERMISSION_DENIED', 'Permission denied.');
        session_write_close();
        if ($write) {
            $db->beginTransaction();
            if ($repeatableRead) $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
        [$status, $payload] = $handler($db, $id);
        if ($write) $db->commit();
        somi_api_json($status, delivery_wire($payload));
    } catch (Throwable $error) {
        if ($db instanceof PDO && $db->inTransaction()) $db->rollBack();
        if ($error instanceof DeliveryApiError) somi_api_json($error->httpStatus, ['code' => $error->errorCode, 'error' => $error->getMessage()]);
        if ($error instanceof PDOException && in_array((string) $error->getCode(), ['40001', '40P01', '23505'], true)) {
            somi_api_json(409, ['code' => 'CONCURRENT_CHANGE', 'error' => 'Concurrent change detected. Retry the request with the same idempotency key.']);
        }
        error_log('[SOMI delivery API] ' . get_class($error) . ' code=' . $error->getCode());
        somi_api_json(503, ['code' => 'SERVICE_UNAVAILABLE', 'error' => 'Delivery service is unavailable. Check database migrations and configuration.']);
    }
}
