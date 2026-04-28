<?php
/**
 * import_csv_recovery.php — Recuperar registros desde CSV de respaldo
 * Usar UNA SOLA VEZ para restaurar la BD tras pérdida de datos.
 * Subir el archivo CSV/TSV exportado y se importa a la BD.
 * ELIMINAR este archivo del servidor después de usar.
 */
require_once __DIR__ . '/session_init.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

// Solo admins pueden usar este script
$adminsFile = __DIR__ . '/../storage/admins.json';
$admins = file_exists($adminsFile) ? json_decode(file_get_contents($adminsFile), true) : [];
$userEmail = strtolower($_SESSION['user']['email'] ?? '');
$isAdmin = false;
foreach ($admins as $admin) {
    $adminEmail = strtolower($admin['email'] ?? (is_string($admin) ? $admin : ''));
    if ($userEmail && $userEmail === $adminEmail) {
        $isAdmin = true;
        break;
    }
}
if (!$isAdmin) {
    die('<h2>Acceso denegado. Solo administradores pueden usar este script.</h2><p>Usuario: ' . htmlspecialchars($userEmail) . '</p>');
}

$error     = '';
$success   = false;
$imported  = 0;
$updated   = 0;
$skipped   = 0;
$details   = [];

// Mapeo de columnas del CSV a columnas de la BD
// Orden esperado del CSV exportado:
// 0:Turno 1:Fecha 2:Requerimiento 3:Solicitante 4:Negocio 5:Ambiente
// 6:Capa 7:Servidor 8:Estado 9:Tipo Solicitud 10:Ticket 11:Tipo Pase
// 12:IC 13:Cantidad 14:Tiempo Total 15:Tiempo unidad 16:Observación 17:ID 18:Registro
define('COL_TURNO',         0);
define('COL_FECHA',         1);
define('COL_REQUERIMIENTO', 2);
define('COL_SOLICITANTE',   3);
define('COL_NEGOCIO',       4);
define('COL_AMBIENTE',      5);
define('COL_CAPA',          6);
define('COL_SERVIDOR',      7);
define('COL_ESTADO',        8);
define('COL_TIPO_SOL',      9);
define('COL_TICKET',        10);
define('COL_TIPO_PASE',     11);
define('COL_IC',            12);
define('COL_CANTIDAD',      13);
define('COL_TIEMPO_TOTAL',  14);
define('COL_TIEMPO_UNIDAD', 15);
define('COL_OBSERVACION',   16);
define('COL_ID',            17);
define('COL_REGISTRO',      18);

