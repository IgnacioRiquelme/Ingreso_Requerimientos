<?php
/**
 * sync_excel_to_db.php — DESHABILITADO
 * La BD SQLite es la fuente de verdad única. No se importa desde Excel.
 */
require_once __DIR__ . '/session_init.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
header('Location: requerimientos.php?msg=sync_disabled');
exit;


if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;
$count   = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $excel = new ExcelGraphAdapter();
        $db    = new LocalDbAdapter();
        $worksheetName = getenv('WORKSHEET_NAME') ?: 'Pasos a Producción';
        
        // Leer todos los datos del Excel
        $allRows = $excel->getAllRows($worksheetName);
        
        // Sincronizar a BD
        $db->syncFromExcel($allRows);
        
        // Contar cuántos se importaron
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
    <title>Importar Excel a BD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-xl font-bold text-gray-900 mb-2">Importar datos históricos</h1>
    <p class="text-gray-500 text-sm mb-6">Copia los requerimientos del Excel a la base de datos local.
    <strong>Ejecutar una sola vez.</strong></p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            ✅ <strong><?= $count ?> requerimientos importados</strong> a la base de datos.
            <br><a href="requerimientos.php" class="underline font-semibold">Ir al sistema</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="bg-blue-50 border border-blue-300 rounded p-4 mb-6 text-sm text-blue-800">
        <strong>Esto hará:</strong><br>
        ✓ Leer todos los datos del Excel<br>
        ✓ Guardarlos en la BD local<br>
        ✓ La interfaz será instantánea desde ahora
    </div>
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                Importar ahora
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
