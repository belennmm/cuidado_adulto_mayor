#requires -Version 7.4
param(
    [string]$PostgresBin = 'C:/Program Files/PostgreSQL/18/bin',
    [switch]$ValidateCareData
)
$ErrorActionPreference = 'Stop'
$tools = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../tools'))
. (Join-Path $tools 'recovery-common.ps1')
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('recovery-test-' + [guid]::NewGuid().ToString('N'))
$originalPath = $env:PATH
$originalPassword = $env:PGPASSWORD
$originalDecryptFailure = $env:RECOVERY_TEST_DECRYPT_FAIL
$startAttempted = $false
$stopped = $true
$checks = [Collections.Generic.List[string]]::new()
New-Item -ItemType Directory -Path $testRoot | Out-Null

function Invoke-TestNative {
    param([string]$Command, [string[]]$Arguments)
    $process = New-RecoveryProcess $Command $Arguments
    # pg_ctl inicia un servidor duradero en Windows que puede heredar pipes.
    # No esperar EOF de sus streams: el estado lo determina su código de salida.
    $capture = $Command -ne 'pg_ctl'
    $process.StartInfo.RedirectStandardOutput = $capture
    $process.StartInfo.RedirectStandardError = $capture
    try {
        [void]$process.Start()
        if ($capture) {
            $stdout = $process.StandardOutput.ReadToEndAsync()
            $stderr = $process.StandardError.ReadToEndAsync()
        }
        if (-not $process.WaitForExit(120000)) {
            $process.Kill($true)
            $process.WaitForExit()
            throw "Tiempo de espera agotado: $Command"
        }
        $result = ''
        if ($capture) {
            $result = $stdout.GetAwaiter().GetResult()
            [void]$stderr.GetAwaiter().GetResult()
        }
        if ($process.ExitCode -ne 0) { throw "Falló la herramienta de prueba: $Command" }
        return $result.Trim()
    } finally { $process.Dispose() }
}

function Assert-Rejected {
    param([string]$Name, [scriptblock]$Action, [string]$Message)
    $rejected = $false
    try { & $Action | Out-Null } catch {
        if ($_.Exception.Message -notlike "*$Message*") { throw }
        $rejected = $true
    }
    if (-not $rejected) { throw "No se rechazó: $Name" }
    $checks.Add($Name)
}