$limpiarBD = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $limpiarBD = !empty($_POST['limpiar_bd']);
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error al subir el archivo. Código: ' . $file['error'];
    } else {
        $tmpPath = $file['tmp_name'];
        $content = file_get_contents($tmpPath);

        // Eliminar BOM UTF-8 si existe
        $content = ltrim($content, "\xEF\xBB\xBF");

        $lines = preg_split('/\r\n|\r|\n/', $content);
        $lines = array_filter($lines, fn($l) => trim($l) !== '');
        $lines = array_values($lines);

        if (empty($lines)) {
            $error = 'El archivo está vacío.';
        } else {
            // Detectar separador (tab o coma o punto y coma)
            $firstLine = $lines[0];
            if (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                $sep = "\t";
            } elseif (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                $sep = ';';
            } else {
                $sep = ',';
            }

            // Detectar filas de título/encabezado a saltear (puede haber título + encabezado)
            $startRow = 0;
            $headerKeywords = ['turno', 'fecha', 'requerimiento', 'solicitante', 'negocio', 'id'];
            foreach (array_slice($lines, 0, 5) as $idx => $line) {
                $cols = str_getcsv($line, $sep);
                $lower = array_map('strtolower', array_map('trim', $cols));
                $matchCount = count(array_intersect($lower, $headerKeywords));
                if ($matchCount >= 3) {
                    $startRow = $idx + 1; // Saltar hasta después del encabezado
                    break;
                }
            }

            try {
                $dbPath = __DIR__ . '/../storage/requerimientos.db';
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->exec('PRAGMA journal_mode=WAL');

                // Limpiar todos los registros si se solicitó
                if ($limpiarBD) {
                    $pdo->exec('DELETE FROM requerimientos');
                    $pdo->exec('DELETE FROM sqlite_sequence WHERE name="requerimientos"');
                }

                // Asegurar que la tabla existe con todas las columnas
                $pdo->exec("CREATE TABLE IF NOT EXISTS requerimientos (
                    id               INTEGER PRIMARY KEY AUTOINCREMENT,
                    excel_row        INTEGER,
                    turno            TEXT,
                    fecha            TEXT,
                    requerimiento    TEXT,
                    solicitante      TEXT,
                    negocio          TEXT,
                    ambiente         TEXT,
                    capa             TEXT,
                    servidor         TEXT,
                    estado           TEXT,
                    tipo_solicitud   TEXT,
                    numero_ticket    TEXT,
                    tipo_pase        TEXT,
                    ic               TEXT,
                    cantidad         INTEGER,
                    tiempo_total     REAL,
                    tiempo_unidad    TEXT,
                    observaciones    TEXT,
                    registro         TEXT,
                    synced_to_excel  INTEGER DEFAULT 0,
                    last_updated     TEXT
                )");

                // Preparar: INSERT o UPDATE según si el excel_row ya existe
                $stmtCheck = $pdo->prepare("SELECT id FROM requerimientos WHERE excel_row = :excel_row");
                $stmtInsert = $pdo->prepare("
                    INSERT INTO requerimientos
                        (excel_row, turno, fecha, requerimiento, solicitante, negocio,
                         ambiente, capa, servidor, estado, tipo_solicitud, numero_ticket,
                         tipo_pase, ic, cantidad, tiempo_total, tiempo_unidad, observaciones,
                         registro, synced_to_excel, last_updated)
                    VALUES
                        (:excel_row, :turno, :fecha, :requerimiento, :solicitante, :negocio,
                         :ambiente, :capa, :servidor, :estado, :tipo_solicitud, :numero_ticket,
                         :tipo_pase, :ic, :cantidad, :tiempo_total, :tiempo_unidad, :observaciones,
                         :registro, 0, datetime('now'))
                ");
                $stmtUpdate = $pdo->prepare("
                    UPDATE requerimientos SET
                        turno          = :turno,
                        fecha          = :fecha,
                        requerimiento  = :requerimiento,
                        solicitante    = :solicitante,
                        negocio        = :negocio,
                        ambiente       = :ambiente,
                        capa           = :capa,
                        servidor       = :servidor,
                        estado         = :estado,
                        tipo_solicitud = :tipo_solicitud,
                        numero_ticket  = :numero_ticket,
                        tipo_pase      = :tipo_pase,
                        ic             = :ic,
                        cantidad       = :cantidad,
                        tiempo_total   = :tiempo_total,
                        tiempo_unidad  = :tiempo_unidad,
                        observaciones  = :observaciones,
                        registro       = :registro,
                        last_updated   = datetime('now')
                    WHERE excel_row = :excel_row
                ");

                $pdo->beginTransaction();
                $rowNum = $startRow;

                foreach (array_slice($lines, $startRow) as $lineIdx => $line) {
                    $rowNum++;
                    $cols = str_getcsv($line, $sep);

                    // Asegurar mínimo de columnas
                    while (count($cols) < 19) {
                        $cols[] = '';
                    }

                    $colId = isset($cols[COL_ID]) ? intval(trim($cols[COL_ID])) : 0;
                    if ($colId <= 0) {
                        $skipped++;
                        $details[] = "Fila $rowNum: ID inválido («{$cols[COL_ID]}»), omitida.";
                        continue;
                    }

                    $cantidadRaw = trim($cols[COL_CANTIDAD]);
                    $cantidad = is_numeric($cantidadRaw) ? intval($cantidadRaw) : null;

                    $tiempoRaw = trim($cols[COL_TIEMPO_TOTAL]);
                    $tiempoRaw = str_replace(',', '.', $tiempoRaw);
                    $tiempo = is_numeric($tiempoRaw) ? floatval($tiempoRaw) : null;

                    $params = [
                        ':excel_row'      => $colId,
                        ':turno'          => trim($cols[COL_TURNO]),
                        ':fecha'          => trim($cols[COL_FECHA]),
                        ':requerimiento'  => trim($cols[COL_REQUERIMIENTO]),
                        ':solicitante'    => trim($cols[COL_SOLICITANTE]),
                        ':negocio'        => trim($cols[COL_NEGOCIO]),
                        ':ambiente'       => trim($cols[COL_AMBIENTE]),
                        ':capa'           => trim($cols[COL_CAPA]),
                        ':servidor'       => trim($cols[COL_SERVIDOR]),
                        ':estado'         => trim($cols[COL_ESTADO]),
                        ':tipo_solicitud' => trim($cols[COL_TIPO_SOL]),
                        ':numero_ticket'  => trim($cols[COL_TICKET]),
                        ':tipo_pase'      => trim($cols[COL_TIPO_PASE]),
                        ':ic'             => trim($cols[COL_IC]),
                        ':cantidad'       => $cantidad,
                        ':tiempo_total'   => $tiempo,
                        ':tiempo_unidad'  => trim($cols[COL_TIEMPO_UNIDAD]),
                        ':observaciones'  => trim($cols[COL_OBSERVACION]),
                        ':registro'       => trim($cols[COL_REGISTRO]),
                    ];

                    $stmtCheck->execute([':excel_row' => $colId]);
                    $existing = $stmtCheck->fetchColumn();

                    if ($existing) {
                        $stmtUpdate->execute($params);
                        $updated++;
                    } else {
                        $stmtInsert->execute($params);
                        $imported++;
                    }
                }

                $pdo->commit();

                $total = $pdo->query("SELECT COUNT(*) FROM requerimientos")->fetchColumn();
                $success = true;

            } catch (Exception $e) {
                if (isset($pdo) && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Error en BD: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperar BD desde CSV</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen py-8">
<div class="max-w-2xl mx-auto px-4">
    <div class="bg-white rounded-xl shadow-lg p-8">
        <h1 class="text-2xl font-bold text-red-700 mb-1">🚨 Recuperar BD desde CSV</h1>
        <p class="text-gray-500 text-sm mb-6">Solo para recuperación de emergencia. <strong>Elimina este archivo del servidor después de usar.</strong></p>

        <?php if ($success): ?>
        <div class="bg-green-50 border border-green-300 rounded-lg p-5 mb-6">
            <h2 class="text-green-800 font-bold text-lg mb-2">✅ Importación completada</h2>
            <p class="text-green-700">Nuevos insertados: <strong><?= $imported ?></strong></p>
            <p class="text-green-700">Existentes actualizados: <strong><?= $updated ?></strong></p>
            <p class="text-green-700">Filas omitidas (sin ID): <strong><?= $skipped ?></strong></p>
            <p class="text-green-700 mt-1">Total en BD ahora: <strong><?= $total ?></strong></p>
            <a href="requerimientos.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700">
                → Ver requerimientos
            </a>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-6">
            <p class="text-red-700 font-medium">❌ <?= htmlspecialchars($error) ?></p>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" enctype="multipart/form-data" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Archivo CSV/TSV de respaldo
                </label>
                <input type="file" name="csv_file" accept=".csv,.tsv,.txt"
                       class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg px-3 py-2 bg-white"
                       required>
                <p class="text-xs text-gray-400 mt-1">
                    Separador detectado automáticamente (tab, coma o punto y coma).
                    El encabezado se salta si existe.
                    Columnas esperadas: Turno, Fecha, Requerimiento, Solicitante, Negocio, Ambiente, Capa, Servidor, Estado, Tipo Solicitud, Ticket, Tipo Pase, IC, Cantidad, Tiempo Total, Tiempo unidad, Observación, <strong>ID</strong>, Registro.
                </p>
            </div>

            <div class="bg-red-50 border border-red-300 rounded-lg p-3">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="limpiar_bd" value="1" id="chkLimpiar" class="w-4 h-4 accent-red-600">
                    <span class="text-sm font-semibold text-red-700">⚠️ Borrar TODA la BD antes de importar (recomendado para reimportación limpia)</span>
                </label>
                <p class="text-xs text-red-500 mt-1 ml-6">Si no marcas esta opción, los registros existentes con el mismo ID se actualizarán y los nuevos se insertarán.</p>
            </div>

            <button type="submit"
                    class="w-full bg-red-600 text-white py-3 rounded-lg font-bold hover:bg-red-700 transition">
                🔄 Importar a la BD
            </button>
        </form>
        <?php endif; ?>

        <?php if (!empty($details)): ?>
        <details class="mt-4">
            <summary class="text-sm text-gray-500 cursor-pointer">Ver filas omitidas (<?= count($details) ?>)</summary>
            <ul class="mt-2 text-xs text-gray-500 list-disc pl-5 space-y-1">
                <?php foreach ($details as $d): ?>
                <li><?= htmlspecialchars($d) ?></li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>

        <div class="mt-6 pt-4 border-t border-gray-100">
            <a href="requerimientos.php" class="text-blue-600 text-sm hover:underline">← Volver a requerimientos</a>
        </div>
    </div>
</div>
</body>
</html>
