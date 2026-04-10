<?php
/**
 * sync.php — Sincronizador background BD → Excel OneDrive
 * Se ejecuta de forma silenciosa (no devuelve HTML, solo JSON para AJAX)
 * 
 * Llamar con: GET /sync.php?action=sync
 * O desde el servidor: php sync.php
 */
set_time_limit(300); // 5 minutos de timeout para operaciones con Graph API
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;
use Requerimiento\ExcelGraphAdapter;

// Cabeceras para AJAX
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$action = $_GET['action'] ?? 'sync';

try {
    $db = new ExcelGraphAdapter();
    $local = new LocalDbAdapter();
    $worksheetName = getenv('WORKSHEET_NAME') ?: 'Pasos a Producción';
    
    switch ($action) {
        case 'sync':
            // Sincronizar registros pendientes desde BD → Excel
            $pending = $local->getUnsyncedRequerimientos();
            $synced = 0;
            
            foreach ($pending as $req) {
                try {
                    $db->writeRowFromDb($worksheetName, $req['excel_row'], $req);
                    $local->markAsSynced($req['excel_row']);
                    $synced++;
                } catch (\Exception $e) {
                    // Log silencioso, continuar con los siguientes
                    error_log("Sync error para fila {$req['excel_row']}: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'synced_count' => $synced]);
            break;
            
        case 'status':
            // Estado actual de la sincronización
            $total = $local->countRequerimientos();
            $pending = count($local->getUnsyncedRequerimientos());
            
            echo json_encode([
                'success'  => true,
                'total'    => $total,
                'pending'  => $pending,
                'synced'   => $total - $pending
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
