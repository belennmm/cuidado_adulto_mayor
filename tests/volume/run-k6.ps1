param(
    [string]$BaseUrl = "http://localhost:8080/api",
    [string]$Email = "zap.admin@example.test",
    [string]$Password = "ZapAdmin-2026!",
    [string]$ReportDirectory = "$PSScriptRoot\reports"
)

$ErrorActionPreference = "Stop"
$scriptPath = Join-Path $PSScriptRoot "api-volume.js"
if (-not (Test-Path -LiteralPath $scriptPath)) {
    throw "No se encontro el script k6: $scriptPath"
}

for ($attempt = 1; $attempt -le 15; $attempt++) {
    try {
        $null = Invoke-RestMethod -Uri "$BaseUrl/ping" -Method Get -TimeoutSec 3
        break
    } catch {
        if ($attempt -eq 15) { throw "La API no esta disponible: $BaseUrl/ping" }
        Start-Sleep -Seconds 2
    }
}

New-Item -ItemType Directory -Force -Path $ReportDirectory | Out-Null
$resolvedScript = (Resolve-Path -LiteralPath $scriptPath).Path
$resolvedReport = (Resolve-Path -LiteralPath $ReportDirectory).Path
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$summaryPath = Join-Path $resolvedReport "k6-$timestamp.json"
$k6BaseUrl = $BaseUrl -replace '://localhost(?=[:/])', '://host.docker.internal' -replace '://127\.0\.0\.1(?=[:/])', '://host.docker.internal'

$dockerArgs = @(
    "run", "--rm",
    "--add-host=host.docker.internal:host-gateway",
    "--volume", "${resolvedScript}:/scripts/api-volume.js:ro",
    "--volume", "${resolvedReport}:/reports:rw",
    "--env", "BASE_URL=$k6BaseUrl",
    "--env", "K6_EMAIL=$Email",
    "--env", "K6_PASSWORD=$Password",
    "grafana/k6:latest",
    "run", "--summary-export", "/reports/summary-$timestamp.json",
    "/scripts/api-volume.js"
)

Write-Host "Ejecutando prueba de volumen k6 contra $BaseUrl"
& docker @dockerArgs
if ($LASTEXITCODE -ne 0) {
    throw "k6 finalizo con codigo $LASTEXITCODE. Revise el resumen en $resolvedReport"
}

Write-Host "Prueba completada. Reportes: $resolvedReport"
