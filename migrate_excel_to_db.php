#!/usr/bin/env php
<?php
/**
 * migrate_excel_to_db.php — Script standalone CLI
 * Ejecutar: php migrate_excel_to_db.php
 * 
 * 1. Lee TODOS los datos del Excel
 * 2. Limpia la BD completamente
 * 3. Inserta TODOS los registros
 * 4. Sincroniza a Excel (backup)
 */

// Configuración
define('APP_ROOT', __DIR__);
require APP_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Requerimiento\ExcelGraphAdapter;
use Requerimiento\LocalDbAdapter;

// Cargar .env
$dotenv = Dotenv::createImmutable(APP_ROOT);
$dotenv->safeLoad();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       Migración Excel → BD SQLite (Standalone)             ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";

try {
    // ── PASO 1: Conectar a Excel ──────────────────────────────
    echo "\n📄 PASO 1: Conectando a Excel...\n";
    $excel = new ExcelGraphAdapter();
    $worksheetName = getenv('WORKSHEET_NAME') ?: 'Requerimientos';
    
    // Verificar token
    if (!$excel->hasStoredToken()) {
        throw new Exception("❌ No hay token. Ejecuta primero: https://ip:8443/auth.php\n");
    }
    echo "✓ Token válido\n";
    
    // Leer Excel
    echo "📖 Leyendo Excel (hoja: $worksheetName)...\n";
    $allRows = $excel->getAllRowsOrFail($worksheetName);
    echo "✓ " . count($allRows) . " filas leídas del Excel\n";
    
    if (count($allRows) < 3) {
        throw new Exception("❌ El Excel tiene menos de 3 filas (vacío?)\n");
    }
    
    // ── PASO 2: Limpiar BD ─────────────────────────────────────
    echo "\n🗑️  PASO 2: Limpiando BD local...\n";
    
    $dbPath = APP_ROOT . '/storage/requerimientos.db';
    if (file_exists($dbPath)) {
        unlink($dbPath);
        echo "✓ BD anterior eliminada\n";
    }
    
    // Nueva instancia (crea schema automáticamente)
    $db = new LocalDbAdapter();
    echo "✓ BD nueva creada\n";
    
    // ── PASO 3: Insertar registros ────────────────────────────
    echo "\n📥 PASO 3: Insertando registros...\n";
    
    // Usar syncFromExcel que ya maneja la conversión de fechas y trimming
    $db->syncFromExcel($allRows);
    $inserted = $db->countRequerimientos();
    
    // ── PASO 4: Sincronizar a Excel ───────────────────────────
    echo "\n☁️  PASO 4: Sincronizando $inserted registros a Excel (backup)...\n";
    
    $allReqs = $db->getAllRequerimientos();
    $synced = 0;
    
    foreach ($allReqs as $req) {
        try {
            $excel->writeRowFromDb($worksheetName, $req['excel_row'], $req);
            $synced++;
            
            if ($synced % 50 === 0) {
                echo ".";
            }
        } catch (Exception $e) {
            error_log("Error sync fila {$req['excel_row']}: " . $e->getMessage());
        }
    }
    
    echo "\n✓ $synced registros sincronizados a Excel\n";
    
    // ── Resumen final ──────────────────────────────────────────
    echo "\n";
    echo "╔════════════════════════════════════════════════════════════╗\n";
    echo "║                   ✅ MIGRACIÓN COMPLETA                     ║\n";
    echo "╠════════════════════════════════════════════════════════════╣\n";
    echo "║  📊 BD SQLite: $inserted registros                           ║\n";
    echo "║  ☁️  Excel: $synced registros                                ║\n";
    echo "║                                                            ║\n";
    echo "║  Próximos pasos:                                           ║\n";
    echo "║  1. Los nuevos registros se clonan automáticamente a Excel ║\n";
    echo "║  2. El cron cada 5 min asegura la sincronización          ║\n";
    echo "║  3. Ya no necesitas editar Excel directamente             ║\n";
    echo "╚════════════════════════════════════════════════════════════╝\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    exit(1);
}

exit(0);
