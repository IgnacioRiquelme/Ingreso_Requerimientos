<?php
/**
 * insert_missing_records.php — Insertar registros que faltaron en la importación
 * Uso: accede desde navegador y click en botón
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$error   = '';
$success = false;
$inserted = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['confirmar'])) {
    try {
        $db = new \Requerimiento\LocalDbAdapter();
        
        // Registros que faltaron del 15/04
        $missingRecords = [
            252 => ['Mañana', '15/04/2026', 'Pase a QA', 'Sebastian Reales', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029418', 'Normal', 'Si', '1', '', '', 'Se avanza sin nuestra intervención ya que viene con IC (Confirmado con Sebastián Reale), TAG: IC, IIS', '15 abril 2026 11:57 | Creado por: Ignacio Riquelme Cisternas'],
            253 => ['Tarde', '15/04/2026', 'Pase a QA', 'Guillermo Contreras', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029560', 'Emergencia', 'No', '1', '', '', 'Ticket aplicado en QA por Gabriel Platz , TAG : IIS', '15 abril 2026 16:43 | Creado por: Ignacio Riquelme Cisternas'],
            254 => ['Tarde', '15/04/2026', 'Pase a QA', 'Andrea Riquelme', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029685', 'Emergencia', 'Si', '1', '', '', 'Se avanza sin nuestra intervención ya que fue ejecutado en QA por IC. TAG : IC , NETCORE', '15 abril 2026 16:29 | Creado por: Ignacio Riquelme Cisternas'],
            255 => ['Tarde', '15/04/2026', 'Pase a QA', 'Carlos Zuñiga', 'BCI Seguros', 'Local', 'Base de datos', 'Oracle', 'Exitoso', 'Proactivanet', 'REQ 2026-029589', 'Normal', 'Si', '1', '', '', 'Se ejecuta script de manera exitosa, se adjunta evidencia, TAG: BD, ORACLE, IC', '15 abril 2026 13:58 | Creado por: Ignacio Riquelme Cisternas'],
            256 => ['Tarde', '15/04/2026', 'Pase a QA', 'Sebastian Reales', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029558', 'Normal', 'Si', '1', '', '', 'Se avanza ticket sin nuestra intervención ya que viene con IC , esto fue conformado con Sebastián Reale , TAG: IIS, IC', '15 abril 2026 13:56 | Creado por: Ignacio Riquelme Cisternas'],
            257 => ['Tarde', '15/04/2026', 'Pase a QA', 'Sebastian Reales', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029558', 'Normal', 'Si', '1', '', '', 'Se avanza ticket sin nuestra intervención ya que viene con IC , esto fue conformado con Sebastián Reale , TAG: IIS, IC', '15 abril 2026 13:44 | Creado por: Ignacio Riquelme Cisternas'],
            258 => ['Tarde', '15/04/2026', 'Pase a QA', 'Juan Rivera', 'BCI Seguros', 'Local', 'Aplicativo', 'Otros', 'Exitoso', 'Proactivanet', 'REQ 2026-029553', 'Emergencia', 'No', '1', '', '', 'Se ejecuta lo solicitado, se valida integridad del sitio con Juan Jose Rivera TAG: GITHUB', '15 abril 2026 13:33 | Creado por: Ignacio Riquelme Cisternas'],
            259 => ['Tarde', '15/04/2026', 'Pase a QA', 'Omar Ramirez', 'BCI Seguros', 'Local', 'Base de datos', 'Oracle', 'Exitoso', 'Proactivanet', 'REQ 2026-029494', 'Normal', 'No', '1', '', '', 'Se ejecutan ambos scripts e manera exitosa, TAG: BD , Oracle', '15 abril 2026 12:59 | Creado por: Ignacio Riquelme Cisternas'],
            260 => ['Tarde', '15/04/2026', 'Pase a QA', 'Victor Carreño', 'BCI Seguros', 'Local', 'Base de datos', 'SQL SERVER', 'Exitoso', 'Proactivanet', 'REQ 2026-029436', 'Normal', 'Si', '1', '', '', 'Se ejecuta script de manera exitosa, se adjunta evidencia ,TAG : BD , SQL SERVER', '15 abril 2026 12:41 | Creado por: Ignacio Riquelme Cisternas'],
        ];
        
        foreach ($missingRecords as $excelRow => $row) {
            $data = [
                'turno'           => $row[0],
                'fecha'           => $row[1],
                'requerimiento'   => $row[2],
                'solicitante'     => $row[3],
                'negocio'         => $row[4],
                'ambiente'        => $row[5],
                'capa'            => $row[6],
                'servidor'        => $row[7],
                'estado'          => $row[8],
                'tipo_solicitud'  => $row[9],
                'numero_ticket'   => $row[10],
                'tipo_pase'       => $row[11],
                'ic'              => $row[12],
                'cantidad'        => $row[13],
                'tiempo_total'    => $row[14],
                'tiempo_unidad'   => $row[15],
                'observaciones'   => $row[16],
                'registro'        => $row[17],
            ];
            
            // Upsert: actualizar si existe
            if ($db->getRequerimiento($excelRow)) {
                $db->updateRequerimiento($excelRow, $data);
            } else {
                $db->insertRequerimiento($excelRow, $data);
            }
            $inserted++;
        }
        
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
    <title>Insertar Registros Faltantes</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-lg w-full">
    <h1 class="text-2xl font-bold text-gray-900 mb-2">➕ Insertar Registros Faltantes</h1>
    <p class="text-gray-600 text-sm mb-6">Agrega los 9 registros del 15/04 que faltaron en la importación.</p>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-800 px-4 py-3 rounded mb-4">
            ✅ <strong>Registros insertados: <?= $inserted ?></strong><br>
            La BD ahora tiene <?= (260 + $inserted) ?> registros.<br>
            <a href="requerimientos.php" class="underline font-semibold mt-2 inline-block">Ver Requerimientos</a>
        </div>
    <?php elseif ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <input type="hidden" name="confirmar" value="1">
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition">
            ➕ Insertar 9 Registros del 15/04
        </button>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
