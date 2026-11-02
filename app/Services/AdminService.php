<?php
declare(strict_types=1);
require_once __DIR__ . '/Input.php';
require_once __DIR__ . '/../Repositories/Person1Repository.php';

final class AdminService
{
    public function __construct(private Person1Repository $repo) {}

    public function accounts(string $query, ?string $role, int $before): array
    {
        $params = ['before' => $before, 'query' => '%' . strtr($query, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%'];
        $where = '';
        if ($role !== null) {
            $where = ' AND EXISTS (SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id = u.user_id AND r.role_code = :role)';
            $params['role'] = $role;
        }
        return $this->repo->all('SELECT u.user_id, u.full_name, u.email, u.phone, u.account_status, u.nationality_code,
            u.created_at, sp.verification_status FROM users u LEFT JOIN shipper_profiles sp USING(user_id)
            WHERE u.user_id < :before AND (u.full_name ILIKE :query OR u.email ILIKE :query)' . $where . ' ORDER BY u.user_id DESC LIMIT 100', $params);
    }

    public function status(int $actor, int $target, string $status, string $reason): array
    {
        if ($actor === $target) throw new ApiProblem(409, 'You cannot change your own account status.', 'SELF_ACTION');
        return $this->repo->transaction(function () use ($actor, $target, $status, $reason) {
            $old = $this->repo->one('SELECT account_status FROM users WHERE user_id = :id FOR UPDATE', ['id' => $target]);
            if (!$old) throw new ApiProblem(404, 'Account not found.', 'NOT_FOUND');
            if ($this->repo->one("SELECT 1 FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id = :id AND r.role_code = 'ADMIN'", ['id' => $target])) {
                throw new ApiProblem(409, 'Admin account access changes require a separate governance policy.', 'ADMIN_GOVERNANCE_REQUIRED');
            }
            if (!in_array($old['account_status'], ['ACTIVE', 'SUSPENDED'], true)) throw new ApiProblem(409, 'Only active or suspended accounts can use this workflow.', 'ACCOUNT_STATE_CONFLICT');
            if ($old['account_status'] === $status) return $old;
            $row = $this->repo->one('UPDATE users SET account_status = :status, updated_at = now() WHERE user_id = :id RETURNING user_id, account_status', ['id' => $target, 'status' => $status]);
            $this->repo->all('INSERT INTO account_status_history (user_id, old_status, new_status, reason, changed_by)
                VALUES (:id, :old, :new, :reason, :actor)', ['id' => $target, 'old' => $old['account_status'], 'new' => $status, 'reason' => $reason, 'actor' => $actor]);
            $this->repo->audit($actor, 'ACCOUNT_STATUS_CHANGED', 'USER', $target, $old, ['account_status' => $status, 'reason' => $reason]);
            return $row;
        });
    }
}
