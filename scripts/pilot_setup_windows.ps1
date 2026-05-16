param(
    [switch]$WithImport,
    [switch]$NoServe,
    [string]$Host = "0.0.0.0",
    [int]$Port = 8081
)

$ErrorActionPreference = "Stop"

function Write-Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Read-EnvValue {
    param(
        [string]$EnvContent,
        [string]$Key
    )

    $pattern = "(?m)^$Key=(.*)$"
    $match = [regex]::Match($EnvContent, $pattern)

    if ($match.Success) {
        return $match.Groups[1].Value.Trim()
    }

    return $null
}

if (-not (Test-Path ".\artisan")) {
    throw "Run this script from the Laravel project root."
}

if (-not (Test-Path ".\.env")) {
    throw ".env file not found. Copy .env.pilot.example to .env first."
}

$envContent = Get-Content ".\.env" -Raw

Write-Step "Checking vendor dependencies"
if (-not (Test-Path ".\vendor\autoload.php")) {
    throw "vendor folder is missing. Run composer install first."
}

$appKey = Read-EnvValue -EnvContent $envContent -Key "APP_KEY"
if ([string]::IsNullOrWhiteSpace($appKey)) {
    Write-Step "Generating app key"
    php artisan key:generate --force
}
else {
    Write-Step "Keeping existing app key"
}

Write-Step "Running database migrations"
php artisan migrate --seed --force

Write-Step "Creating storage link if needed"
php artisan storage:link 2>$null

if ($WithImport) {
    Write-Step "Importing legacy data"
    php artisan access:import-core-data

    Write-Step "Auditing imported data"
    php artisan access:audit-import
}

Write-Step "Refreshing caches"
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

Write-Step "Running readiness check"
php artisan ops:go-live-check

if (-not $NoServe) {
    Write-Step "Starting pilot server at http://${Host}:${Port}"
    php artisan serve --host=$Host --port=$Port
}
