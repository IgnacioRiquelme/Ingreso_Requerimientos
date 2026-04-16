<?php
date_default_timezone_set('America/Santiago');
session_start();
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$storagePath = __DIR__ . '/../storage';

// PASO 0: Asegurar que los combobox estén inicializados desde CSV
require_once __DIR__ . '/ensure_combobox.php';

function getTurno() {
    $hora = (int)date('H');
    if ($hora >= 8 && $hora < 12) return 'Mañana';
    elseif ($hora >= 12 && $hora < 19) return 'Tarde';
    elseif ($hora >= 19 && $hora < 24) return 'Noche';
    else return 'Turno';
}

function getRegistroTimestamp($userName) {
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = (int)date('j');
    $mes = $meses[(int)date('n') - 1];
    $ano = date('Y');
    $hora = date('G');
    $minuto = date('i');
    return "$dia $mes $ano $hora:$minuto | Creado por: $userName";
}

$id = $_GET['id'] ?? null;
if ($id === null) {
    header('Location: requerimientos.php');
    exit;
}

// Leer desde la BD local (igual que requerimientos.php)
try {
    $db = new LocalDbAdapter();
    $stmt = $db->pdo->prepare('SELECT * FROM requerimientos WHERE excel_row = ?');
    $stmt->execute([$id]);
    $requerimiento = $stmt->fetch(\PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error leyendo BD: ' . htmlspecialchars($e->getMessage()));
}

if (!$requerimiento) {
    header('Location: requerimientos.php');
    exit;
}

$error = '';
$success = false;

// Cargar combobox desde BD (igual que submit.php para que los nuevos valores aparezcan)
try {
    $dbCombo = new LocalDbAdapter();
    $tiposSolicitante    = array_map('trim', $dbCombo->getComboboxValues('solicitante'));
    $tiposRequerimientos = array_map('trim', $dbCombo->getComboboxValues('requerimiento'));
    $tiposNegocios       = array_map('trim', $dbCombo->getComboboxValues('negocio'));
    $tiposAmbientes      = array_map('trim', $dbCombo->getComboboxValues('ambiente'));
    $tiposCapa           = array_map('trim', $dbCombo->getComboboxValues('capa'));
    $tiposServidor       = array_map('trim', $dbCombo->getComboboxValues('servidor'));
    $tiposEstado         = array_map('trim', $dbCombo->getComboboxValues('estado'));
    $tiposSolicitud      = array_map('trim', $dbCombo->getComboboxValues('tipo_solicitud'));
    $tiposPase           = array_map('trim', $dbCombo->getComboboxValues('tipo_pase'));
    $tiposIC             = array_map('trim', $dbCombo->getComboboxValues('ic'));
} catch (Exception $e) {
    // Fallback a CSV si la BD no está disponible
    function leerCSV($archivo) {
        if (file_exists($archivo)) {
            $lines = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            return array_map('trim', $lines);
        }
        return [];
    }
    $tiposSolicitante    = leerCSV($storagePath . '/tipos_solicitante.csv');
    $tiposRequerimientos = leerCSV($storagePath . '/tipos_requerimientos.csv');
    $tiposNegocios       = leerCSV($storagePath . '/tipos_negocios.csv');
    $tiposAmbientes      = leerCSV($storagePath . '/tipos_ambientes.csv');
    $tiposCapa           = leerCSV($storagePath . '/tipos_capa.csv');
    $tiposServidor       = leerCSV($storagePath . '/tipos_servidor.csv');
    $tiposEstado         = leerCSV($storagePath . '/tipos_estado.csv');
    $tiposSolicitud      = leerCSV($storagePath . '/tipos_solicitud.csv');
    $tiposPase           = leerCSV($storagePath . '/tipos_pase.csv');
    $tiposIC             = leerCSV($storagePath . '/tipos_ic.csv');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar campos obligatorios
    $campos_obligatorios = ['requerimiento', 'solicitante', 'negocio', 'numero_ticket'];
    foreach ($campos_obligatorios as $campo) {
        if (empty($_POST[$campo])) {
            $error = "El campo '$campo' es obligatorio";
            break;
        }
    }
    
    if (!$error) {
        $data = [
            'turno'          => getTurno(),
            'fecha'          => $requerimiento['fecha'],
            'requerimiento'  => $_POST['requerimiento'] ?? '',
            'solicitante'    => $_POST['solicitante'] ?? '',
            'negocio'        => $_POST['negocio'] ?? '',
            'ambiente'       => $_POST['ambiente'] ?? '',
            'capa'           => $_POST['capa'] ?? '',
            'servidor'       => $_POST['servidor'] ?? '',
            'estado'         => $_POST['estado'] ?? '',
            'tipo_solicitud' => $_POST['tipo_solicitud'] ?? '',
            'numero_ticket'  => $_POST['numero_ticket'] ?? '',
            'tipo_pase'      => $_POST['tipo_pase'] ?? '',
            'ic'             => $_POST['ic'] ?? '',
            'cantidad'       => $requerimiento['cantidad'] ?? '1',
            'tiempo_total'   => $requerimiento['tiempo_total'] ?? '',
            'tiempo_unidad'  => $requerimiento['tiempo_unidad'] ?? '',
            'observaciones'  => $_POST['observaciones'] ?? '',
            'registro'       => getRegistroTimestamp($user['name']),
        ];

        try {
            $db = new LocalDbAdapter();
            $db->updateRequerimiento($id, $data);

            // Marcar para sync a Excel en background
            file_put_contents(__DIR__ . '/../storage/sync_pending.txt', "$id\n", FILE_APPEND | LOCK_EX);

            $success = true;
            $requerimiento = array_merge($requerimiento, $data);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Requerimiento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="js/combobox-dynamic.js"></script>
    <style>
        .ts-control { border-radius: 0.375rem !important; border-color: rgb(209 213 219) !important; padding: 0.5rem !important; background-color: white !important; }
        .ts-wrapper { border-radius: 0.375rem; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen py-8">
<div class="max-w-4xl mx-auto px-4">
    <div class="bg-white p-8 rounded-lg shadow-lg border border-indigo-200">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-3xl font-bold">
                    <span class="text-indigo-600">✏️ Editar Requerimiento</span>
                </h1>
                <p class="text-gray-600 text-sm mt-1">Ticket: <strong><?php echo htmlspecialchars($requerimiento['numero_ticket']); ?></strong></p>
            </div>
            <a href="requerimientos.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md font-semibold transition">
                ← Volver
            </a>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                ❌ Error: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                ✅ Requerimiento actualizado correctamente
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- NUMERO DE TICKET (Read-only) -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">NUMERO DE TICKET</label>
                <input type="text" value="<?php echo htmlspecialchars($requerimiento['numero_ticket']); ?>" disabled
                    class="w-full p-2 border border-gray-300 rounded bg-gray-100 text-gray-600" />
                <input type="hidden" name="numero_ticket" value="<?php echo htmlspecialchars($requerimiento['numero_ticket']); ?>" />
            </div>

            <!-- Row 1: SOLICITANTE, REQUERIMIENTO -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SOLICITANTE</label>
                    <select name="solicitante" class="searchable w-full" required>
                        <option value="">Selecciona un solicitante</option>
                        <?php if (!empty($requerimiento['solicitante']) && !in_array($requerimiento['solicitante'], $tiposSolicitante)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['solicitante']); ?>" selected><?php echo htmlspecialchars($requerimiento['solicitante']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposSolicitante as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>" <?php echo ($s === $requerimiento['solicitante']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">REQUERIMIENTO</label>
                    <select name="requerimiento" class="searchable w-full" required>
                        <option value="">Selecciona un requerimiento</option>
                        <?php if (!empty($requerimiento['requerimiento']) && !in_array($requerimiento['requerimiento'], $tiposRequerimientos)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['requerimiento']); ?>" selected><?php echo htmlspecialchars($requerimiento['requerimiento']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposRequerimientos as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo ($r === $requerimiento['requerimiento']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($r); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: NEGOCIO, AMBIENTE -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">NEGOCIO</label>
                    <select name="negocio" class="searchable w-full" required>
                        <option value="">Selecciona un negocio</option>
                        <?php if (!empty($requerimiento['negocio']) && !in_array($requerimiento['negocio'], $tiposNegocios)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['negocio']); ?>" selected><?php echo htmlspecialchars($requerimiento['negocio']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposNegocios as $n): ?>
                            <option value="<?php echo htmlspecialchars($n); ?>" <?php echo ($n === $requerimiento['negocio']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($n); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">AMBIENTE</label>
                    <select name="ambiente" class="searchable w-full" required>
                        <option value="">Selecciona un ambiente</option>
                        <?php if (!empty($requerimiento['ambiente']) && !in_array($requerimiento['ambiente'], $tiposAmbientes)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['ambiente']); ?>" selected><?php echo htmlspecialchars($requerimiento['ambiente']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposAmbientes as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>" <?php echo ($a === $requerimiento['ambiente']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($a); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 3: CAPA, SERVIDOR -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">CAPA</label>
                    <select name="capa" class="searchable w-full">
                        <option value="">Selecciona una capa</option>
                        <?php if (!empty($requerimiento['capa']) && !in_array($requerimiento['capa'], $tiposCapa)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['capa']); ?>" selected><?php echo htmlspecialchars($requerimiento['capa']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposCapa as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo ($c === $requerimiento['capa']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SERVIDOR</label>
                    <select name="servidor" class="searchable w-full">
                        <option value="">Selecciona un servidor</option>
                        <?php if (!empty($requerimiento['servidor']) && !in_array($requerimiento['servidor'], $tiposServidor)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['servidor']); ?>" selected><?php echo htmlspecialchars($requerimiento['servidor']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposServidor as $sv): ?>
                            <option value="<?php echo htmlspecialchars($sv); ?>" <?php echo ($sv === $requerimiento['servidor']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sv); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 4: ESTADO, TIPO SOLICITUD -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ESTADO</label>
                    <select name="estado" class="searchable w-full">
                        <option value="">Selecciona un estado</option>
                        <?php if (!empty($requerimiento['estado']) && !in_array($requerimiento['estado'], $tiposEstado)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['estado']); ?>" selected><?php echo htmlspecialchars($requerimiento['estado']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposEstado as $e): ?>
                            <option value="<?php echo htmlspecialchars($e); ?>" <?php echo ($e === $requerimiento['estado']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($e); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">TIPO SOLICITUD</label>
                    <select name="tipo_solicitud" class="searchable w-full">
                        <option value="">Selecciona tipo de solicitud</option>
                        <?php if (!empty($requerimiento['tipo_solicitud']) && !in_array($requerimiento['tipo_solicitud'], $tiposSolicitud)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['tipo_solicitud']); ?>" selected><?php echo htmlspecialchars($requerimiento['tipo_solicitud']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposSolicitud as $ts): ?>
                            <option value="<?php echo htmlspecialchars($ts); ?>" <?php echo ($ts === $requerimiento['tipo_solicitud']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ts); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 5: TIPO PASE, IC -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">TIPO PASE</label>
                    <select name="tipo_pase" class="searchable w-full">
                        <option value="">Selecciona tipo de pase</option>
                        <?php if (!empty($requerimiento['tipo_pase']) && !in_array($requerimiento['tipo_pase'], $tiposPase)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['tipo_pase']); ?>" selected><?php echo htmlspecialchars($requerimiento['tipo_pase']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposPase as $tp): ?>
                            <option value="<?php echo htmlspecialchars($tp); ?>" <?php echo ($tp === $requerimiento['tipo_pase']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tp); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">IC</label>
                    <select name="ic" class="searchable w-full">
                        <option value="">Selecciona IC</option>
                        <?php if (!empty($requerimiento['ic']) && !in_array($requerimiento['ic'], $tiposIC)): ?>
                            <option value="<?php echo htmlspecialchars($requerimiento['ic']); ?>" selected><?php echo htmlspecialchars($requerimiento['ic']); ?></option>
                        <?php endif; ?>
                        <?php foreach ($tiposIC as $ic): ?>
                            <option value="<?php echo htmlspecialchars($ic); ?>" <?php echo ($ic === $requerimiento['ic']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ic); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">OBSERVACIONES</label>
                <textarea name="observaciones" placeholder="Observaciones adicionales"
                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 h-24"><?php echo htmlspecialchars($requerimiento['observaciones']); ?></textarea>
            </div>

            <!-- METADATA (Read-only) -->
            <div class="bg-gray-50 p-4 rounded border border-gray-200 text-sm text-gray-600">
                <p><strong>Fecha:</strong> <?php echo htmlspecialchars($requerimiento['fecha']); ?></p>
                <p><strong>Registro:</strong> <?php echo htmlspecialchars($requerimiento['registro']); ?></p>
            </div>

            <!-- BOTONES -->
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.querySelector('form').reset()" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white py-3 rounded-md font-semibold transition">
                    Revertir Cambios
                </button>
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-md font-semibold transition">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.searchable').forEach(function (select) {
            const fieldName = select.name;
            if (['solicitante', 'requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'].includes(fieldName)) {
                initializeCombobox(fieldName, select);
            } else {
                new TomSelect(select, {
                    create: false,
                    maxOptions: 500,
                    sortField: { field: 'text', direction: 'asc' }
                });
            }
        });
    });
</script>
</body>
</html>
