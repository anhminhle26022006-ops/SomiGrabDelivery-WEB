param([string]$Php = 'php')
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
# Current integration suite uses TEMP tables and does not seed application data.
foreach ($file in 'tests/environment.php','tests/database_diagnostics.php','tests/sepay.php','tests/pricing_provider.php','tests/open_meteo.php','tests/geoapify_addresses.php','tests/request_origin.php','tests/customer_orders.php','tests/person1_backend.php','tests/admin_console.php','tests/migration_compat.php','tests/parcel_categories.php') {
    & $Php $file
    if ($LASTEXITCODE -ne 0) { throw "Failed: $file" }
}
& node tests/customer_pricing.cjs
if ($LASTEXITCODE -ne 0) { throw 'Pricing frontend checks failed.' }
& node tests/reaudit_auth_frontend.cjs
if ($LASTEXITCODE -ne 0) { throw 'Auth frontend checks failed.' }

& node tests/geoapify_frontend.cjs
if ($LASTEXITCODE -ne 0) { throw 'Address autocomplete frontend checks failed.' }

& node tests/geoapify_maps.cjs
if ($LASTEXITCODE -ne 0) { throw 'Leaflet map checks failed.' }
