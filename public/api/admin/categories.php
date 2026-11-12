<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    $method = person1_method(['GET', 'POST']);
    $actor = person1_identity('ADMIN', 'pricing.manage');
    $repo = new Person1Repository(somi_db());
    if ($method === 'GET') somi_api_json(200, ['data' => $repo->all('SELECT * FROM parcel_categories ORDER BY category_id')]);
    $input = somi_api_input();
    $id = Input::id($input['category_id'] ?? null);
    $restriction = Input::choice($input, 'restriction_type', ['ALLOWED', 'RESTRICTED', 'PROHIBITED']);
    $active = Input::boolean($input, 'active') ? 'true' : 'false';
    $row = $repo->transaction(function () use ($repo, $actor, $id, $restriction, $active) {
        $old = $repo->one('SELECT * FROM parcel_categories WHERE category_id = :id FOR UPDATE', ['id' => $id]);
        if (!$old) throw new ApiProblem(404, 'Category not found.', 'NOT_FOUND');
        $row = $repo->one('UPDATE parcel_categories SET restriction_type = :restriction, active = CAST(:active AS boolean), updated_at = now()
            WHERE category_id = :id RETURNING *', ['id' => $id, 'restriction' => $restriction, 'active' => $active]);
        $repo->audit($actor, 'CATEGORY_UPDATED', 'PARCEL_CATEGORY', $id, $old, $row);
        return $row;
    });
    somi_api_json(200, ['data' => $row]);
} catch (Throwable $error) { person1_error($error); }
