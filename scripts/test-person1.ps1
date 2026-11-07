param([string]$Php = 'php')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
# Current integration suite uses TEMP tables and does not seed application data.
foreach ($file in 'tests/environment.php','tests/database_diagnostics.php','tests/sepay.php','tests/pricing_provider.php','tests/request_origin.php','tests/customer_orders.php','tests/person1_backend.php','tests/migration_compat.php') {
    & $Php $file
    if ($LASTEXITCODE -ne 0) { throw "Failed: $file" }
}
& node tests/customer_pricing.cjs
if ($LASTEXITCODE -ne 0) { throw 'Pricing frontend checks failed.' }
& node tests/reaudit_auth_frontend.cjs
if ($LASTEXITCODE -ne 0) { throw 'Auth frontend checks failed.' }
