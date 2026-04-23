<?php
/**
 * export_all_to_excel.php — Exportar TODA la BD a Excel OneDrive de una sola operación
 * Limpiar Excel, escribir todos los registros de la BD, y listo.
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;
$synced  = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $db = new \Requerimiento\LocalDbAdapter();
        $excel = new \Requerimiento\ExcelGraphAdapter();
        $worksheetName = getenv('WORKSHEET_NAME') ?: 'Requerimientos';
        
        // Obtener todos los requerimientos de la BD
        $allReqs = $db->getAllRequerimientos();
        
        if (empty($allReqs)) {
            throw new \Exception('No hay requerimientos en la BD local para exportar.');
        }
        
        // Escribir cada registro a Excel
        foreach ($allReqs as $req) {
            try {
                $excel->writeRowFromDb($worksheetName, $req['excel_row'], $req);
                $synced++;
            } catch (\Exception $e) {
                error_log("Error escribiendo fila {$req['excel_row']}: " . $e->getMessage());
            }
        }
        
        $success = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exportar BD a Excel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">📤 Exportar BD → Excel</h1>
    <p class="text-gray-600 text-sm mb-6">Escribe todos los requerimientos de la BD local al Excel en OneDrive.</p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            ✅ <strong>Exportación completada</strong><br>
            <strong><?= $synced ?> requerimientos</strong> sincronizados a Excel.<br>
            <a href="requerimientos.php" class="underline font-semibold mt-2 inline-block">Ir al sistema</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="bg-blue-50 border border-blue-300 rounded p-4 mb-6 text-sm text-blue-800">
        <strong>ℹ️ Flujo:</strong><br>
        ✓ Lee todos los requerimientos de la BD SQLite<br>
        ✓ Los escribe al Excel en OneDrive<br>
        ✓ De ahora en adelante, usa el sync automático (BD → Excel)<br>
    </div>
    
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <div class="flex gap-3">
            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                📤 Exportar Ahora
            </button>
            <a href="requerimientos.php" class="flex-1 bg-gray-300 hover:bg-gray-400 text-gray-900 font-bold py-3 rounded-lg transition text-center">
                Cancelar
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
