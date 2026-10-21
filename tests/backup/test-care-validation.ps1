# Invocado desde test-recovery.ps1, dentro del clúster temporal y sus funciones.
param([hashtable]$Connection, [string]$TestRoot, [string]$Reference, [string]$Tools)
$validator = Join-Path $Tools 'validate-recovered-data.ps1'
$database = 'cuidado_restore_test_roundtrip'
$result = Join-Path $TestRoot 'care-restored.json'
& $validator @Connection -DatabaseName $database -Reference $Reference -Output $result
$manifest = Get-Content -LiteralPath $result -Raw | ConvertFrom-Json
if ($manifest.tables.Count -ne 9 -or $manifest.relationships.Count -ne 17) { throw 'Validación incompleta.' }
$checks.Add('9 tablas idénticas en valores/esquema y 17 relaciones íntegras tras restaurar')
$checks.Add('NULL permitidos en inventario sin asignar y registros históricos')
$mutations = @(
    @('contraseña del usuario', "UPDATE users SET password='synthetic-tampered-hash' WHERE id=1;", "UPDATE users SET password='synthetic-hash-admin' WHERE id=1;"),
    @('rol del usuario', "UPDATE users SET role='familiar' WHERE id=1;", "UPDATE users SET role='admin' WHERE id=1;"),
    @('medicamento', "UPDATE medications SET name='Modificado' WHERE id=1;", "UPDATE medications SET name='Medicamento sintético A' WHERE id=1;"),
    @('dosis', "UPDATE older_adult_medications SET dosage='2 tabletas' WHERE id=1;", "UPDATE older_adult_medications SET dosage='1 tableta' WHERE id=1;"),
    @('rutina', "UPDATE rutinas SET actividades='[""Otra actividad""]' WHERE id=1;", "UPDATE rutinas SET actividades='[""Movilidad"",""Hidratación""]' WHERE id=1;"),
    @('incidente', "UPDATE incidents SET description='Modificado' WHERE id=1;", "UPDATE incidents SET description='Incidente sintético' WHERE id=1;"),
    @('relación con otro adulto existente', 'UPDATE incidents SET older_adult_id=2 WHERE id=1;', 'UPDATE incidents SET older_adult_id=1 WHERE id=1;'),
    @('nota de rutina', "UPDATE routine_notes SET content='Modificada' WHERE id=1;", "UPDATE routine_notes SET content='Nota sintética' WHERE id=1;"),
    @('administración de medicamento', 'UPDATE medication_administrations SET medication_id=2 WHERE id=1;', 'UPDATE medication_administrations SET medication_id=1 WHERE id=1;'),
    @('adquisición de medicamento', 'UPDATE medication_acquisitions SET quantity=31 WHERE id=1;', 'UPDATE medication_acquisitions SET quantity=30 WHERE id=1;')
)
foreach ($mutation in $mutations) {
    [void](Invoke-RecoveryQuery @Connection -DatabaseName $database -Sql $mutation[1])
    Assert-Rejected "detección de cambio sin alterar conteos: $($mutation[0])" {
        & $validator @Connection -DatabaseName $database -Reference $Reference -Output (Join-Path $TestRoot 'must-not-exist.json')
    } 'difieren de la referencia'
    if (Test-Path -LiteralPath (Join-Path $TestRoot 'must-not-exist.json')) { throw 'Se publicó un manifiesto válido ante una alteración.' }
    [void](Invoke-RecoveryQuery @Connection -DatabaseName $database -Sql $mutation[2])
}
# Confirmar que las mutaciones fueron revertidas y la copia sigue coincidiendo.
& $validator @Connection -DatabaseName $database -Reference $Reference -Output (Join-Path $TestRoot 'care-rechecked.json')
$checks.Add('referencia vuelve a coincidir al revertir las alteraciones sintéticas')
[void](Invoke-RecoveryQuery @Connection -DatabaseName $database -Sql 'ALTER TABLE incidents DROP CONSTRAINT incidents_older_adult_id_fkey; UPDATE incidents SET older_adult_id=999 WHERE id=1;')
Assert-Rejected 'detección de huérfanos aun sin constraint y sin referencia' {
    & $validator @Connection -DatabaseName $database -Output (Join-Path $TestRoot 'orphan.json')
} 'referencias huérfanas'
[void](Invoke-RecoveryQuery @Connection -DatabaseName $database -Sql 'UPDATE incidents SET older_adult_id=1 WHERE id=1; ALTER TABLE older_adult_medications ALTER COLUMN medication_id DROP NOT NULL; UPDATE older_adult_medications SET medication_id=NULL WHERE id=1;')
Assert-Rejected 'detección de relación obligatoria nula sin referencia' {
    & $validator @Connection -DatabaseName $database -Output (Join-Path $TestRoot 'null-link.json')
} 'relaciones obligatorias nulas'
Write-Host "OK: $($checks.Count) comprobaciones de recuperación clínica. Modo criptográfico: $cryptoMode."
$checks | ForEach-Object { Write-Host "  OK: $_" }
