param(
    [Parameter(Mandatory=$true)][int]$ServerPid,
    [string]$Php='C:\xampp\php\php.exe'
)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
Set-Location -LiteralPath $projectRoot
while (Get-Process -Id $ServerPid -ErrorAction SilentlyContinue) {
    & $Php (Join-Path $projectRoot 'bin\shipper-worker.php') --seconds=55
    if ($LASTEXITCODE -ne 0) { Start-Sleep -Seconds 5 }
}
