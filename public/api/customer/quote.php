<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
try {
    person1_method(['POST']);
    person1_identity('CUSTOMER', 'order.create');
    somi_api_input();
    throw new ApiProblem(409, 'Quotation requires approved pricing, distance and weather-source rules.', 'BLOCKED_BY_BUSINESS_DECISION');
} catch (Throwable $error) { person1_error($error); }
