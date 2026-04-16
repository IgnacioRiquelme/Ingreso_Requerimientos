#!/bin/bash
##############################################################################
# SCRIPT DE CONFIGURACIÓN DE .env
# Configura las variables de Azure y SharePoint de forma interactiva
##############################################################################

PROJECT_DIR="${1:-.}"
ENV_FILE="$PROJECT_DIR/.env"

echo "════════════════════════════════════════════════════════"
echo "  CONFIGURADOR DE .env"
echo "════════════════════════════════════════════════════════"
echo ""

# Si el archivo ya existe, hacer backup
if [ -f "$ENV_FILE" ]; then
    echo "⚠️  El archivo $ENV_FILE ya existe."
    read -p "¿Deseas hacer un backup? (S/n) " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Ss]$ ]] || [[ -z $REPLY ]]; then
        cp "$ENV_FILE" "$ENV_FILE.backup.$(date +%s)"
        echo "✓ Backup creado"
    fi
fi

echo ""
echo "Ingresa los datos de tu aplicación Azure:"
echo ""

read -p "1. AZURE_TENANT_ID: " TENANT_ID
read -p "2. AZURE_CLIENT_ID: " CLIENT_ID
read -sp "3. AZURE_CLIENT_SECRET (no se mostrará): " CLIENT_SECRET
echo ""
read -p "4. GRAPH_REDIRECT_URI (ej: http://localhost/callback.php): " REDIRECT_URI
read -p "5. ONEDRIVE_FILE_URL (ej: https://cliptecnologia-my.sharepoint.com/personal/...): " ONEDRIVE_URL
read -p "6. EXCEL_FILENAME (ej: \"Requerimientos.xlsx\"): " EXCEL_FILE
read -p "7. WORKSHEET_NAME (ej: Requerimientos): " WORKSHEET

# Crear archivo .env
cat > "$ENV_FILE" <<EOF
# Azure Configuration
AZURE_TENANT_ID="$TENANT_ID"
AZURE_CLIENT_ID="$CLIENT_ID"
AZURE_CLIENT_SECRET="$CLIENT_SECRET"

# OAuth Configuration
GRAPH_REDIRECT_URI="$REDIRECT_URI"

# Excel/SharePoint Configuration
ONEDRIVE_FILE_URL="$ONEDRIVE_URL"
EXCEL_FILENAME="$EXCEL_FILE"
WORKSHEET_NAME="$WORKSHEET"

# Local Database
DATABASE_PATH="storage/requerimientos.db"

# Application Mode
APP_ENV="production"
EOF

echo ""
echo "════════════════════════════════════════════════════════"
echo "✓ Archivo .env creado correctamente"
echo "════════════════════════════════════════════════════════"
echo ""
echo "Ubicación: $ENV_FILE"
echo ""
echo "Contenido:"
cat "$ENV_FILE"
echo ""
echo "⚠️  Asegúrate de:"
echo "   - Mantener SECRET_KEY protegido"
echo "   - No publicar en GitHub"
echo "   - Proteger con permisos de archivo (600)"
echo ""

# Proteger archivo
chmod 600 "$ENV_FILE" 2>/dev/null || true
echo "✓ Permisos del archivo: 600 (solo lectura)"
