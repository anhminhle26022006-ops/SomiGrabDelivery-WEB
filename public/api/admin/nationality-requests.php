<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    $method = person1_method(['GET','POST']);
    $actor = person1_identity('ADMIN', 'nationality.review');
    $repo = new Person1Repository(somi_db());
    if ($method === 'GET') somi_api_json(200, ['data' => $repo->all("SELECT * FROM nationality_change_requests WHERE status = 'PENDING' ORDER BY request_id LIMIT 100")]);
    $input = somi_api_input();
    $id = Input::id($input['request_id'] ?? null);
    $decision = Input::choice($input, 'decision', ['APPROVED','REJECTED']);
    $note = Input::text($input, 'note', 1000);
    $row = $repo->transaction(function () use ($repo, $actor, $id, $decision, $note) {
        $owner = $repo->one('SELECT user_id FROM nationality_change_requests WHERE request_id = :id', ['id' => $id]);
        if (!$owner) throw new ApiProblem(404, 'Request not found.', 'NOT_FOUND');
        $repo->one('SELECT user_id FROM users WHERE user_id = :id FOR UPDATE', ['id' => $owner['user_id']]);
        $request = $repo->one('SELECT * FROM nationality_change_requests WHERE request_id = :id FOR UPDATE', ['id' => $id]);
        if (!$request) throw new ApiProblem(404, 'Request not found.', 'NOT_FOUND');
        if ($request['status'] !== 'PENDING') throw new ApiProblem(409, 'Request already reviewed.', 'CONFLICT');
        if ((int) $request['user_id'] === $actor) throw new ApiProblem(409, 'You cannot review your own request.', 'SELF_ACTION');
        if ($decision === 'APPROVED') {
            // Identity replacement and re-verification rules are not agreed yet.
            throw new ApiProblem(409, 'Approval requires the identity-document replacement policy.', 'BLOCKED_BY_BUSINESS_DECISION');
        }
        $row = $repo->one('UPDATE nationality_change_requests SET status = :status, review_note = :note, reviewed_by = :actor, reviewed_at = now()
            WHERE request_id = :id RETURNING request_id, status', ['id' => $id, 'status' => $decision, 'note' => $note, 'actor' => $actor]);
        $repo->audit($actor, 'NATIONALITY_REQUEST_' . $decision, 'NATIONALITY_REQUEST', $id, ['status' => $request['status']], ['status' => $decision, 'note' => $note]);
        return $row;
    });
    somi_api_json(200, ['data' => $row]);
} catch (Throwable $error) { person1_error($error); }
