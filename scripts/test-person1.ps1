param([switch]$ServeOnly)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
# Hard guard: this script only targets the explicitly named local disposable DB.
$env:SOMI_DB_HOST = '127.0.0.1'
$env:SOMI_DB_PORT = '55432'
$env:SOMI_DB_NAME = 'somi_person1_test'
$env:SOMI_DB_USER = 'postgres'
$env:SOMI_DB_PASSWORD = 'local-test-only'
$env:SOMI_DB_SSLMODE = 'disable'
$phpDirectory = Split-Path (Get-Command php).Source
$phpArguments = @('-d', "extension_dir=$phpDirectory/ext", '-d', 'extension=pdo_pgsql', '-d', 'extension=mbstring')
if ($ServeOnly) {
    New-Item -ItemType Directory -Force -Path 'tests/.runtime/person1-sessions' | Out-Null
    $sessionDirectory = (Resolve-Path 'tests/.runtime/person1-sessions').Path
    & php @phpArguments -d 'session.name=SOMI_PERSON1_TEST' -d "session.save_path=$sessionDirectory" -S 127.0.0.1:3034 index.php
    exit $LASTEXITCODE
}
foreach ($file in 'scripts/migrate.php','tests/person1_seed.php','tests/person1_services.php') {
    if ($file -eq 'scripts/migrate.php') { & php @phpArguments $file --baseline }
    else { & php @phpArguments $file }
    if ($LASTEXITCODE -ne 0) { throw "Failed: $file" }
}
& node tests/person1_http.cjs
if ($LASTEXITCODE -ne 0) { throw 'HTTP tests failed. Start the isolated server in another terminal: scripts/test-person1.ps1 -ServeOnly' }
& php @phpArguments tests/person1_concurrency.php
if ($LASTEXITCODE -ne 0) { throw 'Concurrency tests failed.' }
& php @phpArguments tests/person1_schema.php
if ($LASTEXITCODE -ne 0) { throw 'Schema checks failed.' }
& php @phpArguments tests/person1_assertions.php
if ($LASTEXITCODE -ne 0) { throw 'Database and session assertions failed.' }
