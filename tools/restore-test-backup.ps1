#requires -Version 7.4
param(
    [Parameter(Mandatory)] [string]$Backup,
    [Parameter(Mandatory)] [string]$AgeIdentity,
    [Parameter(Mandatory)] [ValidatePattern('^[a-fA-F0-9]{64}$')] [string]$ExpectedSha256,
    [ValidateSet('127.0.0.1', 'localhost', '::1')] [string]$Server = '127.0.0.1',
    [Parameter(Mandatory)] [ValidateRange(1, 65535)] [int]$Port,
    [Parameter(Mandatory)] [string]$Username,
    [Parameter(Mandatory)] [ValidatePattern('^cuidado_restore_test_[a-z0-9_]+$')] [string]$DatabaseName
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'recovery-common.ps1')
$backupPath = (Resolve-Path -LiteralPath $Backup).Path
$identityPath = (Resolve-Path -LiteralPath $AgeIdentity).Path
if ([IO.Path]::GetExtension($backupPath) -ne '.age') { throw 'Se requiere un respaldo .age.' }
if ((Get-FileHash -LiteralPath $backupPath -Algorithm SHA256).Hash -ne $ExpectedSha256) { throw 'SHA256 incorrecto: no se inicia la restauración.' }
$sql = @'
SELECT count(*) FROM pg_class c JOIN pg_namespace n ON n.oid = c.relnamespace
WHERE n.nspname NOT IN ('pg_catalog', 'information_schema') AND n.nspname !~ '^pg_toast'
AND c.relkind IN ('r', 'p', 'v', 'm', 'S', 'f');
'@
$objects = Invoke-RecoveryQuery -Server $Server -Port $Port -Username $Username -DatabaseName $DatabaseName -Sql $sql
if ($objects -ne '0') { throw 'La base de prueba debe estar vacía; no se elimina ni sobrescribe su contenido.' }
$decrypt = New-RecoveryProcess 'age' @('--decrypt', '--identity', $identityPath, $backupPath)
$restore = New-RecoveryProcess 'pg_restore' @(
    '--host', $Server, '--port', "$Port", '--username', $Username, '--dbname', $DatabaseName,
    '--no-password', '--no-owner', '--no-acl', '--no-tablespaces', '--exit-on-error', '--single-transaction'
)
$decrypt.StartInfo.RedirectStandardOutput = $true
$restore.StartInfo.RedirectStandardInput = $true
$started = [Collections.Generic.List[Diagnostics.Process]]::new()
try {
    [void]$restore.Start()
    $started.Add($restore)
    $restoreErrors = $restore.StandardError.ReadToEndAsync()
    [void]$decrypt.Start()
    $started.Add($decrypt)
    $decryptErrors = $decrypt.StandardError.ReadToEndAsync()
    try { $decrypt.StandardOutput.BaseStream.CopyTo($restore.StandardInput.BaseStream) }
    finally { $restore.StandardInput.Close() }
    $decrypt.WaitForExit()
    $restore.WaitForExit()
    [void]$decryptErrors.GetAwaiter().GetResult()
    [void]$restoreErrors.GetAwaiter().GetResult()
    if ($decrypt.ExitCode -ne 0 -or $restore.ExitCode -ne 0) { throw 'Restauración fallida.' }
} catch {
    throw 'No se pudo restaurar el respaldo; revisar identidad age, archivo, conexión y permisos.'
} finally {
    foreach ($process in @($decrypt, $restore)) {
        if ($started.Contains($process) -and -not $process.HasExited) { $process.Kill($true); $process.WaitForExit() }
        $process.Dispose()
    }
}
Write-Host 'Restauración finalizada en la base local de prueba; ejecutar comparación de registros.'
