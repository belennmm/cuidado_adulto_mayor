#requires -Version 7.4
param(
    [ValidateSet('127.0.0.1', 'localhost', '::1')] [string]$Server = '127.0.0.1',
    [Parameter(Mandatory)] [ValidateRange(1, 65535)] [int]$Port,
    [Parameter(Mandatory)] [string]$Username,
    [Parameter(Mandatory)] [ValidatePattern('^[a-z][a-z0-9_]*$')] [string]$DatabaseName,
    [Parameter(Mandatory)] [string]$Output,
    [string]$Reference
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'recovery-common.ps1')
if (Test-Path -LiteralPath $Output) { throw 'El manifiesto de destino ya existe.' }
$tables = @('users', 'older_adults', 'medications', 'older_adult_medications', 'rutinas', 'incidents', 'routine_notes', 'medication_administrations', 'medication_acquisitions')
# Tabla, columna, destino, admite NULL: corresponde a las migraciones de la aplicación.
$links = @(
    @('older_adults', 'created_by', 'users', $true),
    @('older_adults', 'professional_caregiver_id', 'users', $true),
    @('older_adults', 'family_caregiver_id', 'users', $true),
    @('older_adult_medications', 'older_adult_id', 'older_adults', $true),
    @('older_adult_medications', 'medication_id', 'medications', $false),
    @('rutinas', 'older_adult_id', 'older_adults', $false),
    @('rutinas', 'created_by', 'users', $true),
    @('incidents', 'older_adult_id', 'older_adults', $true),
    @('incidents', 'reported_by', 'users', $true),
    @('routine_notes', 'older_adult_id', 'older_adults', $false),
    @('routine_notes', 'professional_caregiver_id', 'users', $false),
    @('medication_administrations', 'older_adult_id', 'older_adults', $false),
    @('medication_administrations', 'older_adult_medication_id', 'older_adult_medications', $true),
    @('medication_administrations', 'medication_id', 'medications', $false),
    @('medication_administrations', 'recorded_by', 'users', $true),
    @('medication_acquisitions', 'medication_id', 'medications', $false),
    @('medication_acquisitions', 'older_adult_id', 'older_adults', $true)
)
$expectedLinkNames = @($links | ForEach-Object { '{0}.{1}->{2}.id' -f $_[0], $_[1], $_[2] })
if ($Reference) {
    $baseline = Get-Content -LiteralPath $Reference -Raw | ConvertFrom-Json
    if ($baseline.kind -ne 'care-recovery-validation' -or $baseline.version -ne 1 -or
        $baseline.tables -isnot [Array] -or $baseline.tables.Count -ne $tables.Count -or
        $baseline.relationships -isnot [Array] -or $baseline.relationships.Count -ne $links.Count) {
        throw 'Referencia de integridad inválida.'
    }
    $seen = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
    foreach ($row in $baseline.tables) {
        if ($row.table -cnotin $tables -or -not $seen.Add($row.table) -or
            $row.sha256 -cnotmatch '^[a-f0-9]{64}$' -or
            ($row.count -isnot [long] -and $row.count -isnot [int]) -or $row.count -lt 0) {
            throw 'Referencia de integridad inválida.'
        }
    }
    $seen.Clear()
    foreach ($row in $baseline.relationships) {
        if ($row.name -cnotin $expectedLinkNames -or -not $seen.Add($row.name) -or
            ($row.violations -isnot [long] -and $row.violations -isnot [int]) -or $row.violations -ne 0) {
            throw 'Referencia de integridad inválida.'
        }
    }
}
$sql = [Text.StringBuilder]::new()
[void]$sql.AppendLine("BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY; SET LOCAL timezone = 'UTC'; SET LOCAL datestyle = 'ISO, YMD'; SET LOCAL extra_float_digits = 3;")
foreach ($link in $links) {
    $table, $column, $parent, $nullable = $link
    $name = '{0}.{1}->{2}.id' -f $table, $column, $parent
    $nullCheck = if ($nullable) { '' } else { "c.$column IS NULL OR " }
    [void]$sql.AppendLine("SELECT json_build_object('kind','relationship','name','$name','violations',count(*))::text FROM public.$table c LEFT JOIN public.$parent p ON p.id=c.$column WHERE $nullCheck (c.$column IS NOT NULL AND p.id IS NULL);")
}
foreach ($table in $tables) {
    # Metadatos y restricciones entran en la huella incluso si la tabla está vacía.
    [void]$sql.AppendLine(@"
SELECT json_build_object('kind','table','table','$table',
 'columns',(SELECT json_agg(json_build_array(column_name, data_type, udt_name, is_nullable, column_default) ORDER BY ordinal_position) FROM information_schema.columns WHERE table_schema='public' AND table_name='$table'),
 'constraints',(SELECT json_agg(json_build_array(conname,pg_get_constraintdef(oid)) ORDER BY conname COLLATE "C") FROM pg_constraint WHERE conrelid='public.$table'::regclass))::text;
SELECT 'ROW:' || to_jsonb(t)::text FROM public.$table t ORDER BY to_jsonb(t)::text COLLATE "C";
"@)
}
[void]$sql.AppendLine('COMMIT;')
$process = New-RecoveryProcess 'psql' @('--host', $Server, '--port', "$Port", '--username', $Username,
    '--dbname', $DatabaseName, '--no-password', '--no-psqlrc', '--quiet', '--tuples-only', '--no-align', '--set', 'ON_ERROR_STOP=1', '--command', $sql.ToString())
$process.StartInfo.RedirectStandardOutput = $true
$process.StartInfo.StandardOutputEncoding = [Text.Encoding]::UTF8
$process.StartInfo.Environment['PGCLIENTENCODING'] = 'UTF8'
$results = [Collections.Generic.List[object]]::new()
$relations = [Collections.Generic.List[object]]::new()
$hasher = $null
$current = $null
$started = $false
try {
    [void]$process.Start()
    $started = $true
    $errors = $process.StandardError.ReadToEndAsync()
    while ($null -ne ($line = $process.StandardOutput.ReadLine())) {
        if (-not $line.Trim()) { continue }
        # Las filas nunca se convierten a objetos ni se imprimen en logs.
        if ($line.StartsWith('ROW:', [StringComparison]::Ordinal)) {
            if ($null -eq $hasher) { throw 'Stream inválido.' }
            $hasher.AppendData([Text.Encoding]::UTF8.GetBytes($line + "`n"))
            $current.count++
            continue
        }
        $metadata = $line | ConvertFrom-Json
        if ($metadata.kind -eq 'relationship') { $relations.Add($metadata); continue }
        if ($metadata.kind -ne 'table') { throw 'Stream inválido.' }
        if ($hasher) {
            $current.sha256 = [Convert]::ToHexString($hasher.GetHashAndReset()).ToLowerInvariant()
            $results.Add($current)
            $hasher.Dispose()
        }
        $current = [pscustomobject]@{ table = $metadata.table; count = [long]0; sha256 = '' }
        $hasher = [Security.Cryptography.IncrementalHash]::CreateHash([Security.Cryptography.HashAlgorithmName]::SHA256)
        $hasher.AppendData([Text.Encoding]::UTF8.GetBytes($line + "`n"))
    }
    $process.WaitForExit()
    [void]$errors.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) { throw 'Consulta fallida.' }
    if ($hasher) {
        $current.sha256 = [Convert]::ToHexString($hasher.GetHashAndReset()).ToLowerInvariant()
        $results.Add($current)
    }
} catch {
    throw 'No se pudo validar la recuperación; revisar esquema, conexión y permisos. No se muestran filas ni credenciales.'
} finally {
    if ($hasher) { $hasher.Dispose() }
    if ($started -and -not $process.HasExited) { $process.Kill($true); $process.WaitForExit() }
    $process.Dispose()
}
if ($results.Count -ne $tables.Count -or $relations.Count -ne $links.Count) { throw 'Validación incompleta.' }
if (@($relations | Where-Object violations -NE 0).Count -gt 0) { throw 'Integridad fallida: existen referencias huérfanas o relaciones obligatorias nulas.' }
if ($Reference) {
    $differences = @($results | Where-Object {
        $row = $_
        $original = $baseline.tables | Where-Object table -CEQ $row.table
        $row.count -ne $original.count -or $row.sha256 -cne $original.sha256
    })
    if ($differences.Count -gt 0) { throw "Integridad fallida: $($differences.Count) tablas difieren de la referencia en contenido o esquema." }
}
$manifest = [ordered]@{ kind = 'care-recovery-validation'; version = 1; capturedAtUtc = [DateTime]::UtcNow.ToString('o'); tables = @($results); relationships = @($relations) }
$bytes = [Text.Encoding]::UTF8.GetBytes(($manifest | ConvertTo-Json -Depth 8))
$stream = [IO.File]::Open([IO.Path]::GetFullPath($Output), [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
try { $stream.Write($bytes) } finally { $stream.Dispose() }
Write-Host "Integridad correcta: $($results.Count) tablas y $($relations.Count) relaciones. Referencia comparada: $([bool]$Reference)."