try {
    Write-Host 'Preparando herramientas y datos sintéticos...'
    $env:PATH = $PostgresBin + [IO.Path]::PathSeparator + $originalPath
    $hasAge = (Get-Command age -CommandType Application -ErrorAction SilentlyContinue) -and
        (Get-Command age-keygen -CommandType Application -ErrorAction SilentlyContinue)
    $identity = Join-Path $testRoot 'identity.txt'
    if ($hasAge) {
        [void](Invoke-TestNative 'age-keygen' @('--output', $identity))
        $recipient = Invoke-TestNative 'age-keygen' @('-y', $identity)
        $cryptoMode = 'real-age'
    } else {
        # Sustituto transparente SÓLO para probar pg_restore real sin age instalado.
        $compiler = Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
        $source = Join-Path $testRoot 'AgeTransportStub.cs'
        @'
using System;
using System.IO;
class AgeTransportStub {
    static int Main(string[] args) {
        if (Environment.GetEnvironmentVariable("RECOVERY_TEST_DECRYPT_FAIL") == "1") return 8;
        using (Stream input = File.OpenRead(args[args.Length - 1]))
        using (Stream output = Console.OpenStandardOutput()) { input.CopyTo(output); }
        return 0;
    }
}
'@ | Set-Content -LiteralPath $source
        [void](Invoke-TestNative $compiler @('/nologo', '/target:exe', "/out:$testRoot/age.exe", $source))
        $env:PATH = $testRoot + [IO.Path]::PathSeparator + $env:PATH
        'synthetic identity: transport stub only' | Set-Content -LiteralPath $identity
        $cryptoMode = 'transport-stub-no-encryption'
    }
    $env:RECOVERY_TEST_DECRYPT_FAIL = ''
    $env:PGPASSWORD = [guid]::NewGuid().ToString('N')
    $passwordFile = Join-Path $testRoot 'password.txt'
    $env:PGPASSWORD | Set-Content -LiteralPath $passwordFile -NoNewline
    $cluster = Join-Path $testRoot 'cluster'
    Write-Host 'Inicializando PostgreSQL temporal...'
    [void](Invoke-TestNative 'initdb' @('-D', $cluster, '-U', 'recovery_test_owner', '-A', 'scram-sha-256', '--pwfile', $passwordFile, '--encoding=UTF8', '--no-locale', '--no-sync'))
    $listener = [Net.Sockets.TcpListener]::new([Net.IPAddress]::Loopback, 0)
    $listener.Start()
    $port = $listener.LocalEndpoint.Port
    $listener.Stop()
    $startAttempted = $true
    $stopped = $false
    [void](Invoke-TestNative 'pg_ctl' @('-D', $cluster, '-l', (Join-Path $testRoot 'postgres.log'), '-o', "-h 127.0.0.1 -p $port", '-w', 'start'))
    Write-Host 'PostgreSQL temporal iniciado; probando respaldo, restauración y conteos...'
    $connection = @{ Server = '127.0.0.1'; Port = $port; Username = 'recovery_test_owner' }
    [void](Invoke-RecoveryQuery @connection -DatabaseName postgres -Sql 'CREATE DATABASE cuidado_backup_fixture;')
    [void](Invoke-RecoveryQuery @connection -DatabaseName postgres -Sql 'CREATE DATABASE cuidado_restore_test_roundtrip;')
    [void](Invoke-RecoveryQuery @connection -DatabaseName postgres -Sql 'CREATE DATABASE cuidado_restore_test_failure;')
    $fixture = @'
CREATE TABLE users (id integer PRIMARY KEY);
INSERT INTO users VALUES (1), (2);
CREATE TABLE older_adults (id integer PRIMARY KEY, caregiver_id integer REFERENCES users);
INSERT INTO older_adults VALUES (1, 1), (2, 2);
CREATE TABLE medications (id integer PRIMARY KEY);
INSERT INTO medications VALUES (1), (2), (3);
CREATE TABLE rutinas (id integer PRIMARY KEY, older_adult_id integer REFERENCES older_adults);
INSERT INTO rutinas VALUES (1, 1), (2, 2);
CREATE TABLE incidents (id integer PRIMARY KEY, older_adult_id integer REFERENCES older_adults);
INSERT INTO incidents VALUES (1, 1), (2, 1), (3, 2), (4, 2);
CREATE SCHEMA "Quoted schema";
CREATE TABLE "Quoted schema"."Empty.Table" (id integer);
'@
    $expectedTableCount = 6
    if ($ValidateCareData) {
        $fixture = & (Join-Path $PSScriptRoot 'care-validation-fixture.ps1')
        $expectedTableCount = 9
    }
    Write-Host 'Creando las tablas sintéticas de referencia...'
    [void](Invoke-RecoveryQuery @connection -DatabaseName cuidado_backup_fixture -Sql $fixture)
    $reference = Join-Path $testRoot 'reference.json'
    & (Join-Path $tools 'capture-record-counts.ps1') @connection -DatabaseName cuidado_backup_fixture -Output $reference
    if ($ValidateCareData) {
        $careReference = Join-Path $testRoot 'care-reference.json'
        & (Join-Path $tools 'validate-recovered-data.ps1') @connection -DatabaseName cuidado_backup_fixture -Output $careReference
    }
    $plain = Join-Path $testRoot 'synthetic.dump'
    [void](Invoke-TestNative 'pg_dump' @('--host', '127.0.0.1', '--port', "$port", '--username', 'recovery_test_owner', '--no-password', '--format=custom', '--no-owner', '--no-acl', '--file', $plain, 'cuidado_backup_fixture'))
    $backup = Join-Path $testRoot 'synthetic.age'
    if ($hasAge) {
        [void](Invoke-TestNative 'age' @('--recipient', $recipient, '--output', $backup, $plain))
    } else { Copy-Item -LiteralPath $plain -Destination $backup }
    $restoreArgs = $connection.Clone()
    $restoreArgs.Backup = $backup
    $restoreArgs.AgeIdentity = $identity
    $restoreArgs.ExpectedSha256 = (Get-FileHash -LiteralPath $backup).Hash
    & (Join-Path $tools 'restore-test-backup.ps1') @restoreArgs -DatabaseName cuidado_restore_test_roundtrip
    $restored = Join-Path $testRoot 'restored.json'
    & (Join-Path $tools 'capture-record-counts.ps1') @connection -DatabaseName cuidado_restore_test_roundtrip -Output $restored
    $comparison = @(& (Join-Path $tools 'compare-record-counts.ps1') -Reference $reference -Restored $restored)
    if ($comparison.Count -ne $expectedTableCount -or @($comparison | Where-Object status -NE equal).Count -ne 0) { throw 'Conteos incorrectos.' }
    if ($ValidateCareData) {
        & (Join-Path $PSScriptRoot 'test-care-validation.ps1') -Connection $connection -TestRoot $testRoot -Reference $careReference -Tools $tools
        return
    }
    $checks.Add('restauración PostgreSQL y 6 tablas iguales, incluida tabla vacía con identificadores especiales')
    Assert-Rejected 'rechazo de base ocupada' { & (Join-Path $tools 'restore-test-backup.ps1') @restoreArgs -DatabaseName cuidado_restore_test_roundtrip } 'debe estar vacía'
    $badHash = $restoreArgs.Clone()
    $badHash.ExpectedSha256 = '0' * 64
    Assert-Rejected 'rechazo de hash alterado' { & (Join-Path $tools 'restore-test-backup.ps1') @badHash -DatabaseName cuidado_restore_test_failure } 'SHA256 incorrecto'
    Assert-Rejected 'rechazo de nombre ajeno a pruebas' { & (Join-Path $tools 'restore-test-backup.ps1') @restoreArgs -DatabaseName cuidado_backup_fixture } 'DatabaseName'
    Assert-Rejected 'rechazo de host remoto' { & (Join-Path $tools 'restore-test-backup.ps1') @restoreArgs -DatabaseName cuidado_restore_test_failure -Server '192.0.2.1' } 'Server'
    Assert-Rejected 'rechazo de sobrescritura de manifiesto' { & (Join-Path $tools 'capture-record-counts.ps1') @connection -DatabaseName cuidado_backup_fixture -Output $reference } 'ya existe'
    if (-not $hasAge) {
        $env:RECOVERY_TEST_DECRYPT_FAIL = '1'
        Assert-Rejected 'detección de fallo del descifrador simulado' { & (Join-Path $tools 'restore-test-backup.ps1') @restoreArgs -DatabaseName cuidado_restore_test_failure } 'No se pudo restaurar'
        $env:RECOVERY_TEST_DECRYPT_FAIL = ''
    }
    $corruptPlain = Join-Path $testRoot 'corrupt.txt'
    'not a PostgreSQL archive' | Set-Content -LiteralPath $corruptPlain
    $corruptBackup = Join-Path $testRoot 'corrupt.age'
    if ($hasAge) { [void](Invoke-TestNative 'age' @('--recipient', $recipient, '--output', $corruptBackup, $corruptPlain)) }
    else { Copy-Item -LiteralPath $corruptPlain -Destination $corruptBackup }
    $badArchive = $restoreArgs.Clone()
    $badArchive.Backup = $corruptBackup
    $badArchive.ExpectedSha256 = (Get-FileHash -LiteralPath $corruptBackup).Hash
    Assert-Rejected 'detección de archivo PostgreSQL inválido' { & (Join-Path $tools 'restore-test-backup.ps1') @badArchive -DatabaseName cuidado_restore_test_failure } 'No se pudo restaurar'
    $remaining = Invoke-RecoveryQuery @connection -DatabaseName cuidado_restore_test_failure -Sql "SELECT count(*) FROM pg_tables WHERE schemaname = 'public';"
    if ($remaining -ne '0') { throw 'El fallo dejó tablas en el destino.' }
    $checks.Add('destino vacío después de fallar')
    $truncatedPlain = Join-Path $testRoot 'truncated.dump'
    $dumpBytes = [IO.File]::ReadAllBytes($plain)
    [IO.File]::WriteAllBytes($truncatedPlain, [byte[]]$dumpBytes[0..($dumpBytes.Length - 65)])
    $truncatedBackup = Join-Path $testRoot 'truncated.age'
    if ($hasAge) { [void](Invoke-TestNative 'age' @('--recipient', $recipient, '--output', $truncatedBackup, $truncatedPlain)) }
    else { Copy-Item -LiteralPath $truncatedPlain -Destination $truncatedBackup }
    $truncatedArgs = $restoreArgs.Clone()
    $truncatedArgs.Backup = $truncatedBackup
    $truncatedArgs.ExpectedSha256 = (Get-FileHash -LiteralPath $truncatedBackup).Hash
    Assert-Rejected 'detección de archivo truncado durante la carga' { & (Join-Path $tools 'restore-test-backup.ps1') @truncatedArgs -DatabaseName cuidado_restore_test_failure } 'No se pudo restaurar'
    $remaining = Invoke-RecoveryQuery @connection -DatabaseName cuidado_restore_test_failure -Sql "SELECT count(*) FROM pg_tables WHERE schemaname NOT IN ('pg_catalog', 'information_schema');"
    if ($remaining -ne '0') { throw 'La transacción dejó tablas tras restaurar un archivo truncado.' }
    $checks.Add('rollback de carga truncada sin tablas parciales')
    [void](Invoke-RecoveryQuery @connection -DatabaseName cuidado_restore_test_roundtrip -Sql 'DELETE FROM incidents WHERE id=4;')
    $changed = Join-Path $testRoot 'changed.json'
    & (Join-Path $tools 'capture-record-counts.ps1') @connection -DatabaseName cuidado_restore_test_roundtrip -Output $changed
    Assert-Rejected 'detección de registros perdidos' { & (Join-Path $tools 'compare-record-counts.ps1') -Reference $reference -Restored $changed } 'Comparación fallida'
    [void](Invoke-RecoveryQuery @connection -DatabaseName cuidado_restore_test_roundtrip -Sql 'INSERT INTO incidents VALUES (4, 2); DROP TABLE medications; CREATE TABLE unexpected (id integer);')
    $changedSchema = Join-Path $testRoot 'changed-schema.json'
    & (Join-Path $tools 'capture-record-counts.ps1') @connection -DatabaseName cuidado_restore_test_roundtrip -Output $changedSchema
    $layoutRows = [Collections.Generic.List[object]]::new()
    Assert-Rejected 'detección de tablas faltantes y adicionales' {
        & (Join-Path $tools 'compare-record-counts.ps1') -Reference $reference -Restored $changedSchema |
            ForEach-Object { $layoutRows.Add($_) }
    } 'Comparación fallida'
    if (@($layoutRows | Where-Object { $_.table -eq 'medications' -and $_.status -eq 'missing' }).Count -ne 1 -or
        @($layoutRows | Where-Object { $_.table -eq 'unexpected' -and $_.status -eq 'extra' }).Count -ne 1 -or
        @($layoutRows | Where-Object status -EQ different).Count -ne 0) {
        throw 'No se clasificaron correctamente las diferencias de tablas.'
    }
    $invalid = Get-Content -LiteralPath $reference -Raw | ConvertFrom-Json
    $invalid.tables += $invalid.tables[0]
    $duplicate = Join-Path $testRoot 'duplicate.json'
    $invalid | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $duplicate
    Assert-Rejected 'rechazo de manifiesto duplicado' { & (Join-Path $tools 'compare-record-counts.ps1') -Reference $duplicate -Restored $restored } 'Manifiesto inválido'
    Write-Host "OK: $($checks.Count) comprobaciones. Modo criptográfico: $cryptoMode."
    $checks | ForEach-Object { Write-Host "  OK: $_" }
} finally {
    if ($startAttempted) {
        try { [void](Invoke-TestNative 'pg_ctl' @('-D', $cluster, '-m', 'immediate', '-w', 'stop')); $stopped = $true }
        catch { Write-Warning "No se pudo detener el PostgreSQL temporal; conservar $testRoot para revisión." }
    }
    $env:PATH = $originalPath
    $env:PGPASSWORD = $originalPassword
    $env:RECOVERY_TEST_DECRYPT_FAIL = $originalDecryptFailure
    $resolvedRoot = [IO.Path]::GetFullPath($testRoot)
    $tempPrefix = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if ($stopped -and $resolvedRoot.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Path]::GetFileName($resolvedRoot).StartsWith('recovery-test-')) {
        Remove-Item -LiteralPath $resolvedRoot -Recurse -Force
    }
}
