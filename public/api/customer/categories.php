<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    person1_method(['GET']);
    person1_identity('CUSTOMER', 'order.create');
    $repo = new Person1Repository(somi_db());
    somi_api_json(200, ['data' => $repo->all('SELECT category_id, category_code, name_vi, name_en, description, restriction_type
        FROM parcel_categories WHERE active = TRUE ORDER BY category_id')]);
} catch (Throwable $error) { person1_error($error); }
