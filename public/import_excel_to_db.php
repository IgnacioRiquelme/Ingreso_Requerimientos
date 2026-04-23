<?php
/**
 * import_excel_to_db.php — Importar TODOS los datos del Excel a la BD SQLite
 * Uso UNA SOLA VEZ para alimentar la BD desde cero
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error     = '';
$success   = false;
$imported  = 0;
$skipped   = 0;
$debug_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $db = new \Requerimiento\LocalDbAdapter();
        $excel = new \Requerimiento\ExcelGraphAdapter();
        $worksheetName = getenv('WORKSHEET_NAME') ?: 'Requerimientos';
        
        $debug_msg .= "📄 Hoja: $worksheetName\n";
        
        // Obtener todos los datos del Excel (lanza excepción si falla)
        $allRows = $excel->getAllRowsOrFail($worksheetName);
        $debug_msg .= "✓ Filas leídas del Excel: " . count($allRows) . "\n";
        
        if (empty($allRows)) {
            throw new \Exception("El Excel no devolvió filas. Verifica: 1) El nombre de la hoja en .env (WORKSHEET_NAME), 2) Que hayas autenticado en /auth.php");
        }
        
        // Sincronizar: cada fila del Excel se inserta o actualiza en la BD
        $db->syncFromExcel($allRows);
        $imported = $db->countRequerimientos();
        
        $debug_msg .= "✓ Registros ahora en BD: $imported\n";
        $success = true;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("import_excel_to_db ERROR: " . $error);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Excel → BD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
<div class="max-w-3xl mx-auto px-4">
    <div class="bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">📥 Importar Excel → BD SQLite</h1>
        <p class="text-gray-600 text-sm mb-6">Trae TODOS los 263 requerimientos del Excel a la BD local (una sola vez).</p>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-6">
                ✅ <strong>Importación exitosa</strong><br>
                <pre class="text-sm mt-2 bg-white p-2 rounded"><?= htmlspecialchars($debug_msg) ?></pre>
                <div class="mt-4 flex gap-3">
                    <a href="requerimientos.php" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded">
                        📊 Ver Requerimientos
                    </a>
                    <a href="export_all_to_excel.php" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        📤 Sincronizar a Excel
                    </a>
                </div>
            </div>
        <?php elseif ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <strong>❌ Error de importación:</strong><br>
                <code class="text-sm bg-white p-2 rounded block mt-2"><?= htmlspecialchars($error) ?></code>
                <div class="mt-4 text-sm">
                    <strong>Posibles soluciones:</strong>
                    <ul class="list-disc list-inside mt-2">
                        <li>Verifica que hayas autenticado: <a href="auth.php" class="underline text-red-600">ir a /auth.php</a></li>
                        <li>Verifica el nombre de la hoja en <code>.env</code> (<code>WORKSHEET_NAME</code>)</li>
                        <li>Revisa los logs del servidor: <code>tail -f /var/log/apache2/ingreso-ssl-error.log</code></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <div class="bg-yellow-50 border border-yellow-300 rounded p-4 mb-6 text-sm text-yellow-800">
            <strong>⚠️ Requisitos:</strong><br>
            ✓ Debes estar autenticado con Microsoft<br>
            ✓ El archivo Excel debe estar en OneDrive en la ruta configurada<br>
            ✓ La hoja debe llamarse exactamente como en <code>.env</code> WORKSHEET_NAME<br>
            <br>
            <strong>Después de importar:</strong><br>
            ✓ La BD SQLite tendrá todos los registros<br>
            ✓ Excel será solo un backup (sincronización unidireccional BD → Excel)<br>
            ✓ Los nuevos requerimientos se sincronizan automáticamente
        </div>
        
        <form method="POST" class="space-y-4">
            <input type="hidden" name="confirmar" value="1">
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
                📥 Importar Todos los Datos del Excel
            </button>
        </form>

        <div class="mt-6 p-4 bg-gray-100 rounded">
            <strong class="text-gray-700 text-sm">ℹ️ Configuración actual:</strong>
            <pre class="text-xs text-gray-600 mt-2"><?php
                echo "WORKSHEET_NAME: " . (getenv('WORKSHEET_NAME') ?: 'Requerimientos') . "\n";
                echo "EXCEL_FILENAME: " . (getenv('EXCEL_FILENAME') ?: 'N/A') . "\n";
                echo "Token almacenado: " . (file_exists(__DIR__ . '/../storage/graph_token.json') ? '✓ Sí' : '✗ No') . "\n";
            ?></pre>
        </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
