<?php
date_default_timezone_set('America/Santiago');
session_start();
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\ExcelGraphAdapter;

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$storagePath = __DIR__ . '/../storage';
$csvFile = $storagePath . '/Requerimientos.csv';

// Función para determinar el turno según la hora del sistema
function getTurno() {
    $hora = (int)date('H');
    if ($hora >= 8 && $hora < 12) {
        return 'Mañana';
    } elseif ($hora >= 12 && $hora < 19) {
        return 'Tarde';
    } elseif ($hora >= 19 && $hora < 24) {
        return 'Noche';
    } else {
        return 'Turno';  // 00:00 a 07:59
    }
}

// Función para generar timestamp en formato "1 octubre 2025 9:32 | Creado por: nombre"
function getRegistroTimestamp($userName) {
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = (int)date('j');  // Día sin ceros iniciales
    $mes = $meses[(int)date('n') - 1];  // Mes en texto
    $ano = date('Y');
    $hora = date('G');  // Hora sin ceros iniciales
    $minuto = date('i');  // Minuto con ceros
    return "$dia $mes $ano $hora:$minuto | Creado por: $userName";
}

$id = $_GET['id'] ?? null;
if ($id === null) {
    header('Location: requerimientos.php');
    exit;
}

$requerimiento = null;
$allRequerimientos = [];
$lineIndex = 0;

// Conversión de serial Excel a fecha legible
function excelDateToString($excelDate) {
    if (is_numeric($excelDate) && $excelDate > 0) {
        $unixDate = ($excelDate - 25569) * 86400;
        return date('d/m/Y', $unixDate);
    }
    return (string)$excelDate;
}

// Leer desde SharePoint via Graph API
// Mapeo: A(Turno), B(Fecha), C(Requerimiento), D(Solicitante), E(Negocio), F(Ambiente), G(Capa), H(Servidor)
// I(Estado), J(Tipo Solicitud), K(Ticket), L(Tipo de Pase), M(IC), N(Cantidad), O(Tiempo Total), P(Tiempo unidad), Q(Observaciones), R(ID), S(Registro)
try {
    $graphAdapter = new ExcelGraphAdapter();
    $worksheetName = getenv('WORKSHEET_NAME') ?: 'Pasos a Producción';
    $allRows = $graphAdapter->getAllRows($worksheetName);
    foreach ($allRows as $rowNum => $row) {
        // Saltar fila 1 (título), fila 2 (vacía), fila 3 (encabezados)
        if ($rowNum < 3) continue;
        if (empty($row[0]) && empty($row[1]) && empty($row[2])) continue; // saltar filas vacías
        $excelRow = $rowNum + 1; // fila real en Excel (rowNum es 0-based)
        if ($excelRow == $id) {
            $requerimiento = [
                'numero_ticket' => $row[10] ?? '',
                'solicitante' => $row[3] ?? '',
                'requerimiento' => $row[2] ?? '',
                'negocio' => $row[4] ?? '',
                'ambiente' => $row[5] ?? '',
                'capa' => $row[6] ?? '',
                'servidor' => $row[7] ?? '',
                'estado' => $row[8] ?? '',
                'tipo_solicitud' => $row[9] ?? '',
                'tipo_pase' => $row[11] ?? '',
                'ic' => $row[12] ?? '',
                'observaciones' => $row[16] ?? '',
                'fecha' => excelDateToString($row[1] ?? ''),
                'usuario' => $row[3] ?? '',
                'timestamp' => $row[18] ?? '',
                'excel_row' => $excelRow
            ];
        }
    }
} catch (Exception $e) {
    die('Error conectando a SharePoint: ' . htmlspecialchars($e->getMessage()));
}

if (!$requerimiento) {
    header('Location: requerimientos.php');
    exit;
}

$error = '';
$success = false;

// Función para leer CSV
function leerCSV($archivo) {
    if (file_exists($archivo)) {
        $lines = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_map('trim', $lines);
    }
    return [];
}

