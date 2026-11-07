param([int]$Port = 3000)
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
foreach ($name in 'SOMI_DB_HOST','SOMI_DB_PORT','SOMI_DB_NAME','SOMI_DB_USER','SOMI_DB_PASSWORD','SOMI_DB_SSLMODE') {
    if ([string]::IsNullOrEmpty([Environment]::GetEnvironmentVariable($name, 'Process'))) {
        [Environment]::SetEnvironmentVariable($name, [Environment]::GetEnvironmentVariable($name, 'User'), 'Process')
    }
}
$phpDirectory = Split-Path (Get-Command php).Source
& php -d "extension_dir=$phpDirectory/ext" -d extension=pdo_pgsql -d extension=mbstring -S "127.0.0.1:$Port" index.php
