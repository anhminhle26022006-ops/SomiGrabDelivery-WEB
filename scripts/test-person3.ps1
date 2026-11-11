param(
    [string]$Php = 'C:\xampp\php\php.exe',
    [string]$NodeModules = '',
    [switch]$SkipBrowser
)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
if (-not (Test-Path -LiteralPath $Php)) { throw "PHP executable not found: $Php" }
$extensions = & $Php -m
$phpFlags = @()
if ($extensions -notcontains 'pdo_pgsql') { $phpFlags += @('-d', 'extension=pdo_pgsql') }
$previousPhp = $env:SOMI_TEST_PHP
$previousModules = $env:NODE_PATH
try {
    $env:SOMI_TEST_PHP = $Php
    if ($NodeModules) { $env:NODE_PATH = $NodeModules }
    foreach ($file in 'tests/supabase_storage.php','tests/person3_backend.php','tests/geoapify_addresses.php','tests/admin_console.php','tests/person1_backend.php') {
        & $Php @phpFlags $file
        if ($LASTEXITCODE -ne 0) { throw "Failed: $file" }
    }
    foreach ($file in 'tests/person3_http.py','tests/person3_upload_http.py') {
        & python $file
        if ($LASTEXITCODE -ne 0) { throw "Failed: $file" }
    }
    if (-not $SkipBrowser) {
        & node tests/person3_browser.cjs
        if ($LASTEXITCODE -ne 0) { throw 'Person 3 browser checks failed. Supply -NodeModules for your Playwright installation.' }
    }
    Write-Output 'Requested Person 3 checks passed. Fixtures do not prove production delivery or funds movement.'
} finally {
    $env:SOMI_TEST_PHP = $previousPhp
    $env:NODE_PATH = $previousModules
}
