#requires -Version 7.4
param(
    [Parameter(Mandatory)] [string]$DatabaseUrl,
    [Parameter(Mandatory)] [string]$AgeRecipient,
    [Parameter(Mandatory)] [string]$Destination
)

$ErrorActionPreference = "Stop"
foreach ($command in @("pg_dump", "age")) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Falta la herramienta requerida: $command"
    }
}

$resolvedDestination = [IO.Path]::GetFullPath($Destination)
if ([IO.Path]::GetExtension($resolvedDestination) -ne ".age") {
    throw "El destino debe terminar en .age"
}

$repository = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..')) + [IO.Path]::DirectorySeparatorChar
if ($resolvedDestination.StartsWith($repository, [StringComparison]::OrdinalIgnoreCase)) {
    throw "Guardar el respaldo fuera del repositorio."
}
if (Test-Path -LiteralPath $resolvedDestination) {
    throw "El destino ya existe; no se sobrescriben respaldos."
}

$destinationDirectory = Split-Path -Parent $resolvedDestination
New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null

# Los streams binarios evitan conversiones de texto y archivos temporales sin cifrar.
$partial = Join-Path $destinationDirectory ('.' + [guid]::NewGuid().ToString('N') + '.age.partial')
$dump = [Diagnostics.Process]::new()
$encrypt = [Diagnostics.Process]::new()
$started = [Collections.Generic.List[Diagnostics.Process]]::new()
try {
    foreach ($process in @($dump, $encrypt)) {
        $process.StartInfo.UseShellExecute = $false
        $process.StartInfo.CreateNoWindow = $true
        $process.StartInfo.RedirectStandardError = $true
    }
    $dump.StartInfo.FileName = (Get-Command pg_dump -CommandType Application | Select-Object -First 1).Source
    $dump.StartInfo.RedirectStandardOutput = $true
    foreach ($argument in @('--format=custom', '--no-owner', '--no-acl', '--no-password', "--dbname=$DatabaseUrl")) {
        $dump.StartInfo.ArgumentList.Add($argument)
    }
    $encrypt.StartInfo.FileName = (Get-Command age -CommandType Application | Select-Object -First 1).Source
    $encrypt.StartInfo.RedirectStandardInput = $true
    foreach ($argument in @('--recipient', $AgeRecipient, '--output', $partial)) {
        $encrypt.StartInfo.ArgumentList.Add($argument)
    }
    [void]$encrypt.Start()
    $started.Add($encrypt)
    $encryptErrors = $encrypt.StandardError.ReadToEndAsync()
    [void]$dump.Start()
    $started.Add($dump)
    $dumpErrors = $dump.StandardError.ReadToEndAsync()
    try {
        $dump.StandardOutput.BaseStream.CopyTo($encrypt.StandardInput.BaseStream)
    } finally {
        $encrypt.StandardInput.Close()
    }
    $dump.WaitForExit()
    $encrypt.WaitForExit()
    # Drenar stderr sin exponer URI, credenciales o detalles de la base en logs.
    [void]$dumpErrors.GetAwaiter().GetResult()
    [void]$encryptErrors.GetAwaiter().GetResult()
    if ($dump.ExitCode -ne 0 -or $encrypt.ExitCode -ne 0 -or
        -not (Test-Path -LiteralPath $partial) -or (Get-Item -LiteralPath $partial).Length -eq 0) {
        throw "Fallo de respaldo."
    }
    [IO.File]::Move($partial, $resolvedDestination)
} catch {
    throw "No se pudo generar el respaldo cifrado; revisar conexión, permisos, pg_dump y destinatario age."
} finally {
    foreach ($process in @($dump, $encrypt)) {
        if ($started.Contains($process) -and -not $process.HasExited) { $process.Kill($true); $process.WaitForExit() }
        $process.Dispose()
    }
    if (Test-Path -LiteralPath $partial) { Remove-Item -LiteralPath $partial -Force }
}

Write-Host "Respaldo cifrado creado fuera del repositorio: $resolvedDestination"
Write-Host "SHA256: $((Get-FileHash -LiteralPath $resolvedDestination -Algorithm SHA256).Hash)"
