<?php
/**
 * migrate_csv_to_db.php — Migrar datos de CSV a BD local
 * Se ejecuta una sola vez para inicializar los combobox
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $db = new LocalDbAdapter();
        
        // Función para leer CSV
        function leerCSV($archivo) {
            if (file_exists($archivo)) {
                $lines = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                return array_map('trim', $lines);
            }
            return [];
        }
        
        $storagePath = __DIR__ . '/../storage';
        $campos = [
            'solicitante'    => leerCSV($storagePath . '/tipos_solicitante.csv'),
            'requerimiento'  => leerCSV($storagePath . '/tipos_requerimientos.csv'),
            'negocio'        => leerCSV($storagePath . '/tipos_negocios.csv'),
            'ambiente'       => leerCSV($storagePath . '/tipos_ambientes.csv'),
            'capa'           => leerCSV($storagePath . '/tipos_capa.csv'),
            'servidor'       => leerCSV($storagePath . '/tipos_servidor.csv'),
            'estado'         => leerCSV($storagePath . '/tipos_estado.csv'),
            'tipo_solicitud' => leerCSV($storagePath . '/tipos_solicitud.csv'),
            'tipo_pase'      => leerCSV($storagePath . '/tipos_pase.csv'),
            'ic'             => leerCSV($storagePath . '/tipos_ic.csv'),
        ];
        
        $total = 0;
        foreach ($campos as $field => $values) {
            $db->migrateCSVToDb($field, $values);
            $total += count(array_filter($values));
        }
        
        $success = true;
        $message = "✅ Se migró exitosamente <strong>$total valores</strong> de CSV a la BD local.";
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
    <title>Migrar CSV a BD</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-xl font-bold text-gray-900 mb-2">📊 Migrar Combobox CSV → BD</h1>
    <p class="text-gray-500 text-sm mb-6">
        Importa todos los valores de los CSV a la BD local SQLite.
        <strong>Ejecutar una sola vez.</strong>
    </p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            <?php echo $message; ?>
            <br><a href="submit.php" class="underline font-semibold">Ir a crear requerimiento</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <div class="bg-blue-50 border border-blue-300 rounded p-4 mb-6 text-sm text-blue-800">
        <strong>ℹ️ Esto hará:</strong><br>
        ✓ Leer datos de archivos CSV<br>
        ✓ Guardarlos en BD SQLite local<br>
        ✓ Los combobox serán más rápido<br>
        ✓ Se pueden agregar/eliminar dinámicamente
    </div>
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <div class="flex gap-3">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition">
                Migrar ahora
            </button>
            <a href="submit.php"
               class="bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-6 rounded-lg transition">
                Cancelar
            </a>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
