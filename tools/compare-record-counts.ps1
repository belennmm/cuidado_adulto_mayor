#requires -Version 7.4
param(
    [Parameter(Mandatory)] [string]$Reference,
    [Parameter(Mandatory)] [string]$Restored
)
$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'recovery-common.ps1')
$before = Read-RecoveryCounts $Reference
$after = Read-RecoveryCounts $Restored
$expected = [Collections.Generic.Dictionary[string, object]]::new([StringComparer]::Ordinal)
$actual = [Collections.Generic.Dictionary[string, object]]::new([StringComparer]::Ordinal)
foreach ($row in $before.tables) { $expected.Add((ConvertTo-Json -InputObject @($row.schema, $row.table) -Compress), $row) }
foreach ($row in $after.tables) { $actual.Add((ConvertTo-Json -InputObject @($row.schema, $row.table) -Compress), $row) }
$keys = [Collections.Generic.HashSet[string]]::new($expected.Keys, [StringComparer]::Ordinal)
$keys.UnionWith($actual.Keys)
$differences = 0
foreach ($key in ($keys | Sort-Object -CaseSensitive)) {
    $left = $expected[$key]
    $right = $actual[$key]
    $status = if ($null -eq $left) { 'extra' } elseif ($null -eq $right) { 'missing' } elseif ($left.count -ne $right.count) { 'different' } else { 'equal' }
    if ($status -ne 'equal') { $differences++ }
    [pscustomobject]@{
        schema = if ($left) { $left.schema } else { $right.schema }
        table = if ($left) { $left.table } else { $right.table }
        reference = if ($left) { $left.count } else { $null }
        restored = if ($right) { $right.count } else { $null }
        status = $status
    }
}
if ($differences -gt 0) { throw "Comparación fallida: $differences tablas con diferencias." }
Write-Host "Comparación correcta: $($keys.Count) tablas con las mismas cantidades."
