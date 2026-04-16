#!/bin/bash
##############################################################################
# VERIFICACIÓN POST-INSTALACIÓN
# Verifica que todo esté funcionando correctamente
##############################################################################

GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROJECT_DIR="/var/www/ingreso-requerimientos"

echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}  VERIFICACIÓN POST-INSTALACIÓN${NC}"
echo -e "${BLUE}════════════════════════════════════════════════════════${NC}\n"

# 1. Verificar PHP
echo -e "${YELLOW}[1/7] Verificando PHP...${NC}"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n1)
    echo -e "${GREEN}✓ PHP instalado: $PHP_VERSION${NC}"
else
    echo -e "${RED}✗ PHP no encontrado${NC}"
fi
echo ""

# 2. Verificar Apache
echo -e "${YELLOW}[2/7] Verificando Apache2...${NC}"
if systemctl is-active --quiet apache2; then
    echo -e "${GREEN}✓ Apache2 está corriendo${NC}"
else
    echo -e "${RED}✗ Apache2 no está corriendo${NC}"
    echo -e "  Intenta: ${YELLOW}sudo systemctl start apache2${NC}"
fi
echo ""

# 3. Verificar PHP-FPM
echo -e "${YELLOW}[3/7] Verificando PHP-FPM...${NC}"
if systemctl is-active --quiet php8.3-fpm; then
    echo -e "${GREEN}✓ PHP-FPM está corriendo${NC}"
else
    echo -e "${RED}✗ PHP-FPM no está corriendo${NC}"
    echo -e "  Intenta: ${YELLOW}sudo systemctl start php8.3-fpm${NC}"
fi
echo ""

# 4. Verificar directorio del proyecto
echo -e "${YELLOW}[4/7] Verificando directorio del proyecto...${NC}"
if [ -d "$PROJECT_DIR" ]; then
    echo -e "${GREEN}✓ Directorio encontrado: $PROJECT_DIR${NC}"
    FILE_COUNT=$(find $PROJECT_DIR -type f | wc -l)
    echo -e "  Archivos: $FILE_COUNT"
else
    echo -e "${RED}✗ Directorio no encontrado: $PROJECT_DIR${NC}"
fi
echo ""

# 5. Verificar .env
echo -e "${YELLOW}[5/7] Verificando configuración .env...${NC}"
if [ -f "$PROJECT_DIR/.env" ]; then
    echo -e "${GREEN}✓ Archivo .env existe${NC}"
    if grep -q "AZURE_TENANT_ID" "$PROJECT_DIR/.env"; then
        echo -e "${GREEN}✓ Variables de Azure configuradas${NC}"
    else
        echo -e "${YELLOW}⚠ Variables de Azure NO configuradas${NC}"
    fi
else
    echo -e "${RED}✗ Archivo .env no existe${NC}"
    echo -e "  Crea uno con: ${YELLOW}sudo bash $PROJECT_DIR/configure-env.sh $PROJECT_DIR${NC}"
fi
echo ""

# 6. Verificar permisos de storage
echo -e "${YELLOW}[6/7] Verificando permisos de almacenamiento...${NC}"
if [ -d "$PROJECT_DIR/storage" ]; then
    PERMS=$(stat -c %a "$PROJECT_DIR/storage" 2>/dev/null || stat -f %OLp "$PROJECT_DIR/storage")
    echo -e "${GREEN}✓ Directorio storage accesible (permisos: $PERMS)${NC}"
    
    if [ -f "$PROJECT_DIR/storage/requerimientos.db" ]; then
        DB_SIZE=$(du -h "$PROJECT_DIR/storage/requerimientos.db" | cut -f1)
        echo -e "${GREEN}✓ Base de datos: $DB_SIZE${NC}"
    else
        echo -e "${YELLOW}⚠ Base de datos no encontrada (se creará automáticamente)${NC}"
    fi
else
    echo -e "${RED}✗ Directorio storage no accesible${NC}"
fi
echo ""

# 7. Verificar módulos Apache
echo -e "${YELLOW}[7/7] Verificando módulos Apache...${NC}"
MODULES_OK=true
for MOD in php8.3 rewrite headers; do
    if apache2ctl -M 2>/dev/null | grep -q "$MOD"; then
        echo -e "${GREEN}✓ Módulo $MOD habilitado${NC}"
    else
        echo -e "${RED}✗ Módulo $MOD NO habilitado${NC}"
        MODULES_OK=false
    fi
done
echo ""

# Resumen
if [ "$MODULES_OK" = true ]; then
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo -e "${GREEN}✓ VERIFICACIÓN COMPLETADA - Todo parece estar OK${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "${BLUE}Accede a la aplicación:${NC}"
    curl -s http://localhost/ | grep -q "title" && echo -e "${GREEN}✓ Sitio accesible en http://localhost/${NC}" || echo -e "${YELLOW}⚠ Verifica que Apache esté respondiendo${NC}"
else
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo -e "${YELLOW}⚠ VERIFICACIÓN COMPLETADA - Hay problemas${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════════${NC}"
    echo ""
    echo -e "${YELLOW}Próximos pasos:${NC}"
    echo "  1. Revisa los logs de Apache: sudo tail -f /var/log/apache2/ingreso_error.log"
    echo "  2. Revisa los logs de PHP: sudo tail -f /var/log/php8.3-fpm.log"
fi
echo ""
