<?php
declare(strict_types=1);
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../app/Services/SessionGuard.php';
require_once __DIR__ . '/../../app/Services/Input.php';
require_once __DIR__ . '/../../app/Repositories/Person1Repository.php';

function person1_method(array $methods): string
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        throw new ApiProblem(405, 'Method not allowed.', 'METHOD_NOT_ALLOWED');
    }
    if ($method === 'POST') somi_api_require_post();
    return $method;
}

/** Roles of the user validated by person1_identity() in this request. */
function person1_session_roles(?array $set = null): array
{
    static $roles = [];
    if ($set !== null) $roles = $set;
    return $roles;
}

/** The session workspace alone never grants a role or a permission. */
function person1_identity(?string $role = null, ?string $permission = null): int
{
    $id = $_SESSION['somi_user_id'] ?? null;
    if (!is_int($id) || $id < 1) throw new ApiProblem(401, 'Please sign in.', 'UNAUTHENTICATED');
    // Session validity and the permission grant are checked in one query.
    if (!somi_require_active_session($user, $permission)) throw new ApiProblem(401, 'Please sign in again.', 'UNAUTHENTICATED');
    // Person 1 endpoints never write the session after this point. Releasing the
    // file lock lets one user's parallel API calls run concurrently instead of
    // queueing behind each other.
    session_write_close();
    $roles = json_decode((string) $user['roles'], true, 512, JSON_THROW_ON_ERROR);
    person1_session_roles($roles);
    $workspace = strtoupper((string) ($_SESSION['somi_role'] ?? ''));
    if (!in_array($workspace, $roles, true) || ($role !== null && $workspace !== $role)) {
        throw new ApiProblem(403, 'This workspace is not authorized.', 'FORBIDDEN');
    }
    if ($permission !== null && !in_array($user['has_permission'], [true, 't', '1', 1], true)) {
        throw new ApiProblem(403, 'Permission is required.', 'FORBIDDEN');
    }
    return $id;
}

function person1_error(Throwable $error): void
{
    if ($error instanceof ApiProblem) somi_api_json($error->status, ['error' => $error->getMessage(), 'code' => $error->errorCode]);
    if ($error instanceof PDOException && in_array($error->getCode(), ['23503', '23505', '40001', '40P01'], true)) {
        somi_api_json(409, ['error' => 'The data changed or conflicts with an existing record. Reload and try again.', 'code' => 'CONFLICT']);
    }
    somi_api_handle_error($error);
    exit;
}
