<?php
declare(strict_types=1);
require_once __DIR__ . '/_kernel.php';
require_once __DIR__ . '/../../app/Services/CustomerOrderService.php';

function delivery_body(): array { return somi_json_body(); }
function delivery_query_id(string $name = 'id', bool $optional = false): ?int
{
    return $optional && !isset($_GET[$name]) ? null : DeliveryInput::id($_GET[$name] ?? null, $name);
}
function delivery_wire(array $data): array { return somi_wire($data); }
function delivery_run(array $methods, string $permission, bool $write, callable $handler, bool $repeatableRead = false): void
{
    somi_run_api($methods, ['CUSTOMER'], $permission, $write, $handler, $repeatableRead);
}
