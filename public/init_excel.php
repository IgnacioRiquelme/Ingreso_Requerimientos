<?php
/**
 * init_excel.php — Inicialización de encabezados del Excel (ejecutar UNA SOLA VEZ)
 * Escribe el título en fila 1 y los encabezados de columna en fila 3.
 * Los datos existentes en fila 4+ NO se modifican.
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';
use Requerimiento\ExcelGraphAdapter;

// Solo el usuario "ignacio" puede ejecutar esto
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $adapter       = new ExcelGraphAdapter();
        $worksheetName = getenv('WORKSHEET_NAME') ?: 'Pasos a Producción';
        $adapter->initializeHeaders($worksheetName);
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
    <title>Inicializar Excel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-xl font-bold text-gray-900 mb-2">Inicializar encabezados del Excel</h1>
    <p class="text-gray-500 text-sm mb-6">Escribe el título en fila 1 y los encabezados en fila 3.<br>
    <strong>Los datos existentes (fila 4+) no se tocan.</strong></p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            ✅ Encabezados escritos correctamente. <a href="requerimientos.php" class="underline font-semibold">Ir al sistema</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="bg-yellow-50 border border-yellow-300 rounded p-4 mb-6 text-sm text-yellow-800">
        <strong>Se escribirá en el Excel:</strong><br>
        <code>Fila 1 → "Pasos a Producción"</code><br>
        <code>Fila 2 → (vacía)</code><br>
        <code>Fila 3 → Turno | Fecha | Requerimiento | Solicitante | Negocio | Ambiente | Capa | Servidor | Estado | Tipo de Solicitud | Ticket | Tipo de Pase a Prod y QA | IC | Cantidad | Tiempo Total | Tiempo Unidad | Observaciones | ID | Registro</code>
    </div>
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                Escribir encabezados
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
