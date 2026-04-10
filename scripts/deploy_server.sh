#!/usr/bin/env bash
set -euo pipefail

# Script de despliegue mínimo para Ubuntu (ejecutar como root o con sudo)
# Uso: sudo ./scripts/deploy_server.sh /var/www/ingreso-requerimientos ejemplo.tu-dominio.com

TARGET_DIR=${1:-/var/www/ingreso-requerimientos}
SERVER_NAME=${2:-localhost}

echo "Directorio objetivo: $TARGET_DIR"
echo "ServerName: $SERVER_NAME"

apt update
apt upgrade -y
apt install -y apache2 php php-sqlite3 php-xml php-mbstring php-zip php-curl unzip git curl

# Composer
if ! command -v composer >/dev/null 2>&1; then
  echo "Instalando Composer..."
  curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm /tmp/composer-setup.php
fi

# Clonar o actualizar repo
if [ ! -d "$TARGET_DIR" ]; then
  git clone https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git "$TARGET_DIR"
else
  cd "$TARGET_DIR"
  git pull || true
fi

cd "$TARGET_DIR"
composer install --no-dev --optimize-autoloader || true

# Crear VirtualHost
VHOST=/etc/apache2/sites-available/ingreso-requerimientos.conf
cat > "$VHOST" <<EOF
<VirtualHost *:80>
    ServerName $SERVER_NAME
    DocumentRoot $TARGET_DIR/public

    <Directory $TARGET_DIR/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \\${APACHE_LOG_DIR}/ingreso_error.log
    CustomLog \\${APACHE_LOG_DIR}/ingreso_access.log combined
</VirtualHost>
EOF

a2ensite ingreso-requerimientos
a2enmod rewrite
systemctl reload apache2

# Permisos
chown -R www-data:www-data "$TARGET_DIR"
find "$TARGET_DIR" -type d -exec chmod 755 {} \;
find "$TARGET_DIR" -type f -exec chmod 644 {} \;

echo "Despliegue básico completado. Visit http://$SERVER_NAME/"
