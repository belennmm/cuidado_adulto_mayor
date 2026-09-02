param(
    [ValidateSet("smoke", "load", "stress", "spike")]
    [string]$Test = "smoke",
    [string]$BaseUrl = "http://host.docker.internal:8080/api"
)

$ErrorActionPreference = "Stop"
$performanceDir = $PSScriptRoot
$resultsDir = Join-Path $performanceDir "results"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$summaryName = "$Test-$timestamp-summary.json"
$summaryFile = "/scripts/results/$summaryName"
$localSummaryFile = Join-Path $resultsDir $summaryName

New-Item -ItemType Directory -Force -Path $resultsDir | Out-Null

$pingUrl = $BaseUrl.Replace("host.docker.internal", "localhost").TrimEnd("/") + "/ping"
try {
    $ping = Invoke-RestMethod -Uri $pingUrl -Method Get -TimeoutSec 10
    if (-not $ping.ok) {
        throw "La API respondió, pero no confirmó su estado."
    }
} catch {
    throw "La API no está disponible en $pingUrl. Ejecuta 'docker compose up -d --build' antes de la prueba."
}

docker compose exec -T backend php artisan db:seed --class=PerformanceTestSeeder --force
if ($LASTEXITCODE -ne 0) {
    throw "No fue posible preparar las cuentas exclusivas para las pruebas de rendimiento."
}

docker run --rm `
    -e "BASE_URL=$BaseUrl" `
    -v "${performanceDir}:/scripts" `
    grafana/k6:latest run `
    --summary-export=$summaryFile `
    "/scripts/$Test.js"

$k6ExitCode = $LASTEXITCODE

# k6 incluye los datos devueltos por setup() en el resumen. En este proyecto
# contienen tokens temporales, así que se eliminan antes de conservar el JSON.
if (Test-Path $localSummaryFile) {
    $summary = Get-Content -Raw $localSummaryFile | ConvertFrom-Json
    if ($summary.PSObject.Properties.Name -contains "setup_data") {
        $summary.PSObject.Properties.Remove("setup_data")
        $summary | ConvertTo-Json -Depth 100 | Set-Content -Encoding utf8 $localSummaryFile
    }
}

if ($k6ExitCode -ne 0) {
    throw "La prueba terminó con criterios incumplidos. Revisa el resumen guardado en $resultsDir."
}

Write-Host "Prueba finalizada. Resultado: $resultsDir"
