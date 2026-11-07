<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    $method = person1_method(['GET','POST']);
    $id = person1_identity(null, 'profile.update_own');
    $repo = new Person1Repository(somi_db());
    if ($method === 'GET') somi_api_json(200, ['data' => $repo->all('SELECT request_id, old_country_code, requested_country_code, reason, status, review_note, created_at, reviewed_at
        FROM nationality_change_requests WHERE user_id = :id ORDER BY request_id DESC LIMIT 100', ['id' => $id])]);
    $input = somi_api_input();
    $country = Input::text($input, 'requested_country_code', 2);
    if (!preg_match('/^[A-Z]{2}$/D', $country)) throw new ApiProblem(422, 'Use a two-letter country code.', 'VALIDATION_ERROR');
    $countries = json_decode(file_get_contents(__DIR__ . '/../../../resources/countries.json'), true, 512, JSON_THROW_ON_ERROR);
    if (!in_array($country, array_column($countries, 'code'), true)) throw new ApiProblem(422, 'Unknown country code.', 'VALIDATION_ERROR');
    $reason = Input::text($input, 'reason', 1000);
    $row = $repo->transaction(function () use ($repo, $id, $country, $reason) {
        $user = $repo->one('SELECT nationality_code FROM users WHERE user_id = :id FOR UPDATE', ['id' => $id]);
        if ($user['nationality_code'] === $country) throw new ApiProblem(409, 'Nationality is unchanged.', 'CONFLICT');
        return $repo->one('INSERT INTO nationality_change_requests (user_id, old_country_code, requested_country_code, reason)
            VALUES (:id, :old, :country, :reason) RETURNING request_id, status', ['id' => $id, 'old' => $user['nationality_code'], 'country' => $country, 'reason' => $reason]);
    });
    somi_api_json(201, ['data' => $row]);
} catch (Throwable $error) { person1_error($error); }