$tiposSolicitante = leerCSV($storagePath . '/tipos_solicitante.csv');
$tiposRequerimientos = leerCSV($storagePath . '/tipos_requerimientos.csv');
$tiposNegocios = leerCSV($storagePath . '/tipos_negocios.csv');
$tiposAmbientes = leerCSV($storagePath . '/tipos_ambientes.csv');
$tiposCapa = leerCSV($storagePath . '/tipos_capa.csv');
$tiposServidor = leerCSV($storagePath . '/tipos_servidor.csv');
$tiposEstado = leerCSV($storagePath . '/tipos_estado.csv');
$tiposSolicitud = leerCSV($storagePath . '/tipos_solicitud.csv');
$tiposPase = leerCSV($storagePath . '/tipos_pase.csv');
$tiposIC = leerCSV($storagePath . '/tipos_ic.csv');

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
        // Orden correcto de columnas del Excel: A-S
        // A=Turno, B=Fecha, C=Requerimiento, D=Solicitante, E=Negocio, F=Ambiente, G=Capa, H=Servidor
        // I=Estado, J=Tipo Solicitud, K=Ticket, L=Tipo de Pase, M=IC, N=Cantidad, O=Tiempo Total, P=Tiempo unidad, Q=Observaciones, R=ID, S=Registro
        $updatedRow = [
            getTurno(),  // [0] A = Turno (actualizado automáticamente)
        $requerimiento['fecha'],  // [1] B = Fecha (no cambia)
        $_POST['requerimiento'] ?? '',  // [2] C = Requerimiento
        $_POST['solicitante'] ?? '',  // [3] D = Solicitante
        $_POST['negocio'] ?? '',  // [4] E = Negocio
        $_POST['ambiente'] ?? '',  // [5] F = Ambiente
        $_POST['capa'] ?? '',  // [6] G = Capa
        $_POST['servidor'] ?? '',  // [7] H = Servidor
        $_POST['estado'] ?? '',  // [8] I = Estado
        $_POST['tipo_solicitud'] ?? '',  // [9] J = Tipo Solicitud
        $_POST['numero_ticket'] ?? '',  // [10] K = Ticket
        $_POST['tipo_pase'] ?? '',  // [11] L = Tipo de Pase
        $_POST['ic'] ?? '',  // [12] M = IC
        '1',  // [13] N = Cantidad
        '',  // [14] O = Tiempo Total
        '',  // [15] P = Tiempo unidad
        $_POST['observaciones'] ?? '',  // [16] Q = Observaciones
        '',  // [17] R = ID
        getRegistroTimestamp($user['name'])  // [18] S = Registro
    ];

// Actualizar fila en Excel via Graph API
        try {
            $graphAdapter = new ExcelGraphAdapter();
            $worksheetName = getenv('WORKSHEET_NAME') ?: 'Pasos a Producción';
            $excelRow = $requerimiento['excel_row'] ?? ($id + 2); // +2: fila 1 es cabecera
            $graphAdapter->updateRowInWorksheet($worksheetName, $excelRow, $updatedRow);
            $success = true;
        
        // Recargar datos
        $requerimiento = [
            'numero_ticket' => $updatedRow[10] ?? '',  // K
            'solicitante' => $updatedRow[3] ?? '',     // D
            'requerimiento' => $updatedRow[2] ?? '',   // C
            'negocio' => $updatedRow[4] ?? '',         // E
            'ambiente' => $updatedRow[5] ?? '',        // F
            'capa' => $updatedRow[6] ?? '',            // G
            'servidor' => $updatedRow[7] ?? '',        // H
            'estado' => $updatedRow[8] ?? '',          // I
            'tipo_solicitud' => $updatedRow[9] ?? '',  // J
            'tipo_pase' => $updatedRow[11] ?? '',      // L
            'ic' => $updatedRow[12] ?? '',             // M
            'observaciones' => $updatedRow[16] ?? '',  // Q
            'fecha' => $updatedRow[1] ?? '',           // B
            'usuario' => $updatedRow[3] ?? '',         // D
            'timestamp' => $updatedRow[18] ?? ''       // S
        ];
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
            </div>

            <!-- Row 1: SOLICITANTE, REQUERIMIENTO -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SOLICITANTE</label>
                    <select name="solicitante" class="searchable w-full" required>
                        <option value="">Selecciona un solicitante</option>
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
                <p><strong>Creado por:</strong> <?php echo htmlspecialchars($requerimiento['usuario']); ?></p>
                <p><strong>Fecha:</strong> <?php echo htmlspecialchars($requerimiento['fecha']); ?></p>
                <p><strong>Timestamp:</strong> <?php echo htmlspecialchars($requerimiento['timestamp']); ?></p>
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
            new TomSelect(select, { 
                create: false, 
                maxOptions: 500, 
                sortField: { field: 'text', direction: 'asc' } 
            });
        });
    });
</script>
</body>
</html>
