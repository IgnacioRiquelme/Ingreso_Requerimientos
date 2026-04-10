<?php
require __DIR__ . '/vendor/autoload.php';

use Requerimiento\ExcelGraphAdapter;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$config = [
    'tenant_id' => $_ENV['AZURE_TENANT_ID'] ?? null,
    'client_id' => $_ENV['AZURE_CLIENT_ID'] ?? null,
    'client_secret' => $_ENV['AZURE_CLIENT_SECRET'] ?? null,
    'site_id' => $_ENV['GRAPH_SITE_ID'] ?? null,
    // Allow using a direct share URL via GRAPH_DRIVE_URL instead of GRAPH_DRIVE_PATH
    'drive_path' => $_ENV['GRAPH_DRIVE_URL'] ?? ($_ENV['GRAPH_DRIVE_PATH'] ?? null)
];

$adapter = new ExcelGraphAdapter($config);

// Construye aquí la fila con exactamente 19 columnas (A..S)
$row = [
    'Mañana',
    date('d/m/Y'),
    'Desvinculación de Usuario',
    'Luis Adasme',
    'BCI Seguros',
    'Local',
    'As400',
    'Concorde',
    'Exitoso',
    'Proactivanet',
    'REQ 2026-XXXXX',
    'Normal',
    'No',
    '1',
    '',
    '',
    '',
    '',
    ''
];

try {
    $resp = $adapter->appendRowToWorksheet($_ENV['WORKSHEET_NAME'] ?? 'Requerimientos', $row);
    echo "Escritura ok:\n";
    print_r($resp);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
