<?php
declare(strict_types=1);
require_once __DIR__ . '/../_delivery.php';
delivery_run(['GET'], 'order.create', false, static function (PDO $db, int $owner): array {
    return [200, ['items' => $db->query('SELECT category_id, category_code, name_vi, name_en, description, restriction_type FROM parcel_categories WHERE active ORDER BY category_id')->fetchAll()]];
});
