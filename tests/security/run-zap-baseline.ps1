param(
    [string]$Target = "http://host.docker.internal:3000",
    [string]$ReportDirectory = "$PSScriptRoot\reports"
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker no esta disponible. Instala o inicia Docker Desktop antes de ejecutar ZAP."
}

New-Item -ItemType Directory -Force -Path $ReportDirectory | Out-Null
$resolvedReportDirectory = (Resolve-Path -LiteralPath $ReportDirectory).Path

Write-Host "Ejecutando OWASP ZAP Baseline contra $Target"
Write-Host "Los reportes se guardaran en $resolvedReportDirectory"

docker run --rm `
    --volume "${resolvedReportDirectory}:/zap/wrk/:rw" `
    --tty `
    ghcr.io/zaproxy/zaproxy:stable `
    zap-baseline.py `
    -t $Target `
    -m 2 `
    -I `
    -r zap-baseline.html `
    -J zap-baseline.json `
    -w zap-baseline.md

if ($LASTEXITCODE -ne 0) {
    throw "OWASP ZAP no pudo completar el escaneo (codigo $LASTEXITCODE)."
}

Write-Host "Escaneo finalizado. Abre reports/zap-baseline.html para revisar los hallazgos."
