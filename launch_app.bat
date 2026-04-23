@echo off
REM Levanta la app de Ingreso de Requerimientos en http://localhost:8081

set PHP=C:\xampp\php\php.exe
set COMPOSER=C:\xampp\php\composer.phar
set APP_DIR=C:\Users\IARC\Desktop\Proyecto Ingreso ticket

cd /d "%APP_DIR%"

REM Instalar dependencias si no existen
if not exist "%APP_DIR%\vendor\autoload.php" (
    "%PHP%" "%COMPOSER%" install --no-interaction
)

REM Iniciar servidor PHP en puerto 8081 (sin ventana visible)
start "" /B "%PHP%" -S localhost:8081 -t public >nul 2>&1

REM Esperar un momento y abrir el navegador
timeout /t 2 /nobreak >nul
start http://localhost:8081/

exit /b 0
