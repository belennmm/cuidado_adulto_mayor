param(
    [Parameter(Mandatory = $true)][string]$AdminPassword,
    [Parameter(Mandatory = $true)][string]$ProfessionalPassword,
    [Parameter(Mandatory = $true)][string]$FamilyPassword,
    [ValidateSet('smoke', 'load', 'stress', 'volume100')]
    [string]$Profile = 'smoke',
    [string]$ApiUrl = 'http://host.docker.internal:8080/api',
    [string]$ReportDirectory = "$PSScriptRoot\reports"
)

$ErrorActionPreference = 'Stop'

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker no esta disponible. Inicia Docker Desktop antes de ejecutar k6.'
}

Write-Host 'Esperando a que la API local este disponible...'
$hostApiUrl = $ApiUrl.Replace('host.docker.internal', 'localhost')
$apiReady = $false
for ($attempt = 1; $attempt -le 15; $attempt++) {
    try {
        $null = Invoke-RestMethod -Uri "$hostApiUrl/ping" -Method Get -TimeoutSec 3
        $apiReady = $true
        break
    } catch {
        Start-Sleep -Seconds 2
    }
}

if (-not $apiReady) {
    throw "La API no respondio despues de 30 segundos: $hostApiUrl/ping"
}

Write-Host 'Preparando cuentas locales exclusivas para la prueba...'
docker exec --env "SECURITY_SCAN_ADMIN_PASSWORD=$AdminPassword" --env "SECURITY_SCAN_PROFESSIONAL_PASSWORD=$ProfessionalPassword" --env "SECURITY_SCAN_FAMILY_PASSWORD=$FamilyPassword" cuidado_backend php artisan db:seed --class=SecurityScanSeeder --force
if ($LASTEXITCODE -ne 0) {
    throw 'No fue posible preparar las cuentas locales de rendimiento.'
}

New-Item -ItemType Directory -Force -Path $ReportDirectory | Out-Null
$resolvedReportDirectory = (Resolve-Path -LiteralPath $ReportDirectory).Path
$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$summaryName = "k6-$Profile-$timestamp.json"

Write-Host "Ejecutando k6 con el perfil $Profile..."
docker run --rm `
    --volume "${PSScriptRoot}:/scripts:ro" `
    --volume "${resolvedReportDirectory}:/reports:rw" `
    --env "API_URL=$ApiUrl" `
    --env "TEST_PROFILE=$Profile" `
    --env "ADMIN_PASSWORD=$AdminPassword" `
    --env "PROFESSIONAL_PASSWORD=$ProfessionalPassword" `
    --env "FAMILY_PASSWORD=$FamilyPassword" `
    grafana/k6:latest run `
    --summary-export "/reports/$summaryName" `
    /scripts/volume-test.js

if ($LASTEXITCODE -ne 0) {
    throw "k6 detecto que uno o mas umbrales no se cumplieron (codigo $LASTEXITCODE)."
}

Write-Host "Prueba finalizada. Resumen: $resolvedReportDirectory\$summaryName"
