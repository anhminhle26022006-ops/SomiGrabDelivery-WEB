<?php
// CLI-only rendering fixture: no sessions, database, credentials or provider calls.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
parse_str($argv[1] ?? '', $_GET);
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function assetUrl(string $value): string { return '/'.$value; }
$sessionUserId=2;
$sessionUser=['user_id'=>2,'full_name'=>'Khách hàng thử nghiệm','app_language'=>'vi'];
$hasShipperWorkspace=true;
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/css/app.css"></head><body data-page="customer">
<?php require __DIR__.'/../app/Views/customer/index.php'; ?>
</body></html>
