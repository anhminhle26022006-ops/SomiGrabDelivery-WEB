<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    $method = person1_method(['GET', 'POST']);
    $id = person1_identity(null, $method === 'GET' ? 'profile.read_own' : 'profile.update_own');
    $repo = new Person1Repository(somi_db());
    if ($method === 'POST') {
        $input = somi_api_input();
        // Email, phone, nationality, roles and account status have separate verified workflows.
        $allowed = ['full_name', 'app_language'];
        if (array_diff(array_keys($input), $allowed)) throw new ApiProblem(422, 'Only full_name and app_language can be updated here.', 'VALIDATION_ERROR');
        $repo->all('UPDATE users SET full_name = :name, app_language = :language, updated_at = now() WHERE user_id = :id',
            ['id' => $id, 'name' => Input::text($input, 'full_name', 150), 'language' => Input::choice($input, 'app_language', ['vi', 'en'])]);
    }
    $profile = $repo->one('SELECT user_id, full_name, email, phone, nationality_code, date_of_birth, gender,
        app_language, account_status, email_verified_at, phone_verified_at, created_at FROM users WHERE user_id = :id', ['id' => $id]);
    $profile['roles'] = array_column($repo->all('SELECT r.role_code FROM user_roles ur JOIN roles r USING(role_id) WHERE ur.user_id = :id ORDER BY r.role_code', ['id' => $id]), 'role_code');
    somi_api_json(200, ['data' => $profile]);
} catch (Throwable $error) { person1_error($error); }
