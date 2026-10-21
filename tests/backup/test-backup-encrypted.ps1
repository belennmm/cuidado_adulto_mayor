#requires -Version 7.4
# Prueba del transporte y errores con ejecutables simulados, NO del cifrado age.
$ErrorActionPreference = 'Stop'
$scriptPath = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../../tools/backup-encrypted.ps1'))
$compiler = Join-Path $env:WINDIR 'Microsoft.NET/Framework64/v4.0.30319/csc.exe'
if (-not (Test-Path -LiteralPath $compiler)) { throw 'Se requiere el compilador C# de .NET Framework en Windows.' }
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ('backup-test-' + [guid]::NewGuid().ToString('N'))
$originalPath = $env:PATH
$originalFailure = $env:BACKUP_TEST_FAILURE
New-Item -ItemType Directory -Path $testRoot | Out-Null
try {
    $source = Join-Path $testRoot 'FakeTool.cs'
    @'
using System;
using System.IO;
class FakeTool {
    static int Main(string[] args) {
        bool dump = Path.GetFileNameWithoutExtension(Environment.GetCommandLineArgs()[0]) == "pg_dump";
        string failure = Environment.GetEnvironmentVariable("BACKUP_TEST_FAILURE");
        if (dump) {
            byte[] bytes = new byte[] {80,71,68,77,80,0,255,128,13,10,0,254};
            using (Stream output = Console.OpenStandardOutput()) { output.Write(bytes, 0, bytes.Length); }
            return failure == "dump" ? 9 : 0;
        }
        string target = args[Array.IndexOf(args, "--output") + 1];
        using (Stream input = Console.OpenStandardInput())
        using (Stream output = File.Create(target)) { input.CopyTo(output); }
        return failure == "age" ? 8 : 0;
    }
}
'@ | Set-Content -LiteralPath $source
    & $compiler /nologo /target:exe "/out:$testRoot/pg_dump.exe" $source
    if ($LASTEXITCODE -ne 0) { throw 'No se pudo compilar el simulador.' }
    Copy-Item -LiteralPath (Join-Path $testRoot 'pg_dump.exe') -Destination (Join-Path $testRoot 'age.exe')
    $env:PATH = $testRoot + [IO.Path]::PathSeparator + $originalPath
    $env:BACKUP_TEST_FAILURE = ''
    $destination = Join-Path $testRoot 'success.age'
    & $scriptPath -DatabaseUrl 'synthetic_database' -AgeRecipient 'synthetic_recipient' -Destination $destination
    $expected = [byte[]]@(80,71,68,77,80,0,255,128,13,10,0,254)
    if ([Convert]::ToBase64String([IO.File]::ReadAllBytes($destination)) -ne [Convert]::ToBase64String($expected)) {
        throw 'El transporte alteró los bytes binarios.'
    }
    foreach ($mode in @('dump', 'age')) {
        $env:BACKUP_TEST_FAILURE = $mode
        $failedDestination = Join-Path $testRoot "$mode.age"
        $rejected = $false
        try { & $scriptPath -DatabaseUrl 'synthetic_database' -AgeRecipient 'synthetic_recipient' -Destination $failedDestination }
        catch { $rejected = $true }
        if (-not $rejected -or (Test-Path -LiteralPath $failedDestination)) { throw "No se detectó el fallo de $mode." }
        if (Get-ChildItem -LiteralPath $testRoot -Force -Filter '*.partial') { throw 'Quedó un respaldo parcial.' }
    }
    $env:BACKUP_TEST_FAILURE = ''
    foreach ($invalid in @($destination, (Join-Path $testRoot 'invalid.sql'), (Join-Path $PSScriptRoot 'forbidden.age'))) {
        $rejected = $false
        try { & $scriptPath -DatabaseUrl 'synthetic_database' -AgeRecipient 'synthetic_recipient' -Destination $invalid }
        catch { $rejected = $true }
        if (-not $rejected) { throw 'Se aceptó un destino prohibido.' }
    }
    if ([Convert]::ToBase64String([IO.File]::ReadAllBytes($destination)) -ne [Convert]::ToBase64String($expected)) {
        throw 'Se sobrescribió un respaldo existente.'
    }
    Write-Host 'OK: bytes binarios, fallo pg_dump, fallo age, limpieza, no sobrescritura, extensión y destino externo.'
} finally {
    $env:PATH = $originalPath
    $env:BACKUP_TEST_FAILURE = $originalFailure
    # Eliminar únicamente el directorio temporal único creado por esta prueba.
    $resolvedTestRoot = [IO.Path]::GetFullPath($testRoot)
    $tempPrefix = [IO.Path]::GetFullPath([IO.Path]::GetTempPath()).TrimEnd('\', '/') + [IO.Path]::DirectorySeparatorChar
    if ($resolvedTestRoot.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase) -and
        [IO.Path]::GetFileName($resolvedTestRoot).StartsWith('backup-test-')) {
        Remove-Item -LiteralPath $resolvedTestRoot -Recurse -Force
    }
}
