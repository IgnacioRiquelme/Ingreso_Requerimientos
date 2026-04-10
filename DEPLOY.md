# Despliegue en servidor Ubuntu

Este archivo resume los pasos para desplegar la aplicación en una máquina Ubuntu (ej. 20.04+).

1) Preparación del servidor

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y apache2 php php-sqlite3 php-xml php-mbstring php-zip php-curl unzip git curl
```

2) Instalar Composer (si no existe)

```bash
curl -sS https://getcomposer.org/installer -o composer-setup.php
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php
```

3) Clonar el repositorio y preparar

```bash
cd /var/www
sudo git clone https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git ingreso-requerimientos
cd ingreso-requerimientos
sudo composer install --no-dev --optimize-autoloader
```

4) Configurar VirtualHost de Apache (archivo de ejemplo)

Cree `/etc/apache2/sites-available/ingreso-requerimientos.conf` con el siguiente contenido (ajuste `ServerName` y rutas si corresponde):

```
<VirtualHost *:80>
    ServerName ejemplo.tu-dominio.com
    DocumentRoot /var/www/ingreso-requerimientos/public

    <Directory /var/www/ingreso-requerimientos/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/ingreso_error.log
    CustomLog ${APACHE_LOG_DIR}/ingreso_access.log combined
</VirtualHost>
```

Habilitar y recargar Apache:

```bash
sudo a2ensite ingreso-requerimientos
sudo a2enmod rewrite
sudo systemctl reload apache2
```

5) Permisos

```bash
sudo chown -R www-data:www-data /var/www/ingreso-requerimientos
sudo find /var/www/ingreso-requerimientos -type d -exec chmod 755 {} \;
sudo find /var/www/ingreso-requerimientos -type f -exec chmod 644 {} \;
```

6) (Opcional) Cron para sincronización periódica

Ejecutar cada 15 minutos (crontab de root o www-data):

```bash
*/15 * * * * /usr/bin/php /var/www/ingreso-requerimientos/public/sync_excel_to_db.php >> /var/log/ingreso_sync.log 2>&1
```

7) Subir a GitHub

Si quieres que yo haga el `git push` desde este equipo, tendrás que proporcionar credenciales/token (no envíes credenciales por chat). Alternativamente ejecuta estos comandos localmente:

```bash
git init
git add .
git commit -m "Initial commit"
git remote add origin https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git
git branch -M main
git push -u origin main
```

Si usas token:

```bash
git remote set-url origin https://<TOKEN>@github.com/IgnacioRiquelme/Ingreso_Requerimientos.git
git push -u origin main
```


Si quieres, puedo:
- Inicializar git aquí y ayudarte a empujar (necesito que ejecutes el `git push` localmente o me proporciones un token de forma segura).
- Ejecutar el script `deploy_server.sh` directamente en la máquina Ubuntu si me das acceso (o te lo dejo listo para ejecutar).
