# Instalación en Ubuntu 24.04 LTS

Este documento describe cómo instalar el **Sistema de Ingreso de Requerimientos** en un servidor Ubuntu.

## 📋 Requisitos

- Ubuntu 24.04 LTS (o 22.04 LTS)
- Acceso con `sudo` (para instalar paquetes)
- Conexión a internet
- ~500 MB de espacio en disco

## ⚡ Instalación Rápida (Automática)

### Paso 1: Clonar y preparar

```bash
cd /tmp
git clone https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git
cd Ingreso_Requerimientos
chmod +x install-ubuntu.sh configure-env.sh verify-install.sh
```

### Paso 2: Ejecutar instalación

```bash
sudo bash install-ubuntu.sh
```

Esto instala automáticamente:
- PHP 8.3 + Apache2 + PHP-FPM
- SQLite3
- Git + Composer
- Dependencias del proyecto
- Configuración de Apache

### Paso 3: Configurar credenciales Azure

```bash
sudo bash configure-env.sh /var/www/ingreso-requerimientos
```

Se te pedirá:
- `AZURE_TENANT_ID` - ID del tenant Azure
- `AZURE_CLIENT_ID` - ID de la aplicación registrada
- `AZURE_CLIENT_SECRET` - Secret de la aplicación
- `GRAPH_REDIRECT_URI` - URL de callback (ej: `http://localhost/callback.php`)
- `ONEDRIVE_FILE_URL` - URL compartida del Excel en SharePoint
- `EXCEL_FILENAME` - Nombre del archivo Excel
- `WORKSHEET_NAME` - Nombre de la hoja

### Paso 4: Verificar instalación

```bash
sudo bash /var/www/ingreso-requerimientos/verify-install.sh
```

Debería mostrar:
- ✓ PHP instalado
- ✓ Apache2 corriendo
- ✓ PHP-FPM corriendo
- ✓ Directorio del proyecto accesible
- ✓ .env configurado
- ✓ Módulos Apache habilitados

## 🌐 Acceso a la Aplicación

Una vez completada la instalación:

1. **Por primera vez** (obtener token Azure):
   ```
   http://tu-servidor-ip/auth.php
   ```
   Serás redirigido a Microsoft para autorizar, luego volverás a la app.

2. **Ingreso de Requerimientos**:
   ```
   http://tu-servidor-ip/
   ```

3. **Admin** (listar/editar valores de combobox):
   ```
   http://tu-servidor-ip/
   ```
   Solo acceso si estás logueado como admin.

## ⚙️ Configuración Manual

Si prefieres hacer todo manualmente:

### Instalar dependencias

```bash
sudo apt update
sudo apt install -y php8.3 php8.3-fpm apache2 libapache2-mod-php8.3 \
    git curl sqlite3 composer
```

### Clonar repositorio

```bash
sudo git clone https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git \
    /var/www/ingreso-requerimientos
```

### Instalar dependencias PHP

```bash
cd /var/www/ingreso-requerimientos
sudo composer install
```

### Crear .env

```bash
sudo cp .env.example .env
sudo nano .env
```

Edita con tus credenciales Azure.

### Configurar Apache

```bash
sudo a2enmod php8.3 rewrite headers
sudo cp /var/www/ingreso-requerimientos/apache.conf \
    /etc/apache2/sites-available/ingreso-requerimientos.conf
sudo a2ensite ingreso-requerimientos.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl restart apache2
```

### Permisos

```bash
sudo chown -R www-data:www-data /var/www/ingreso-requerimientos
sudo chmod -R 755 /var/www/ingreso-requerimientos
sudo chmod -R 775 /var/www/ingreso-requerimientos/storage
```

## 🔍 Troubleshooting

### Error: "Could not open file: /var/www/ingreso-requerimientos/public/index.php"

**Solución:**
```bash
sudo chmod -R 755 /var/www/ingreso-requerimientos
```

### Error: "Permission denied" al escribir en storage

**Solución:**
```bash
sudo chown -R www-data:www-data /var/www/ingreso-requerimientos/storage
sudo chmod -R 775 /var/www/ingreso-requerimientos/storage
```

### Error: "Graph API error 400" o "Token expired"

**Solución:**
1. Vuelve a `/auth.php` para obtener nuevo token
2. Verifica que el `.env` tiene credenciales correctas:
   ```bash
   sudo cat /var/www/ingreso-requerimientos/.env | grep AZURE
   ```

### PHP-FPM no responde

**Verificar estado:**
```bash
sudo systemctl status php8.3-fpm
```

**Reiniciar:**
```bash
sudo systemctl restart php8.3-fpm
```

### Apache no inicia

**Verificar sintaxis:**
```bash
sudo apache2ctl configtest
```

**Ver error completo:**
```bash
sudo systemctl status apache2
sudo tail -f /var/log/apache2/error.log
```

## 📊 Monitoreo

### Ver logs en tiempo real

```bash
# Apache errors
sudo tail -f /var/log/apache2/ingreso_error.log

# Apache access
sudo tail -f /var/log/apache2/ingreso_access.log

# PHP-FPM errors
sudo tail -f /var/log/php8.3-fpm.log
```

### Verificar estado de servicios

```bash
sudo systemctl status apache2
sudo systemctl status php8.3-fpm
```

### Base de datos

La BD SQLite se encuentra en:
```bash
/var/www/ingreso-requerimientos/storage/requerimientos.db
```

Ver tamaño:
```bash
du -h /var/www/ingreso-requerimientos/storage/requerimientos.db
```

Hacer backup:
```bash
sudo cp /var/www/ingreso-requerimientos/storage/requerimientos.db \
    /var/www/ingreso-requerimientos/storage/requerimientos.db.backup.$(date +%s)
```

## 🔐 Seguridad

### Cambiar contraseña de admin

```bash
sudo php /var/www/ingreso-requerimientos/update_admin_password.php
```

### Proteger archivos sensibles

```bash
# .env debe tener permisos 600
sudo chmod 600 /var/www/ingreso-requerimientos/.env

# Verificar
ls -la /var/www/ingreso-requerimientos/.env
```

### HTTPS en Producción

Para usar SSL/TLS, instala Certbot:

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d tu-dominio.com
```

## 📝 Actualizar código

Para actualizar a la última versión:

```bash
cd /var/www/ingreso-requerimientos
sudo git pull origin main
sudo composer install
```

No olvides hacer backup de la BD antes:

```bash
sudo cp storage/requerimientos.db storage/requerimientos.db.backup.$(date +%Y%m%d_%H%M%S)
```

## 🆘 Soporte

Si tienes problemas:

1. Revisa los logs (arriba)
2. Ejecuta el script de verificación: `sudo bash /var/www/ingreso-requerimientos/verify-install.sh`
3. Verifica que el `.env` esté correctamente configurado
4. Abre un issue en GitHub: https://github.com/IgnacioRiquelme/Ingreso_Requerimientos/issues

---

**Última actualización:** Abril 2026
**Versión:** 1.0
**Compatibilidad:** Ubuntu 24.04 LTS
