param([switch]$SkipBrowser)

$ProjectRoot = Split-Path -Path $MyInvocation.MyCommand.Definition -Parent

Write-Host ""
Write-Host "========== Levantando Aplicacion ==========" -ForegroundColor Cyan
Write-Host ""

# 1. Composer
Write-Host "[1/2] Instalando dependencias Composer..." -ForegroundColor Yellow
Push-Location $ProjectRoot
composer install --no-interaction
Pop-Location
Write-Host "OK`n" -ForegroundColor Green

# 2. Abrir navegador
Write-Host "[2/2] Abriendo navegador..." -ForegroundColor Yellow
if (-not $SkipBrowser) {
    Start-Process "http://localhost/"
}
Write-Host "OK`n" -ForegroundColor Green

Write-Host "=========================================" -ForegroundColor Cyan
Write-Host "COMPLETADO - Aplicacion en http://localhost" -ForegroundColor Green
Write-Host "=========================================" -ForegroundColor Cyan
Write-Host ""

Read-Host "Presiona Enter para salir"
