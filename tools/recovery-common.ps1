#requires -Version 7.4
# Funciones compartidas; no ejecuta operaciones al importar.
function New-RecoveryProcess {
    param([string]$Command, [string[]]$Arguments)
    $process = [Diagnostics.Process]::new()
    $process.StartInfo.FileName = (Get-Command $Command -CommandType Application -ErrorAction Stop | Select-Object -First 1).Source
    $process.StartInfo.UseShellExecute = $false
    $process.StartInfo.CreateNoWindow = $true
    $process.StartInfo.RedirectStandardError = $true
    foreach ($argument in $Arguments) { $process.StartInfo.ArgumentList.Add($argument) }
    return $process
}

function Invoke-RecoveryQuery {
    param([string]$Server, [int]$Port, [string]$Username, [string]$DatabaseName, [string]$Sql)
    $process = New-RecoveryProcess 'psql' @(
        '--host', $Server, '--port', "$Port", '--username', $Username, '--dbname', $DatabaseName,
        '--no-password', '--no-psqlrc', '--quiet', '--tuples-only', '--no-align', '--set', 'ON_ERROR_STOP=1', '--set', 'VERBOSITY=sqlstate'
    )
    $process.StartInfo.RedirectStandardInput = $true
    $process.StartInfo.RedirectStandardOutput = $true
    $process.StartInfo.StandardInputEncoding = [Text.UTF8Encoding]::new($false)
    $process.StartInfo.StandardOutputEncoding = [Text.Encoding]::UTF8
    $process.StartInfo.Environment['PGCLIENTENCODING'] = 'UTF8'
    $sqlState = ''
    try {
        [void]$process.Start()
        $errors = $process.StandardError.ReadToEndAsync()
        $output = $process.StandardOutput.ReadToEndAsync()
        $process.StandardInput.Write($Sql)
        $process.StandardInput.Close()
        $process.WaitForExit()
        $errorOutput = $errors.GetAwaiter().GetResult()
        $result = $output.GetAwaiter().GetResult()
        if ($process.ExitCode -ne 0) {
            if ($errorOutput -match '(?:ERROR|FATAL):\s+([0-9A-Z]{5})\b') { $sqlState = " SQLSTATE: $($Matches[1])." }
            throw 'Consulta fallida.'
        }
        return $result.Trim()
    } catch {
        throw "No se pudo consultar PostgreSQL; revisar conexión y permisos.$sqlState No se muestran datos de la conexión."
    } finally {
        $process.Dispose()
    }
}

function Read-RecoveryCounts {
    param([string]$Path)
    $manifest = Get-Content -LiteralPath $Path -Raw | ConvertFrom-Json
    if ($manifest.version -ne 1 -or $manifest.tables -isnot [Array] -or $manifest.tables.Count -eq 0) {
        throw 'Manifiesto inválido: se requieren versión 1 y tablas.'
    }
    $seen = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
    foreach ($row in $manifest.tables) {
        $key = ConvertTo-Json -InputObject @($row.schema, $row.table) -Compress
        if ($row.schema -isnot [string] -or [string]::IsNullOrWhiteSpace($row.schema) -or
            $row.table -isnot [string] -or [string]::IsNullOrWhiteSpace($row.table) -or
            ($row.count -isnot [long] -and $row.count -isnot [int]) -or $row.count -lt 0 -or -not $seen.Add($key)) {
            throw 'Manifiesto inválido: tabla duplicada, nombre o cantidad inválida.'
        }
    }
    return $manifest
}
