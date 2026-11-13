param([int]$Port=3000,[string]$Php='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
if(-not(Test-Path -LiteralPath $Php)){throw "PHP not found: $Php"}
$serverAddress="127.0.0.1:$Port"
try {$existing=Invoke-WebRequest "http://$serverAddress/" -TimeoutSec 2 -UseBasicParsing;Write-Output "Server is already listening: http://$serverAddress/admin";exit 0} catch {if($_.Exception.Response){throw "Port $Port is already used by a server. Check it before starting SOMI."}}
$runtimeDir=Join-Path $projectRoot 'tests\.runtime'
New-Item -ItemType Directory -Path $runtimeDir -Force | Out-Null
$extensions=& $Php -m
$phpArgs=@()
if($extensions -notcontains 'pdo_pgsql'){$phpArgs+=@('-d','extension=pdo_pgsql')}
if($extensions -notcontains 'mbstring'){$phpArgs+=@('-d','extension=mbstring')}
$phpArgs+=@('-S',$serverAddress,'-t',('"'+$projectRoot+'"'),('"'+(Join-Path $projectRoot 'index.php')+'"'))
$process=Start-Process -FilePath $Php -ArgumentList $phpArgs -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDir "server-$Port.out.log") -RedirectStandardError (Join-Path $runtimeDir "server-$Port.err.log")
Set-Content -LiteralPath (Join-Path $runtimeDir "server-$Port.pid") -Value $process.Id
$workerScript=Join-Path $PSScriptRoot 'run-local-matching.ps1'
$workerArgs=@('-NoProfile','-ExecutionPolicy','Bypass','-File',('"'+$workerScript+'"'),'-ServerPid',$process.Id,'-Php',('"'+$Php+'"'))
$worker=Start-Process -FilePath (Get-Process -Id $PID).Path -ArgumentList $workerArgs -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimeDir "matching-$Port.out.log") -RedirectStandardError (Join-Path $runtimeDir "matching-$Port.err.log")
Set-Content -LiteralPath (Join-Path $runtimeDir "matching-$Port.pid") -Value $worker.Id
for($attempt=0;$attempt -lt 30;$attempt++){
    Start-Sleep -Milliseconds 150
    try {$response=Invoke-WebRequest "http://$serverAddress/" -TimeoutSec 2 -UseBasicParsing;if($response.StatusCode -eq 200){Write-Output "SOMI is running: http://$serverAddress/admin";exit 0}} catch {}
    $process.Refresh();if($process.HasExited){throw 'PHP stopped. Check tests/.runtime server log.'}
}
throw 'SOMI did not respond. Check tests/.runtime server log.'
