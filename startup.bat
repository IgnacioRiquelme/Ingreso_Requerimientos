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
echo [1/3] Instalando dependencias Composer...
cd /d "%SCRIPT_DIR%"
"%PHP_BIN%" "%COMPOSER_BIN%" install --no-interaction
if %errorlevel% equ 0 (
    echo OK
) else (
    echo Intento con: composer install
    composer install --no-interaction
)
echo.

REM 2. Resolver configuraciones
echo [2/3] Resolviendo configuraciones...
if exist "%SCRIPT_DIR%scripts\resolve_share.php" (
    "%PHP_BIN%" "%SCRIPT_DIR%scripts\resolve_share.php"
)
echo OK
echo.

REM 3. Sincronizar BD
echo [3/3] Sincronizando base de datos...
if exist "%SCRIPT_DIR%public\sync_excel_to_db.php" (
    "%PHP_BIN%" "%SCRIPT_DIR%public\sync_excel_to_db.php"
)
echo OK
echo.

echo =========================================
echo Aplicacion disponible en http://localhost
echo =========================================
echo.

start http://localhost/

pause

