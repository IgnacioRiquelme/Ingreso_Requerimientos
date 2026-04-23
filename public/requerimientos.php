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

// Conversión de serial Excel a fecha legible
function excelDateToString($excelDate) {
    if (is_numeric($excelDate) && $excelDate > 0) {
        $unixDate = ($excelDate - 25569) * 86400;
        return date('d/m/Y', $unixDate);
    }
    return (string)$excelDate;
}

// PASO 0: Asegurar que los combobox estén inicializados desde CSV
require_once __DIR__ . '/ensure_combobox.php';

// LEER DE LA BD (rápido)
try {
    $db = new LocalDbAdapter();
    $requerimientos = $db->getAllRequerimientos();
    $graphError = '';
} catch (Exception $e) {
    $graphError = 'Error conectando a la BD: ' . htmlspecialchars($e->getMessage());
    $requerimientos = [];
}

// Extraer valores únicos para los filtros
$filtros = [
    'turno' => array_unique(array_column($requerimientos, 'turno')),
    'numero_ticket' => array_unique(array_column($requerimientos, 'numero_ticket')),
    'solicitante' => array_unique(array_column($requerimientos, 'solicitante')),
    'requerimiento' => array_unique(array_column($requerimientos, 'requerimiento')),
    'negocio' => array_unique(array_column($requerimientos, 'negocio')),
    'ambiente' => array_unique(array_column($requerimientos, 'ambiente')),
    'capa' => array_unique(array_column($requerimientos, 'capa')),
    'servidor' => array_unique(array_column($requerimientos, 'servidor')),
    'estado' => array_unique(array_column($requerimientos, 'estado')),
    'tipo_solicitud' => array_unique(array_column($requerimientos, 'tipo_solicitud')),
    'tipo_pase' => array_unique(array_column($requerimientos, 'tipo_pase')),
];

// Limpiar arrays (remover vacíos, null, etc)
foreach ($filtros as $key => $values) {
    $filtros[$key] = array_filter(array_map('trim', $values));
    sort($filtros[$key]);
}

