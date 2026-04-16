param([switch]$SkipBrowser)

$ProjectRoot = Split-Path -Path $MyInvocation.MyCommand.Definition -Parent

Write-Host ""
Write-Host "========== Levantando Aplicacion ==========" -ForegroundColor Cyan
Write-Host ""

# 1. Composer
Write-Host "[1/4] Instalando dependencias Composer..." -ForegroundColor Yellow
Push-Location $ProjectRoot
composer install --no-interaction
Pop-Location
Write-Host "OK`n" -ForegroundColor Green

# 2. Resolve Share
if (Test-Path "$ProjectRoot\scripts\resolve_share.php") {
    Write-Host "[2/4] Resolviendo configuraciones..." -ForegroundColor Yellow
    Push-Location $ProjectRoot
    php scripts\resolve_share.php 2>$null
    Pop-Location
    Write-Host "OK`n" -ForegroundColor Green
}

# 3. Sincronizar BD
if (Test-Path "$ProjectRoot\public\sync_excel_to_db.php") {
    Write-Host "[3/4] Sincronizando base de datos..." -ForegroundColor Yellow
    Push-Location $ProjectRoot
    php public\sync_excel_to_db.php 2>$null
    Pop-Location
    Write-Host "OK`n" -ForegroundColor Green
}

# 4. Abrir navegador
Write-Host "[4/4] Abriendo navegador..." -ForegroundColor Yellow
if (-not $SkipBrowser) {
    Start-Process "http://localhost/"
}
Write-Host "OK`n" -ForegroundColor Green

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "COMPLETADO - Aplicacion en http://localhost" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

Read-Host "Presiona Enter para salir"
