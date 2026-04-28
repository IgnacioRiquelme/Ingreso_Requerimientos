@echo off
REM Levantador de aplicacion para XAMPP

setlocal enabledelayedexpansion

title Iniciando Aplicacion...

REM Obtener rutas
set SCRIPT_DIR=%~dp0
set PHP_BIN=C:\xampp\php\php.exe
set COMPOSER_BIN=C:\xampp\php\composer.phar

REM Verificar que PHP existe
if not exist "%PHP_BIN%" (
    echo ERROR: PHP no encontrado en %PHP_BIN%
    timeout /t 5
    exit /b 1
)

echo.
echo ========== Levantando Aplicacion ==========
echo.

REM 1. Composer
echo [1/2] Instalando dependencias Composer...
cd /d "%SCRIPT_DIR%"
"%PHP_BIN%" "%COMPOSER_BIN%" install --no-interaction
if %errorlevel% equ 0 (
    echo OK
) else (
    echo Intento con: composer install
    composer install --no-interaction
)
echo.

REM 2. Abrir navegador
echo [2/2] Abriendo navegador...
echo OK
echo.

echo =========================================
echo Aplicacion disponible en http://localhost
echo =========================================
echo.

start http://localhost/

pause

