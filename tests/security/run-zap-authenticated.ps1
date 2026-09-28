param(
    [string]$ApiUrl = "http://localhost:8080/api",
    [string]$AdminEmail = "zap.admin@example.test",
    [string]$AdminPassword = "ZapAdmin-2026!",
    [string]$ProfessionalEmail = "zap.professional@example.test",
    [string]$ProfessionalPassword = "ZapProfessional-2026!",
    [string]$FamilyEmail = "zap.family@example.test",
    [string]$FamilyPassword = "ZapFamily-2026!",
    [string]$ReportDirectory = "$PSScriptRoot\reports\authenticated",
    [switch]$Active
)

$ErrorActionPreference = "Stop"

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker no esta disponible. Inicia Docker Desktop antes de ejecutar ZAP."
}

Write-Host "Esperando a que la API local este disponible..."
$apiReady = $false
for ($attempt = 1; $attempt -le 15; $attempt++) {
    try {
        $null = Invoke-RestMethod -Uri "$ApiUrl/ping" -Method Get -TimeoutSec 3
        $apiReady = $true
        break
    } catch {
        Start-Sleep -Seconds 2
    }
}

if (-not $apiReady) {
    throw "La API no respondio despues de 30 segundos: $ApiUrl/ping"
}

Write-Host "Preparando cuentas locales exclusivas para el escaneo..."
docker exec cuidado_backend php artisan db:seed --class=SecurityScanSeeder --force
if ($LASTEXITCODE -ne 0) {
    throw "No fue posible preparar las cuentas locales de OWASP ZAP."
}

$openApiPath = Join-Path $PSScriptRoot "openapi-security.yaml"
if (-not (Test-Path -LiteralPath $openApiPath)) {
    throw "No se encontro la especificacion OpenAPI: $openApiPath"
}

New-Item -ItemType Directory -Force -Path $ReportDirectory | Out-Null
$resolvedReportDirectory = (Resolve-Path -LiteralPath $ReportDirectory).Path

function Get-AccessToken {
    param([string]$Email, [string]$Password)

    $body = @{ email = $Email; password = $Password } | ConvertTo-Json
    $response = $null
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            $response = Invoke-RestMethod -Uri "$ApiUrl/login" -Method Post -ContentType "application/json" -Body $body -TimeoutSec 10
            break
        } catch {
            if ($attempt -eq 3) { throw }
            Start-Sleep -Seconds 2
        }
    }

    if (-not $response.token) {
        throw "El inicio de sesion de $Email no devolvio un token."
    }

    return $response.token
}

$roles = @(
    @{ Name = "admin"; Email = $AdminEmail; Password = $AdminPassword },
    @{ Name = "professional"; Email = $ProfessionalEmail; Password = $ProfessionalPassword },
    @{ Name = "family"; Email = $FamilyEmail; Password = $FamilyPassword }
)

foreach ($role in $roles) {
    Write-Host "Obteniendo sesion temporal para el rol $($role.Name)..."
    $token = Get-AccessToken -Email $role.Email -Password $role.Password

    $dockerArgs = @(
        "run", "--rm",
        "--volume", "${PSScriptRoot}:/zap/wrk/config:ro",
        "--volume", "${resolvedReportDirectory}:/zap/wrk/reports:rw",
        "--env", "ZAP_AUTH_HEADER_VALUE=Bearer $token",
        "--env", "ZAP_AUTH_HEADER_SITE=host.docker.internal",
        "--tty",
        "ghcr.io/zaproxy/zaproxy:stable",
        "zap-api-scan.py",
        "-t", "/zap/wrk/config/openapi-security.yaml",
        "-f", "openapi",
        "-I",
        "-r", "reports/zap-$($role.Name).html",
        "-J", "reports/zap-$($role.Name).json",
        "-w", "reports/zap-$($role.Name).md"
    )

    if (-not $Active) {
        $dockerArgs += "-S"
    }

    Write-Host "Escaneando como $($role.Name) en modo $(if ($Active) { 'activo' } else { 'seguro' })..."
    & docker @dockerArgs

    if ($LASTEXITCODE -ne 0) {
        throw "OWASP ZAP no pudo completar el escaneo del rol $($role.Name) (codigo $LASTEXITCODE)."
    }

    Remove-Variable token
}

Write-Host "Escaneos autenticados finalizados. Reportes: $resolvedReportDirectory"
