<?php
/**
 * reset_db.php — Limpiar y reimportar la BD desde Excel
 * Borrar storage/requerimientos.db completamente y recargar datos
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $dbPath = __DIR__ . '/../storage/requerimientos.db';
        
        // Borrar BD si existe
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        
        // Crear nueva instancia (crea esquema automáticamente)
        $db = new \Requerimiento\LocalDbAdapter();
        
        // Importar todos los datos del Excel
        $excel = new \Requerimiento\ExcelGraphAdapter();
        $worksheetName = getenv('WORKSHEET_NAME') ?: 'Requerimientos';
        $allRows = $excel->getAllRowsOrFail($worksheetName);
        if (empty($allRows)) {
            throw new \Exception("La hoja '$worksheetName' no devolvió filas. Verifica el nombre en .env (WORKSHEET_NAME).");
        }
        $db->syncFromExcel($allRows);
        
        $count = $db->countRequerimientos();
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
    <title>Resetear BD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-xl font-bold text-gray-900 mb-2">🔄 Resetear Base de Datos</h1>
    <p class="text-gray-500 text-sm mb-6">Borra la BD local y reimporta todos los datos del Excel.
    <strong>Ejecutar si hay fechas mal formateadas.</strong></p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            ✅ <strong>BD limpiada y recargada</strong><br>
            <strong><?= $count ?> requerimientos importados</strong> con fechas correctas.
            <br><a href="requerimientos.php" class="underline font-semibold">Ir al sistema</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="bg-yellow-50 border border-yellow-300 rounded p-4 mb-6 text-sm text-yellow-800">
        <strong>⚠️ Esto hará:</strong><br>
        ✓ Borrar la BD local completamente<br>
        ✓ Recargar datos del Excel<br>
        ✓ Convertir fechas correctamente<br>
        <br>
        <em>Los nuevos requerimientos (no sincronizados) se perderán.</em>
    </div>
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-6 rounded-lg transition">
                Resetear BD
            </button>
            <a href="requerimientos.php"
               class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded-lg transition">
                Cancelar
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
