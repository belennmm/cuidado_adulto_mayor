param(
    [Parameter(Mandatory)] [string]$DatabaseUrl,
    [Parameter(Mandatory)] [string]$AgeRecipient,
    [Parameter(Mandatory)] [string]$Destination
)

$ErrorActionPreference = "Stop"
foreach ($command in @("pg_dump", "age")) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Falta la herramienta requerida: $command"
    }
}

$resolvedDestination = [IO.Path]::GetFullPath($Destination)
if ([IO.Path]::GetExtension($resolvedDestination) -ne ".age") {
    throw "El destino debe terminar en .age"
}

$destinationDirectory = Split-Path -Parent $resolvedDestination
New-Item -ItemType Directory -Force -Path $destinationDirectory | Out-Null

pg_dump --format=custom --no-owner --no-acl --dbname=$DatabaseUrl |
    age --recipient $AgeRecipient --output $resolvedDestination

if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $resolvedDestination)) {
    throw "No se pudo generar el respaldo cifrado."
}

Write-Host "Respaldo cifrado creado fuera del repositorio: $resolvedDestination"
