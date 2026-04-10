<?php
/**
 * export_excel.php - Exportar datos filtrados a Excel
 */

session_start();

if (!isset($_SESSION['user'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit('No autenticado');
}

$datosJSON = $_POST['datos'] ?? '';
if (!$datosJSON) {
    header('HTTP/1.1 400 Bad Request');
    exit('No hay datos');
}

$datos = json_decode($datosJSON, true);
if (!is_array($datos) || count($datos) < 2) {
    header('HTTP/1.1 400 Bad Request');
    exit('Datos inválidos');
}

$nombreArchivo = 'Requerimientos_' . date('Y-m-d_H-i-s') . '.xls';

// Headers
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Abrir output
$output = fopen('php://output', 'w');

// UTF-8 BOM
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Escribir CSV con TAB como separador (Excel lo entiende mejor)
foreach ($datos as $fila) {
    fputcsv($output, $fila, "\t");
}

fclose($output);
exit;
