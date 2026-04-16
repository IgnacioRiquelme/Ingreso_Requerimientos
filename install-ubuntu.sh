#!/bin/bash
##############################################################################
# SCRIPT DE INSTALACIÓN - Sistema de Ingreso de Requerimientos en Ubuntu
# Versión: 1.0
# Compatible: Ubuntu 24.04 LTS (y versiones similares)
# Requiere: sudo access, conexión a internet
##############################################################################

set -e  # Salir si hay error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}═══════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  INSTALACIÓN - Sistema Ingreso Requerimientos    ${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════${NC}\n"

# Verificar si se ejecuta con sudo
if [[ $EUID -ne 0 ]]; then
   echo -e "${RED}✗ Este script debe ejecutarse con sudo${NC}"
   echo "Ejecuta: sudo bash install-ubuntu.sh"
   exit 1
fi

# PASO 1: Actualizar sistema
echo -e "${YELLOW}[1/8] Actualizando sistema...${NC}"
apt-get update -qq
apt-get upgrade -y -qq > /dev/null 2>&1
echo -e "${GREEN}✓ Sistema actualizado${NC}\n"

# PASO 2: Instalar PHP y dependencias
echo -e "${YELLOW}[2/8] Instalando PHP 8.3 y dependencias...${NC}"
apt-get install -y -qq php8.3 php8.3-cli php8.3-fpm php8.3-curl php8.3-json php8.3-sqlite3 > /dev/null 2>&1
echo -e "${GREEN}✓ PHP instalado: $(php -v | head -n1)${NC}\n"

# PASO 3: Instalar Apache
echo -e "${YELLOW}[3/8] Instalando Apache2...${NC}"
apt-get install -y -qq apache2 libapache2-mod-php8.3 > /dev/null 2>&1
a2enmod php8.3 > /dev/null 2>&1
a2enmod rewrite > /dev/null 2>&1
a2enmod headers > /dev/null 2>&1
echo -e "${GREEN}✓ Apache instalado y módulos habilitados${NC}\n"

# PASO 4: Instalar git y composer
echo -e "${YELLOW}[4/8] Instalando Git y Composer...${NC}"
apt-get install -y -qq git curl sqlite3 > /dev/null 2>&1

# Descargar Composer
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer > /dev/null 2>&1
echo -e "${GREEN}✓ Git, Composer y SQLite3 instalados${NC}\n"

# PASO 5: Clonar repositorio
echo -e "${YELLOW}[5/8] Clonando repositorio desde GitHub...${NC}"
PROJECT_DIR="/var/www/ingreso-requerimientos"
if [ -d "$PROJECT_DIR" ]; then
    echo -e "${YELLOW}  Directorio ya existe, actualizando...${NC}"
    cd $PROJECT_DIR
    git pull origin main -q
else
    git clone https://github.com/IgnacioRiquelme/Ingreso_Requerimientos.git $PROJECT_DIR -q
    cd $PROJECT_DIR
fi
echo -e "${GREEN}✓ Repositorio clonado en $PROJECT_DIR${NC}\n"

# PASO 6: Instalar dependencias PHP (Composer)
echo -e "${YELLOW}[6/8] Instalando dependencias PHP (Composer)...${NC}"
cd $PROJECT_DIR
composer install -q --no-dev
echo -e "${GREEN}✓ Dependencias PHP instaladas${NC}\n"

# PASO 7: Configurar .env
echo -e "${YELLOW}[7/8] Configurando archivo .env...${NC}"
if [ ! -f "$PROJECT_DIR/.env" ]; then
    echo -e "${YELLOW}  ⚠ El archivo .env no existe. Necesitas completarlo manualmente:${NC}"
    echo -e "${YELLOW}  ${PROJECT_DIR}/.env${NC}"
    echo ""
    echo -e "${YELLOW}  Variables requeridas:${NC}"
    echo "  - AZURE_TENANT_ID"
    echo "  - AZURE_CLIENT_ID"
    echo "  - AZURE_CLIENT_SECRET"
    echo "  - GRAPH_REDIRECT_URI=http://localhost:8081/callback.php"
    echo "  - ONEDRIVE_FILE_URL (opcional, para SharePoint)"
    echo ""
else
    echo -e "${GREEN}✓ Archivo .env ya existe${NC}"
fi
echo ""

# PASO 8: Configurar permisos y Apache
echo -e "${YELLOW}[8/8] Configurando permisos y Apache...${NC}"

# Permisos
chown -R www-data:www-data $PROJECT_DIR
chmod -R 755 $PROJECT_DIR
chmod -R 775 $PROJECT_DIR/storage
chmod -R 775 $PROJECT_DIR/public

# Crear VirtualHost de Apache
VHOST_FILE="/etc/apache2/sites-available/ingreso-requerimientos.conf"
if [ ! -f "$VHOST_FILE" ]; then
    cat > $VHOST_FILE <<'VHOST'
<VirtualHost *:80>
    ServerName localhost
    ServerAlias ingreso-requerimientos.local
    DocumentRoot /var/www/ingreso-requerimientos/public
    
    <Directory /var/www/ingreso-requerimientos/public>
        AllowOverride All
        Require all granted
        
        RewriteEngine On
        RewriteBase /
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^ index.php [QSA,L]
    </Directory>
    
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    ErrorLog ${APACHE_LOG_DIR}/ingreso_error.log
    CustomLog ${APACHE_LOG_DIR}/ingreso_access.log combined
</VirtualHost>
VHOST
    
    a2ensite ingreso-requerimientos.conf > /dev/null 2>&1
    a2dissite 000-default.conf > /dev/null 2>&1
    apache2ctl configtest > /dev/null 2>&1
fi

# Reiniciar servicios
systemctl restart apache2
systemctl restart php8.3-fpm
systemctl enable apache2 > /dev/null 2>&1
systemctl enable php8.3-fpm > /dev/null 2>&1

echo -e "${GREEN}✓ Permisos y Apache configurados${NC}\n"

# RESUMEN FINAL
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}"
echo -e "${GREEN}  ✓ INSTALACIÓN COMPLETADA${NC}"
echo -e "${GREEN}═══════════════════════════════════════════════════${NC}\n"

echo -e "${BLUE}📍 Información del servidor:${NC}"
echo "   Directorio: $PROJECT_DIR"
echo "   Servidor: http://localhost/ (o tu IP/hostname)"
echo "   Usuario web: www-data"
echo ""

echo -e "${BLUE}📋 Próximos pasos:${NC}"
echo "   1. Edita el archivo .env con tus credenciales de Azure:"
echo "      ${GREEN}sudo nano $PROJECT_DIR/.env${NC}"
echo ""
echo "   2. Obtén el token inicial visitando /auth.php:"
echo "      ${GREEN}http://localhost/auth.php${NC}"
echo ""
echo "   3. O directamente ingresa a:"
echo "      ${GREEN}http://localhost/${NC}"
echo ""

echo -e "${BLUE}📝 Logs:${NC}"
echo "   Error: /var/log/apache2/ingreso_error.log"
echo "   Access: /var/log/apache2/ingreso_access.log"
echo ""

echo -e "${BLUE}🔍 Verificar estado:${NC}"
echo "   ${GREEN}sudo systemctl status apache2${NC}"
echo "   ${GREEN}sudo systemctl status php8.3-fpm${NC}"
echo ""

echo -e "${YELLOW}⚠ IMPORTANTE:${NC}"
echo "   - Configura tu .env ANTES de acceder a la aplicación"
echo "   - Asegúrate de tener SSL/HTTPS en producción"
echo "   - Protege el archivo .env con contraseña de admin"
echo ""