// Agrupar por rango de fechas
$hoy = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requerimientos - Sistema</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <style>
        .status-exitoso { @apply text-green-700 bg-green-100; }
        .status-rechazado { @apply text-red-700 bg-red-100; }
        .status-encurso { @apply text-yellow-700 bg-yellow-100; }
        .status-erroneo { @apply text-red-700 bg-red-100; }
        .status-pendiente { @apply text-orange-700 bg-orange-100; }
        .filtro-activo { @apply bg-blue-100 border-blue-400; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="flex h-screen">
        <!-- SIDEBAR IZQUIERDO -->
        <div class="w-64 bg-white shadow-lg overflow-y-auto">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-6">Acciones</h2>
                <div class="space-y-3 mb-8">
                    <button onclick="filtrarDelDia()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                        📅 Requerimientos del día
                    </button>
                    <button onclick="limpiarFiltros()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold transition">
                        🔄 Filtrar
                    </button>
                </div>

                <h3 class="text-md font-bold text-gray-900 mb-4">Filtros</h3>
                <div class="mb-4">
                    <select id="filterType" onchange="agregarFiltro()" class="w-full border border-gray-300 rounded-lg p-2 text-sm text-gray-700">
                        <option value="">Agregar filtro...</option>
                        <option value="numero_ticket">Nº de requerimiento</option>
                        <option value="fecha">Rango de fechas</option>
                        <option value="requerimiento">Tipo de requerimiento</option>
                        <option value="negocio">Tipo de negocio</option>
                        <option value="ambiente">Tipo de ambiente</option>
                        <option value="capa">Capa</option>
                        <option value="servidor">Servidor</option>
                        <option value="estado">Estado</option>
                        <option value="tipo_solicitud">Tipo de solicitud</option>
                        <option value="tipo_pase">Tipo de pase</option>
                        <option value="solicitante">Solicitante</option>
                    </select>
                </div>

                <!-- Filtros activos dinámicos -->
                <div id="filtrosActivos" class="space-y-3">
                    <!-- Se llenan con JavaScript -->
                </div>
            </div>
        </div>

        <!-- CONTENIDO DERECHO -->
        <div class="flex-1 overflow-auto">
            <div class="max-w-full px-8 py-8">
                <!-- Header -->
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 class="text-4xl font-bold text-gray-800">📋 Requerimientos</h1>
                        <p class="text-gray-600 mt-2">Total: <strong id="totalCount"><?php echo count($requerimientos); ?></strong> requerimientos</p>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="exportarAExcel()" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                            📊 Exportar a Excel
                        </button>
                        <a href="submit.php" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                            ➕ Nuevo Requerimiento
                        </a>
                        <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                            Cerrar Sesión
                        </a>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="bg-white rounded-lg shadow-lg overflow-hidden">
                    <?php if ($graphError): ?>
                        <div class="p-6 bg-red-50 border border-red-300 rounded m-4">
                            <p class="text-red-700 font-semibold">❌ Error conectando a la BD:</p>
                            <p class="text-red-600 text-sm mt-1"><?php echo htmlspecialchars($graphError); ?></p>
                        </div>
                    <?php elseif (empty($requerimientos)): ?>
                        <div class="p-12 text-center">
                            <p class="text-gray-500 text-lg">No hay requerimientos registrados</p>
                            <a href="submit.php" class="text-indigo-600 hover:text-indigo-700 font-semibold mt-4 inline-block">
                                Crear el primer requerimiento →
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full" id="tablaRequerimientos">
                                <thead class="bg-gray-100 border-b">
                                    <tr>
                                        <th class="px-4 py-4 text-left text-sm font-semibold text-gray-500">#ID</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Turno</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Fecha</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Ticket</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Requerimiento</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Solicitante</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Negocio</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Estado</th>
                                        <th class="px-6 py-4 text-left text-sm font-semibold text-gray-700">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y" id="tablaBody">
                                    <?php foreach ($requerimientos as $req): ?>
                                        <tr class="hover:bg-gray-50 transition fila-data" data-turno="<?php echo htmlspecialchars($req['turno']); ?>" data-fecha="<?php echo htmlspecialchars($req['fecha']); ?>" data-ticket="<?php echo htmlspecialchars($req['numero_ticket']); ?>" data-requerimiento="<?php echo htmlspecialchars($req['requerimiento']); ?>" data-solicitante="<?php echo htmlspecialchars($req['solicitante']); ?>" data-negocio="<?php echo htmlspecialchars($req['negocio']); ?>" data-ambiente="<?php echo htmlspecialchars($req['ambiente']); ?>" data-capa="<?php echo htmlspecialchars($req['capa']); ?>" data-servidor="<?php echo htmlspecialchars($req['servidor']); ?>" data-estado="<?php echo htmlspecialchars($req['estado']); ?>" data-tipo-solicitud="<?php echo htmlspecialchars($req['tipo_solicitud']); ?>" data-tipo-pase="<?php echo htmlspecialchars($req['tipo_pase']); ?>" data-ic="<?php echo htmlspecialchars($req['ic']); ?>" data-cantidad="<?php echo htmlspecialchars($req['cantidad'] ?? ''); ?>" data-tiempo-total="<?php echo htmlspecialchars($req['tiempo_total'] ?? ''); ?>" data-tiempo-unidad="<?php echo htmlspecialchars($req['tiempo_unidad'] ?? ''); ?>" data-observaciones="<?php echo htmlspecialchars($req['observaciones'] ?? ''); ?>" data-id="<?php echo htmlspecialchars($req['excel_row'] ?? ''); ?>" data-registro="<?php echo htmlspecialchars($req['registro'] ?? ''); ?>">
                                            <td class="px-4 py-4 font-mono text-xs text-gray-400 font-semibold"><?php echo htmlspecialchars($req['excel_row'] ?? ''); ?></td>
                                            <td class="px-6 py-4 font-semibold text-gray-900"><?php echo htmlspecialchars($req['turno']); ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($req['fecha']); ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($req['numero_ticket']); ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($req['requerimiento']); ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($req['solicitante']); ?></td>
                                            <td class="px-6 py-4 text-gray-700"><?php echo htmlspecialchars($req['negocio']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="status-<?php echo strtolower(str_replace(' ', '', $req['estado'])); ?> px-3 py-1 rounded-full text-xs font-semibold">
                                                    <?php echo htmlspecialchars($req['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <a href="edit.php?id=<?php echo $req['excel_row']; ?>" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-1 rounded text-sm font-semibold transition">
                                                    Editar
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Admin Section -->
                <div class="mt-8 text-center space-y-2">
                    <p class="text-gray-500 text-xs bg-blue-50 p-3 rounded border border-blue-200">
                        ℹ️ <strong>Los datos se guardan inmediatamente en la BD local.</strong><br>
                        Se sincronizan a Excel automáticamente en background (puede tomar 15-30 minutos).
                    </p>
                    <p class="text-gray-500 text-xs">
                        <a href="sync_excel_to_db.php" class="text-gray-400 hover:text-gray-600 underline">
                            Importar datos Excel a BD (primera vez)
                        </a>
                    </p>
                    <p class="text-gray-500 text-xs">
                        <a href="reset_db.php" class="text-gray-400 hover:text-gray-600 underline">
                            Resetear BD y reimportar (si hay errores)
                        </a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Seleccionar día -->
    <div id="modalSeleccionarDia" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-8 shadow-2xl w-96">
            <h2 class="text-2xl font-bold text-gray-800 mb-6">📅 Seleccionar Día</h2>
            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Elige el día que deseas consultar:</label>
                <input type="date" id="inputFechaModal" class="w-full border-2 border-gray-300 rounded-lg p-3 text-gray-700 focus:border-blue-500 focus:outline-none">
            </div>
            <div class="flex gap-3 justify-end">
                <button onclick="cerrarModalDia()" class="bg-gray-400 hover:bg-gray-500 text-white px-6 py-2 rounded-lg font-semibold transition">
                    Cancelar
                </button>
                <button onclick="aceptarFechaModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-semibold transition">
                    ✓ Aceptar
                </button>
            </div>
        </div>
    </div>

    <!-- Modal y scripts abajo -->

<script>
const hoyDate = '<?php echo $hoy; ?>';
const filtrosDisponibles = <?php echo json_encode($filtros); ?>;
let filtrosActuales = {};

// Convertir fecha d/m/Y a número para comparación
function fechaANumero(fecha) {
    if (!fecha) return 0;
    const parts = fecha.split('/');
    if (parts.length !== 3) return 0;
    const [dia, mes, ano] = parts;
    return parseInt(`${ano}${mes.padStart(2, '0')}${dia.padStart(2, '0')}`);
}

// Filtrar por día (abre modal de selección)
function filtrarDelDia() {
    // Establecer la fecha actual como default en el input
    const hoyYYYYMMDD = convertirFechaAInputDate(hoyDate); // Convertir d/m/Y a YYYY-MM-DD
    document.getElementById('inputFechaModal').value = hoyYYYYMMDD;
    document.getElementById('modalSeleccionarDia').classList.remove('hidden');
}

// Cerrar modal
function cerrarModalDia() {
    document.getElementById('modalSeleccionarDia').classList.add('hidden');
}

// Aceptar fecha seleccionada
function aceptarFechaModal() {
    const inputDate = document.getElementById('inputFechaModal').value; // YYYY-MM-DD
    if (!inputDate) {
        alert('Por favor selecciona una fecha');
        return;
    }
    
    // Convertir YYYY-MM-DD a d/m/Y
    const fechaSeleccionada = convertirInputDateAFecha(inputDate);
    
    // Aplicar filtro
    filtrosActuales = { fecha: fechaSeleccionada };
    actualizarFiltrosUI();
    aplicarFiltros();
    
    // Cerrar modal
    cerrarModalDia();
}

// Agregar filtro dinámico
function agregarFiltro() {
    const tipo = document.getElementById('filterType').value;
    if (!tipo) return;
    
    const filtrosActivos = document.getElementById('filtrosActivos');
    const id = `filtro-${tipo}-${Date.now()}`;
    
    let html = `
        <div class="border border-gray-300 rounded p-3 bg-blue-50" id="${id}">
            <div class="flex justify-between items-start mb-2">
                <label class="font-semibold text-sm text-gray-700">${tipo === 'fecha' ? 'RANGO DE FECHAS' : tipo.toUpperCase()}</label>
                <button onclick="removerFiltro('${id}', '${tipo}')" class="text-red-500 hover:text-red-700 text-lg">×</button>
            </div>
    `;
    
    if (tipo === 'fecha') {
        html += `
            <div class="mb-2">
                <label class="text-xs text-gray-600">Desde:</label>
                <input type="date" class="w-full border rounded p-1 text-sm mb-2" id="fecha-desde-${id}" onchange="aplicarFiltros()">
            </div>
            <div>
                <label class="text-xs text-gray-600">Hasta:</label>
                <input type="date" class="w-full border rounded p-1 text-sm" id="fecha-hasta-${id}" onchange="aplicarFiltros()">
            </div>
        `;
    } else {
        html += `
            <select class="w-full border rounded p-1 text-sm" id="select-${id}" onchange="aplicarFiltros()">
                <option value="">Seleccione...</option>
        `;
        if (filtrosDisponibles[tipo]) {
            filtrosDisponibles[tipo].forEach(val => {
                html += `<option value="${val}">${val}</option>`;
            });
        }
        html += `</select>`;
    }
    
    html += `</div>`;
    
    filtrosActivos.insertAdjacentHTML('beforeend', html);
    document.getElementById('filterType').value = '';
}

// Remover filtro
function removerFiltro(id, tipo) {
    document.getElementById(id).remove();
    delete filtrosActuales[tipo];
    aplicarFiltros();
}

// Limpiar todos los filtros
function limpiarFiltros() {
    filtrosActuales = {};
    document.getElementById('filtrosActivos').innerHTML = '';
    aplicarFiltros();
}

// Actualizar UI de filtros
function actualizarFiltrosUI() {
    const filtrosActivos = document.getElementById('filtrosActivos');
    filtrosActivos.innerHTML = '';
    
    Object.keys(filtrosActuales).forEach(tipo => {
        const valor = filtrosActuales[tipo];
        const id = `filtro-${tipo}`;
        
        let html = `
            <div class="border border-blue-400 rounded p-3 bg-blue-50" id="${id}">
                <div class="flex justify-between items-start">
                    <div>
                        <label class="font-semibold text-sm text-gray-700">${tipo === 'fecha' ? 'FECHA' : tipo.toUpperCase()}</label>
                        <p class="text-sm text-gray-600">${valor}</p>
                    </div>
                    <button onclick="removerFiltro('${id}', '${tipo}')" class="text-red-500 hover:text-red-700 text-lg">×</button>
                </div>
            </div>
        `;
        filtrosActivos.insertAdjacentHTML('beforeend', html);
    });
}

// Aplicar filtros a la tabla
function aplicarFiltros() {
    recolectarFiltros();
    
    const filas = document.querySelectorAll('.fila-data');
    let visibles = 0;
    
    filas.forEach(fila => {
        let mostrar = true;
        const fechaFila = fila.getAttribute('data-fecha') || ''; // formato: d/m/Y
        const fechaNum = fechaANumero(fechaFila); // convertir a YYYYMMDD
        
        // Verificar filtro de rango de fechas
        if (filtrosActuales.fechaDesde) {
            const desdeNum = convertirInputDateANumero(filtrosActuales.fechaDesde);
            if (fechaNum < desdeNum) {
                mostrar = false;
            }
        }
        
        if (filtrosActuales.fechaHasta) {
            const hastaNum = convertirInputDateANumero(filtrosActuales.fechaHasta);
            if (fechaNum > hastaNum) {
                mostrar = false;
            }
        }
        
        // Verificar filtro de fecha exacta (para "Hoy")
        if (filtrosActuales.fecha) {
            if (fechaFila !== filtrosActuales.fecha) {
                mostrar = false;
            }
        }
        
        // Verificar otros filtros exactos
        Object.keys(filtrosActuales).forEach(tipo => {
            if (tipo === 'fecha' || tipo === 'fechaDesde' || tipo === 'fechaHasta') return;
            
            const valor = filtrosActuales[tipo];
            if (valor) {
                const atributo = `data-${tipo.replace('_', '-')}`;
                const filaValor = fila.getAttribute(atributo) || '';
                if (filaValor.toLowerCase() !== valor.toLowerCase()) {
                    mostrar = false;
                }
            }
        });
        
        fila.style.display = mostrar ? '' : 'none';
        if (mostrar) visibles++;
    });
    
    document.getElementById('totalCount').textContent = visibles;
}

// Convertir input date (YYYY-MM-DD) a número (YYYYMMDD)
function convertirInputDateANumero(inputDate) {
    if (!inputDate) return 0;
    // inputDate format: YYYY-MM-DD
    return parseInt(inputDate.replace(/-/g, ''));
}

// Recolectar valores de filtros dinámicos
function recolectarFiltros() {
    const selectores = document.querySelectorAll('[id^="select-filtro-"]');
    const inputsFecha = document.querySelectorAll('[id^="fecha-desde-"], [id^="fecha-hasta-"]');
    
    // Mantener filtros manuales (como fecha exacta)
    const filtrosMantenidos = {};
    if (filtrosActuales.fecha) {
        filtrosMantenidos.fecha = filtrosActuales.fecha;
    }
    
    filtrosActuales = { ...filtrosMantenidos };
    
    // Recolectar selectores dinámicos
    selectores.forEach(select => {
        const match = select.id.match(/select-filtro-(.+?)-(\d+)$/);
        const tipo = match ? match[1] : '';
        if (tipo && select.value) {
            filtrosActuales[tipo] = select.value;
        }
    });
    
    // Recolectar rango de fechas
    inputsFecha.forEach(input => {
        if (input.id.includes('fecha-desde-') && input.value) {
            filtrosActuales.fechaDesde = input.value;
        }
        if (input.id.includes('fecha-hasta-') && input.value) {
            filtrosActuales.fechaHasta = input.value;
        }
    });
}

// Convertir fecha d/m/Y a número YYYYMMDD
function convertirFechaAYYYYMMDD(fecha) {
    const parts = fecha.split('/');
    if (parts.length === 3) {
        return `${parts[2]}-${parts[1]}-${parts[0]}`;
    }
    return fecha;
}

// Convertir fecha d/m/Y a formato input date YYYY-MM-DD
function convertirFechaAInputDate(fecha) {
    const parts = fecha.split('/');
    if (parts.length === 3) {
        const [dia, mes, ano] = parts;
        return `${ano}-${mes.padStart(2, '0')}-${dia.padStart(2, '0')}`;
    }
    return fecha;
}

// Convertir input date YYYY-MM-DD a formato d/m/Y
function convertirInputDateAFecha(inputDate) {
    const parts = inputDate.split('-');
    if (parts.length === 3) {
        const [ano, mes, dia] = parts;
        return `${dia}/${mes}/${ano}`;
    }
    return inputDate;
}

// Exportar datos filtrados a Excel (ExcelJS - .xlsx real con formato de encabezado)
function exportarAExcel() {
    const filas = document.querySelectorAll('.fila-data');
    const encabezados = ['Turno', 'Fecha', 'Requerimiento', 'Solicitante', 'Negocio', 'Ambiente', 'Capa', 'Servidor', 'Estado', 'Tipo Solicitud', 'Ticket', 'Tipo Pase', 'IC', 'Cantidad', 'Tiempo Total', 'Tiempo unidad', 'Observación', 'ID', 'Registro'];

    const filasDatos = [];
    filas.forEach(fila => {
        if (fila.style.display !== 'none') {
            filasDatos.push([
                fila.getAttribute('data-turno') || '',
                fila.getAttribute('data-fecha') || '',
                fila.getAttribute('data-requerimiento') || '',
                fila.getAttribute('data-solicitante') || '',
                fila.getAttribute('data-negocio') || '',
                fila.getAttribute('data-ambiente') || '',
                fila.getAttribute('data-capa') || '',
                fila.getAttribute('data-servidor') || '',
                fila.getAttribute('data-estado') || '',
                fila.getAttribute('data-tipo-solicitud') || '',
                fila.getAttribute('data-ticket') || '',
                fila.getAttribute('data-tipo-pase') || '',
                fila.getAttribute('data-ic') || '',
                fila.getAttribute('data-cantidad') || '',
                fila.getAttribute('data-tiempo-total') || '',
                fila.getAttribute('data-tiempo-unidad') || '',
                fila.getAttribute('data-observaciones') || '',
                fila.getAttribute('data-id') || '',
                fila.getAttribute('data-registro') || ''
            ]);
        }
    });

    if (filasDatos.length === 0) {
        alert('No hay datos para exportar. Algún filtro oculta todos los requerimientos.');
        return;
    }

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Requerimientos');

    // Fila de encabezado con estilo
    const filaEncabezado = ws.addRow(encabezados);
    filaEncabezado.height = 22;
    filaEncabezado.eachCell(cell => {
        cell.font      = { bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
        cell.fill      = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1F4E79' } };
        cell.alignment = { horizontal: 'center', vertical: 'middle' };
        cell.border    = {
            top:    { style: 'thin' },
            bottom: { style: 'thin' },
            left:   { style: 'thin' },
            right:  { style: 'thin' }
        };
    });

    // Filas de datos
    filasDatos.forEach(r => ws.addRow(r));

    // Ancho de columnas
    ws.columns = encabezados.map((h, i) => {
        let maxLen = h.length;
        filasDatos.forEach(r => { if (r[i]) maxLen = Math.max(maxLen, String(r[i]).length); });
        return { width: Math.min(Math.max(maxLen + 2, 12), 40) };
    });

    // Descargar
    wb.xlsx.writeBuffer().then(buffer => {
        const blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = 'Requerimientos_' + new Date().toISOString().slice(0, 10) + '.xlsx';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
}
</script>
</body>
</html>
