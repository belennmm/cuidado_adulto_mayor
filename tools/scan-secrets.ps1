param(
    [string]$Path = "."
)

$ErrorActionPreference = "Stop"
$blockedExtensions = @(".sql", ".dump", ".backup", ".bak", ".pem", ".key", ".p12", ".pfx")
$secretPatterns = @(
    '(?i)-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----',
    '(?i)(api[_-]?key|secret|password|token)\s*[:=]\s*["'']?[A-Za-z0-9_+/=-]{20,}',
    'AKIA[0-9A-Z]{16}',
    'gh[pousr]_[A-Za-z0-9]{30,}'
)

$files = git -C $Path ls-files
$failures = New-Object System.Collections.Generic.List[string]

foreach ($file in $files) {
    $fullPath = Join-Path $Path $file
    if (-not (Test-Path -LiteralPath $fullPath -PathType Leaf)) { continue }

    $extension = [IO.Path]::GetExtension($file).ToLowerInvariant()
    if ($blockedExtensions -contains $extension) {
        $failures.Add("Archivo sensible versionado: $file")
        continue
    }

    $content = Get-Content -LiteralPath $fullPath -Raw -ErrorAction SilentlyContinue
    foreach ($pattern in $secretPatterns) {
        if ($content -match $pattern) {
            $failures.Add("Posible secreto en: $file")
            break
        }
    }
}

if ($failures.Count -gt 0) {
    $failures | Sort-Object -Unique | ForEach-Object { Write-Error $_ }
    exit 1
}

Write-Host "No se detectaron secretos ni respaldos prohibidos en archivos versionados."
