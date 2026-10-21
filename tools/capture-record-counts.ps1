#requires -Version 7.4
param(
    [ValidateSet('127.0.0.1', 'localhost', '::1')] [string]$Server = '127.0.0.1',
    [Parameter(Mandatory)] [ValidateRange(1, 65535)] [int]$Port,
    [Parameter(Mandatory)] [string]$Username,
    [Parameter(Mandatory)] [ValidatePattern('^[a-z][a-z0-9_]*$')] [string]$DatabaseName,
    [Parameter(Mandatory)] [string]$Output
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'recovery-common.ps1')
if (Test-Path -LiteralPath $Output) { throw 'El manifiesto de destino ya existe.' }
# Una instantánea consistente para todos los conteos; sin contenido de registros.
$sql = @'
BEGIN TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY;
SELECT format(
  'SELECT json_build_object(''schema'', %L, ''table'', %L, ''count'', count(*))::text FROM %I.%I;',
  schemaname, tablename, schemaname, tablename)
FROM pg_tables
WHERE schemaname NOT IN ('pg_catalog', 'information_schema')
  AND schemaname !~ '^pg_toast'
ORDER BY schemaname, tablename
\gexec
COMMIT;
'@
$result = Invoke-RecoveryQuery -Server $Server -Port $Port -Username $Username -DatabaseName $DatabaseName -Sql $sql
$tables = @($result -split '\r?\n' | Where-Object { $_.Trim() } | ForEach-Object { $_ | ConvertFrom-Json })
if ($tables.Count -eq 0) { throw 'La base no contiene tablas; no se genera una referencia vacía.' }
$manifest = [ordered]@{ version = 1; capturedAtUtc = [DateTime]::UtcNow.ToString('o'); tables = $tables }
$json = $manifest | ConvertTo-Json -Depth 5
# CreateNew también evita sobrescrituras ante ejecuciones concurrentes.
$stream = [IO.File]::Open([IO.Path]::GetFullPath($Output), [IO.FileMode]::CreateNew, [IO.FileAccess]::Write)
try {
    $bytes = [Text.Encoding]::UTF8.GetBytes($json)
    $stream.Write($bytes)
} finally { $stream.Dispose() }
Write-Host "Referencia creada: $($tables.Count) tablas."
