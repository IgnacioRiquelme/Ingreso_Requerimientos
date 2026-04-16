# 🚀 Guía: Levantamiento de Aplicación en XAMPP

## Resumen

Se han creado dos scripts para levantar automáticamente la aplicación después de un reinicio:

1. **`startup.bat`** - Script rápido (para usuarios que no conocen PowerShell)
2. **`startup.ps1`** - Script completo con validaciones y logs detallados (recomendado)

---

## 📋 Requisitos Previos

Antes de ejecutar los scripts, asegúrate de tener:

✅ **XAMPP instalado** (ruta por defecto: `C:\xampp`)  
✅ **Apache corriendo en XAMPP** (desde XAMPP Control Panel)  
✅ **Composer instalado** (https://getcomposer.org/download/)  
✅ **PowerShell con permisos de administrador** (para el script .ps1)  

---

## 🎯 Uso Rápido

### Opción 1: Script BAT (más fácil)

```bash
1. Haz clic derecho en "startup.bat"
2. Selecciona "Ejecutar como administrador"
3. El script hará todo automáticamente
```

### Opción 2: Script PowerShell (más control)

```powershell
# Abre PowerShell como Administrador y ejecuta:
cd "C:\Users\IARC\Desktop\Proyecto Ingreso ticket"
.\startup.ps1
```

---

## ✅ Qué hace cada script

1. ✓ **Verifica XAMPP** - Comprueba que Apache esté disponible
2. ✓ **Inicia Apache** - Si no está corriendo, lo levanta
3. ✓ **Instala dependencias** - Ejecuta `composer install` si es necesario
4. ✓ **Resuelve configuraciones** - Ejecuta `scripts/resolve_share.php`
5. ✓ **Sincroniza BD** - Ejecuta `sync_excel_to_db.php` si es necesario
6. ✓ **Verifica permisos** - Asegura que `/storage` sea escribible
7. ✓ **Abre el navegador** - Automáticamente va a `http://localhost`

---

## 🔧 Configuración de XAMPP

Si la aplicación no levanta después de ejecutar los scripts, verifica:

### 1. DocumentRoot correcto

Abre `C:\xampp\apache\conf\httpd.conf` y busca:

```
DocumentRoot "C:/xampp/htdocs"
```

Cambia a la ruta de tu proyecto:

```
DocumentRoot "C:/Users/IARC/Desktop/Proyecto Ingreso ticket/public"

<Directory "C:/Users/IARC/Desktop/Proyecto Ingreso ticket/public">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### 2. Módulo mod_rewrite habilitado

En el mismo archivo, busca:

```
#LoadModule rewrite_module modules/mod_rewrite.so
```

Si está comentado (tiene `#`), descomenta:

```
LoadModule rewrite_module modules/mod_rewrite.so
```

### 3. Reiniciar Apache

Después de cambios en configuración:

```powershell
# En PowerShell como admin:
net stop Apache2.4
Start-Sleep -Seconds 2
net start Apache2.4
```

---

## 🐛 Troubleshooting

### ❌ "Apache service not found"

**Problema:** El servicio Apache no se llama `Apache2.4`

**Solución:**
```powershell
# En PowerShell como admin, lista los servicios:
Get-Service | findstr Apache
```

Nota el nombre exacto y edita `startup.ps1` línea 10:
```powershell
$XAMPP_Apache_ServiceName = "TU_SERVICIO_AQUI"
```

---

### ❌ "Composer not found"

**Problema:** Composer no está en PATH

**Solución:**
1. Descarga composer desde: https://getcomposer.org/download/
2. Usa el instalador para Windows
3. Reinicia PowerShell después de instalar

---

### ❌ Aplicación no carga en http://localhost

**Verifica:**

1. Apache está corriendo:
```powershell
Get-Service Apache2.4
```
Debe decir: **Status : Running**

2. Revisa los logs de Apache:
```
C:\xampp\apache\logs\error.log
C:\xampp\apache\logs\access.log
```

3. Confirma el DocumentRoot en `httpd.conf`:
```
grep DocumentRoot C:\xampp\apache\conf\httpd.conf
```

4. Verifica que PHP está habilitado:
```
grep LoadModule.*php C:\xampp\apache\conf\httpd.conf
```

---

### ⚠️ Permisos insuficientes en `/storage`

La aplicación necesita escribir en esta carpeta.

**Solución (en PowerShell como admin):**
```powershell
$path = "C:\Users\IARC\Desktop\Proyecto Ingreso ticket\storage"
$acl = Get-Acl $path
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule(
    "Users", "Modify", "ContainerInherit,ObjectInherit", "None", "Allow"
)
$acl.SetAccessRule($rule)
Set-Acl -Path $path -AclObject $acl
```

---

## 📝 Crear tarea automatizada de Windows

Para que se ejecute automáticamente **después de cada reinicio**:

### Opción 1: Tarea programada (recomendada)

```powershell
# En PowerShell como admin:

$action = New-ScheduledTaskAction `
    -Execute "powershell.exe" `
    -Argument "-NoProfile -WindowStyle Hidden -File `
              'C:\Users\IARC\Desktop\Proyecto Ingreso ticket\startup.ps1' -SkipBrowser"

$trigger = New-ScheduledTaskTrigger -AtStartup

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest

Register-ScheduledTask `
    -TaskName "Levanta App Ingreso Requerimientos" `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Force
```

Verifica que se haya creado:
```powershell
Get-ScheduledTask | grep "Ingreso"
```

### Opción 2: Atajo en Startup

1. Presiona `Win + R` y escribe: `shell:startup`
2. Crea un shortcut hacia `startup.bat`
3. Se ejecutará al iniciar la sesión (después del login)

---

## 🎓 Notas técnicas

- Los scripts crean un archivo de log en: `startup.log` (en la carpeta del proyecto)
- Apache se levanta como servicio de Windows (requiere reinicios manuales si falla)
- Las dependencias se cachean en `/vendor` (solo se reinstalan si faltan)
- La sincronización de BD se ejecuta siempre (necesaria para datos)

---

## 📞 Ayuda adicional

Si tienes problemas:

1. **Revisa el log:** `cat .\startup.log` en PowerShell
2. **Verifica Apache:** Abre XAMPP Control Panel
3. **Revisa configuración:** `C:\xampp\apache\conf\httpd.conf`
4. **Reinstala dependencias:**
   ```powershell
   composer install --no-cache
   ```

---

¡Listo! La aplicación debería levantar correctamente con estos scripts. 🚀
