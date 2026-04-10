# Run development setup: installs deps, resolves share, and starts PHP server on :8081
param()
Write-Host "Instalando dependencias Composer..."
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    Write-Host "Composer no está disponible en PATH. Instálalo primero." -ForegroundColor Red
    exit 1
}
Push-Location (Split-Path -Path $MyInvocation.MyCommand.Definition -Parent)
composer install

Write-Host "Resolviendo driveItem desde la URL compartida..."
php scripts\resolve_share.php

Write-Host "Iniciando servidor PHP en http://localhost:8081 (usa Ctrl+C para detener)"
Start-Process -NoNewWindow -FilePath php -ArgumentList "-S localhost:8081 -t public"
Start-Sleep -Seconds 1
Start-Process "http://localhost:8081/"
Pop-Location
