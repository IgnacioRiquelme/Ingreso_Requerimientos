<?php
date_default_timezone_set('America/Santiago');
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\ExcelGraphAdapter;
use Requerimiento\LocalDbAdapter;

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
$error = '';
$success = false;

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
        return 'Turno';
    }
}

// Normalizar texto: minúsculas sin tildes
function normalizarTexto(string $s): string {
    $s = mb_strtolower(trim($s));
    return str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $s);
}

function esReqQA(string $req): bool {
    return normalizarTexto($req) === 'pase a qa';
}

function esReqProduccion(string $req): bool {
    $n = normalizarTexto($req);
    return str_contains($n, 'produccion') || str_contains($n, 'paso a prod') || str_contains($n, 'pasos a prod');
}

// Función para generar timestamp en formato "1 octubre 2025 9:32 | Creado por: nombre"
function getRegistroTimestamp($userName) {
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $dia = (int)date('j');
    $mes = $meses[(int)date('n') - 1];
    $ano = date('Y');
    $hora = date('G');
    $minuto = date('i');
    return "$dia $mes $ano $hora:$minuto | Creado por: $userName";
}

// PASO 0: Asegurar que los combobox estén inicializados desde CSV
require_once __DIR__ . '/ensure_combobox.php';

// Leer valores de combobox desde BD (rápido)
try {
    $db = new LocalDbAdapter();
    $tiposSolicitante = $db->getComboboxValues('solicitante');
    $tiposRequerimientos = $db->getComboboxValues('requerimiento');
    $tiposNegocios = $db->getComboboxValues('negocio');
    $tiposAmbientes = $db->getComboboxValues('ambiente');
    $tiposCapa = $db->getComboboxValues('capa');
    $tiposServidor = $db->getComboboxValues('servidor');
    $tiposEstado = $db->getComboboxValues('estado');
    $tiposSolicitud = $db->getComboboxValues('tipo_solicitud');
    $tiposPase = $db->getComboboxValues('tipo_pase');
    $tiposIC = $db->getComboboxValues('ic');
    
    // Hacer trim() a todos los valores para eliminar espacios adicionales
    $tiposSolicitante = array_map('trim', $tiposSolicitante);
    $tiposRequerimientos = array_map('trim', $tiposRequerimientos);
    $tiposNegocios = array_map('trim', $tiposNegocios);
    $tiposAmbientes = array_map('trim', $tiposAmbientes);
    $tiposCapa = array_map('trim', $tiposCapa);
    $tiposServidor = array_map('trim', $tiposServidor);
    $tiposEstado = array_map('trim', $tiposEstado);
    $tiposSolicitud = array_map('trim', $tiposSolicitud);
    $tiposPase = array_map('trim', $tiposPase);
    $tiposIC = array_map('trim', $tiposIC);
} catch (Exception $e) {
    die('Error cargando combobox desde BD: ' . htmlspecialchars($e->getMessage()));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar todos los campos obligatorios (excepto observaciones)
    $campos_obligatorios = [
        'numero_ticket' => 'Número de Ticket',
        'solicitante' => 'Solicitante',
        'requerimiento' => 'Requerimiento',
        'negocio' => 'Negocio',
        'ambiente' => 'Ambiente',
        'capa' => 'Capa',
        'servidor' => 'Servidor',
        'estado' => 'Estado',
        'tipo_solicitud' => 'Tipo de Solicitud',
        'tipo_pase' => 'Tipo de Pase',
        'ic' => 'IC'
    ];
    
    foreach ($campos_obligatorios as $campo => $nombre) {
        if (empty(trim($_POST[$campo] ?? ''))) {
            $error = "El campo '$nombre' es obligatorio y no puede estar vacío";
            break;
        }
    }
    
    if (!$error) {
        try {
            $db = new LocalDbAdapter();

            // Validar ticket duplicado
            $ticketBuscar = trim($_POST['numero_ticket']);
            $nuevoReq     = trim($_POST['requerimiento']);
            $chkStmt = $db->pdo->prepare(
                "SELECT excel_row, requerimiento FROM requerimientos WHERE LOWER(TRIM(numero_ticket)) = LOWER(?)"
            );
            $chkStmt->execute([$ticketBuscar]);
            $existentes = $chkStmt->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($existentes)) {
                $nuevoEsProd = esReqProduccion($nuevoReq);
                $todosQA     = array_reduce($existentes, fn($carry, $ex) => $carry && esReqQA($ex['requerimiento']), true);
                if (!($nuevoEsProd && $todosQA)) {
                    $ids = implode(', ', array_column($existentes, 'excel_row'));
                    throw new \Exception("El ticket '$ticketBuscar' ya está registrado (ID: $ids). Solo se permite un segundo ingreso si el existente es 'Pase a QA' y el nuevo es 'Pase a Producción'.");
                }
            }

            // PASO 1: Obtener la siguiente fila disponible desde la BD local (evita duplicados)
            $stmt = $db->pdo->query('SELECT MAX(excel_row) FROM requerimientos');
            $lastRow = (int)($stmt->fetchColumn() ?: 0);
            $excelRow = $lastRow + 1;
            
            // Datos para guardar
            $data = [
                'turno'           => getTurno(),
                'fecha'           => date('d/m/Y'),
                'requerimiento'   => $_POST['requerimiento'],
                'solicitante'     => $_POST['solicitante'],
                'negocio'         => $_POST['negocio'],
                'ambiente'        => $_POST['ambiente'] ?? '',
                'capa'            => $_POST['capa'] ?? '',
                'servidor'        => $_POST['servidor'] ?? '',
                'estado'          => $_POST['estado'] ?? '',
                'tipo_solicitud'  => $_POST['tipo_solicitud'] ?? '',
                'numero_ticket'   => $_POST['numero_ticket'],
                'tipo_pase'       => $_POST['tipo_pase'] ?? '',
                'ic'              => $_POST['ic'] ?? '',
                'cantidad'        => '1',
                'tiempo_total'    => '',
                'tiempo_unidad'   => '',
                'observaciones'   => $_POST['observaciones'] ?? '',
                'registro'        => getRegistroTimestamp($user['name']),
            ];
            
            // PASO 2: Guardar en BD (fuente de verdad única)
            $db->insertRequerimiento($excelRow, $data);
            
            $success = true;
            $_POST = [];
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
    <title>Ingresar Requerimiento</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="js/combobox-dynamic.js"></script>
    <script src="js/combobox-rules.js"></script>
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
                    <span class="text-indigo-600">📋 Nuevo Requerimiento</span>
                </h1>
                <p class="text-gray-600 text-sm mt-1">Usuario: <strong><?php echo htmlspecialchars($user['name']); ?></strong></p>
            </div>
            <div class="flex gap-3">
                <a href="requerimientos.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md font-semibold transition text-sm">
                    📊 Ver Requerimientos
                </a>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md font-semibold transition text-sm">
                    Cerrar sesión
                </a>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                ❌ Error: <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                ✅ Requerimiento guardado correctamente
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <!-- NUMERO DE TICKET -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">NUMERO DE TICKET <span class="text-red-500">*</span></label>
                <input type="text" name="numero_ticket" placeholder="REQ 2026-XXXXX" required
                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500" />
            </div>

            <!-- Row 1: SOLICITANTE, REQUERIMIENTO -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SOLICITANTE <span class="text-red-500">*</span></label>
                    <select name="solicitante" class="searchable w-full" required>
                        <option value="">Selecciona un solicitante</option>
                        <?php foreach ($tiposSolicitante as $s): ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">REQUERIMIENTO <span class="text-red-500">*</span></label>
                    <select name="requerimiento" class="searchable w-full" required>
                        <option value="">Selecciona un requerimiento</option>
                        <?php foreach ($tiposRequerimientos as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($r); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 2: NEGOCIO, AMBIENTE -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">NEGOCIO <span class="text-red-500">*</span></label>
                    <select name="negocio" class="searchable w-full" required>
                        <option value="">Selecciona un negocio</option>
                        <?php foreach ($tiposNegocios as $n): ?>
                            <option value="<?php echo htmlspecialchars($n); ?>"><?php echo htmlspecialchars($n); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">AMBIENTE <span class="text-red-500">*</span></label>
                    <select name="ambiente" class="searchable w-full" required>
                        <option value="">Selecciona un ambiente</option>
                        <?php foreach ($tiposAmbientes as $a): ?>
                            <option value="<?php echo htmlspecialchars($a); ?>"><?php echo htmlspecialchars($a); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 3: CAPA, SERVIDOR -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">CAPA <span class="text-red-500">*</span></label>
                    <select name="capa" class="searchable w-full" required>
                        <option value="">Selecciona una capa</option>
                        <?php foreach ($tiposCapa as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">SERVIDOR <span class="text-red-500">*</span></label>
                    <select name="servidor" class="searchable w-full" required>
                        <option value="">Selecciona un servidor</option>
                        <?php foreach ($tiposServidor as $sv): ?>
                            <option value="<?php echo htmlspecialchars($sv); ?>"><?php echo htmlspecialchars($sv); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 4: ESTADO, TIPO SOLICITUD -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">ESTADO <span class="text-red-500">*</span></label>
                    <select name="estado" class="searchable w-full" required>
                        <option value="">Selecciona un estado</option>
                        <?php foreach ($tiposEstado as $e): ?>
                            <option value="<?php echo htmlspecialchars($e); ?>"><?php echo htmlspecialchars($e); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">TIPO SOLICITUD <span class="text-red-500">*</span></label>
                    <select name="tipo_solicitud" class="searchable w-full" required>
                        <option value="">Selecciona tipo de solicitud</option>
                        <?php foreach ($tiposSolicitud as $ts): ?>
                            <option value="<?php echo htmlspecialchars($ts); ?>"><?php echo htmlspecialchars($ts); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Row 5: TIPO PASE, IC -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">TIPO PASE <span class="text-red-500">*</span></label>
                    <select name="tipo_pase" class="searchable w-full" required>
                        <option value="">Selecciona tipo de pase</option>
                        <?php foreach ($tiposPase as $tp): ?>
                            <option value="<?php echo htmlspecialchars($tp); ?>"><?php echo htmlspecialchars($tp); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">IC <span class="text-red-500">*</span></label>
                    <select name="ic" class="searchable w-full" required>
                        <option value="">Selecciona IC</option>
                        <?php foreach ($tiposIC as $ic): ?>
                            <option value="<?php echo htmlspecialchars($ic); ?>"><?php echo htmlspecialchars($ic); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- OBSERVACIONES -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">OBSERVACIONES</label>
                <textarea name="observaciones" placeholder="Observaciones adicionales"
                    class="w-full p-2 border border-gray-300 rounded focus:ring-2 focus:ring-indigo-500 h-24"></textarea>
            </div>

            <!-- BOTONES -->
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="document.querySelector('form').reset()" class="flex-1 bg-yellow-500 hover:bg-yellow-600 text-white py-3 rounded-md font-semibold transition">
                    Editar Requerimiento
                </button>
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-md font-semibold transition">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Lista de campos obligatorios
        const requiredFields = ['numero_ticket', 'solicitante', 'requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'];
        
        // Inicializar Tom-Select con manejo dinámico de valores
        document.querySelectorAll('.searchable').forEach(function (select) {
            const fieldName = select.name;
            let tomSelectInstance;
            
            // Si es solicitante o campo protegido, usar inicialización dinámica
            if (fieldName === 'solicitante' || ['requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'].includes(fieldName)) {
                tomSelectInstance = initializeCombobox(fieldName, select);
            } else {
                // Otros campos: Tom-Select genérico
                tomSelectInstance = new TomSelect(select, { 
                    create: false, 
                    maxOptions: 500, 
                    sortField: { field: 'text', direction: 'asc' } 
                });
            }
            
            // Registrar instancia para el sistema de reglas dinámicas
            if (tomSelectInstance) {
                registerTomSelectInstance(fieldName, tomSelectInstance);
            }
        });
        
        // Agregar validación al envío del formulario
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                // Validar que todos los campos obligatorios tengan valor
                let errores = [];
                
                requiredFields.forEach(fieldName => {
                    const field = form.querySelector('[name="' + fieldName + '"]');
                    if (field) {
                        const value = field.value ? field.value.trim() : '';
                        if (!value) {
                            const label = field.parentElement.querySelector('label')?.textContent || fieldName;
                            errores.push(label.replace(/\s\*$/, '')); // Quitar el asterisco
                        }
                    }
                });
                
                if (errores.length > 0) {
                    e.preventDefault();
                    alert('❌ Los siguientes campos son obligatorios:\n\n' + errores.join('\n'));
                    return false;
                }
            });
        }
    });
</script>
</body>
</html>
