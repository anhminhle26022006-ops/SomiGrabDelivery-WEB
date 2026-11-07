<?php
declare(strict_types=1);
require_once __DIR__ . '/../_person1.php';
require_once __DIR__ . '/../../../app/Services/PricingConfiguration.php';
try {
    $method = person1_method(['GET','POST']);
    $actor = person1_identity('ADMIN', 'pricing.manage');
    $repo = new Person1Repository(somi_db());
    if ($method === 'GET') somi_api_json(200, ['data' => $repo->all('SELECT * FROM pricing_configuration_drafts ORDER BY draft_id DESC LIMIT 100'), 'activation' => 'BLOCKED_BY_BUSINESS_DECISION']);
    $input = PricingConfiguration::validate(somi_api_input(true));
    $row = $repo->transaction(function () use ($repo, $actor, $input) {
        $row = $repo->one('INSERT INTO pricing_configuration_drafts (currency_code, configuration, created_by)
            VALUES (:currency, CAST(:config AS jsonb), :actor) RETURNING *', ['currency' => $input['currency_code'], 'config' => json_encode($input['configuration'], JSON_THROW_ON_ERROR), 'actor' => $actor]);
        $repo->audit($actor, 'PRICING_DRAFT_CREATED', 'PRICING_DRAFT', (int) $row['draft_id'], [], $input);
        return $row;
    });
    somi_api_json(201, ['data' => $row, 'activation' => 'BLOCKED_BY_BUSINESS_DECISION']);
} catch (Throwable $error) { person1_error($error); }
