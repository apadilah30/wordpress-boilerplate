# ==============================================================================
# db-dump.ps1 — Dump database untuk deployment ke server (Windows PowerShell)
#
# Usage:
#   .\scripts\db-dump.ps1 [-OutputFile "path\to\dump.sql.gz"]
#
# ==============================================================================

param(
    [string]$OutputFile
)

$ErrorActionPreference = "Stop"

# Load .env file
if (Test-Path ".env") {
    Get-Content ".env" | ForEach-Object {
        if ($_ -match "^([^#][^=]+)=(.*)$") {
            $name = $matches[1].Trim()
            $value = $matches[2].Trim()
            # Remove quotes if present
            if ($value -match '^"(.*)"$') { $value = $matches[1] }
            Set-Item -Path "env:$name" -Value $value
        }
    }
}

# Generate timestamp and output file
$timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
if (-not $OutputFile) {
    $OutputFile = "backups\db-dump-$timestamp.sql.gz"
}

# Create backups directory
$outputDir = Split-Path $OutputFile -Parent
if ($outputDir -and -not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

# Get database credentials
$dbHost = $env:DB_HOST
$dbName = $env:DB_NAME
$dbUser = $env:DB_USER
$dbPassword = $env:DB_PASSWORD

if (-not $dbName -or -not $dbUser -or -not $dbPassword) {
    Write-Host "ERROR: Database credentials not found in .env" -ForegroundColor Red
    exit 1
}

Write-Host "=== Database Dump ===" -ForegroundColor Cyan
Write-Host "Database: $dbName"
Write-Host "Host: $dbHost"
Write-Host "Output: $OutputFile"
Write-Host ""

# Check if Docker is available
$dockerAvailable = $false
try {
    docker --version | Out-Null
    $dockerAvailable = $true
} catch {
    Write-Host "Docker not available in terminal. Please run manually via Docker Desktop." -ForegroundColor Yellow
}

if ($dockerAvailable) {
    # Check if containers are running
    $containerStatus = docker compose ps db --format "{{.State}}" 2>$null
    if ($containerStatus -ne "running") {
        Write-Host "ERROR: Database container is not running." -ForegroundColor Red
        Write-Host "Start Docker Desktop and run: docker compose up -d" -ForegroundColor Yellow
        exit 1
    }
    
    Write-Host "Dumping database via Docker..." -ForegroundColor Green
    
    # Run mysqldump inside container
    $env:MYSQL_PWD = $dbPassword
    
    docker compose exec -T db mysqldump `
        --user=$dbUser `
        --single-transaction `
        --quick `
        --routines `
        --triggers `
        --default-character-set=utf8mb4 `
        $dbName | gzip -9 > $OutputFile
    
    Remove-Item env:MYSQL_PWD -ErrorAction SilentlyContinue
    
    # Verify
    if (Test-Path $OutputFile) {
        $size = (Get-Item $OutputFile).Length / 1MB
        Write-Host ""
        Write-Host "Dump completed successfully!" -ForegroundColor Green
        Write-Host "  File: $OutputFile"
        Write-Host "  Size: $([math]::Round($size, 2)) MB"
        Write-Host ""
        Write-Host "To restore on server (Linux):" -ForegroundColor Yellow
        Write-Host "  gunzip -c ${OutputFile} | mysql -u [user] -p [database]"
    } else {
        Write-Host "ERROR: Dump file not created" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host ""
    Write-Host "Please run this command manually in Docker Desktop terminal:" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  docker compose exec -T db mysqldump --user=$dbUser --password=`"$dbPassword`" --single-transaction --quick --routines --triggers --default-character-set=utf8mb4 $dbName > db-dump.sql"
    Write-Host ""
    Write-Host "Or use Docker Desktop GUI to access container shell."
}
