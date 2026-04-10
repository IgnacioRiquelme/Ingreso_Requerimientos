<?php
/**
 * Script de prueba para verificar que el mapeo de columnas sea correcto
 * Crea una entrada de prueba y verifica que se escriba en las columnas correctas
 */

require __DIR__ . '/vendor/autoload.php';
use Requerimiento\LocalFileAdapter;

$storagePath = __DIR__ . '/storage';

// Crear una fila de prueba con valores identificables
$testRow = [
    '',  // [0] A = Radicado (vacío)
    '27/01/2025',  // [1] B = Fecha (fecha de prueba)
    'TEST_REQUER',  // [2] C = Requerimiento
    'TEST_SOLICT',  // [3] D = Solicitante
    '',  // [4] E = (vacío)
    'TEST_NEGOC',  // [5] F = Negocio
    'TEST_AMBIE',  // [6] G = Ambiente
    'TEST_CAPA',  // [7] H = Capa
    'TEST_SERV',  // [8] I = Servidor
    'TEST_ESTAD',  // [9] J = Estado
    'TEST_TSOLIC',  // [10] K = Tipo Solicitud
    'REQ-TEST-001',  // [11] L = Ticket
    'TEST_TPASE',  // [12] M = Tipo de Pase
    'Si',  // [13] N = IC
    '1',  // [14] O = Cantidad
    '',  // [15] P = Tiempo Total
    '',  // [16] Q = Tiempo unidad
    'TEST_OBSERV',  // [17] R = Observaciones
    ''  // [18] S = ID
];

// Verificar que tenemos exactamente 19 elementos
if (count($testRow) !== 19) {
    die("ERROR: Se esperan 19 columnas, pero se proporcionaron " . count($testRow) . "\n");
}

echo "✅ Estructura correcta: 19 columnas (A-S)\n\n";

// Escribir la fila en el CSV
$adapter = new LocalFileAdapter($storagePath);
try {
    $adapter->appendRowToWorksheet('Requerimientos', $testRow);
    echo "✅ Fila escrita en CSV\n\n";
} catch (Exception $e) {
    die("❌ Error al escribir: " . $e->getMessage() . "\n");
}

// Leer el CSV y verificar la última línea
$csvFile = $storagePath . '/Requerimientos.csv';
if (file_exists($csvFile)) {
    $file = fopen($csvFile, 'r');
    $lastRow = null;
    while (($row = fgetcsv($file)) !== false) {
        $lastRow = $row;
    }
    fclose($file);
    
    if ($lastRow) {
        echo "Última línea escrita en CSV:\n";
        echo "Número de campos: " . count($lastRow) . "\n\n";
        
        $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S'];
        $mapping = [
            'A' => 'Radicado',
            'B' => 'Fecha',
            'C' => 'Requerimiento',
            'D' => 'Solicitante',
            'E' => '(vacío)',
            'F' => 'Negocio',
            'G' => 'Ambiente',
            'H' => 'Capa',
            'I' => 'Servidor',
            'J' => 'Estado',
            'K' => 'Tipo Solicitud',
            'L' => 'Ticket',
            'M' => 'Tipo de Pase',
            'N' => 'IC',
            'O' => 'Cantidad',
            'P' => 'Tiempo Total',
            'Q' => 'Tiempo unidad',
            'R' => 'Observaciones',
            'S' => 'ID'
        ];
        
        for ($i = 0; $i < count($lastRow); $i++) {
            $col = $columns[$i] ?? '?';
            $desc = $mapping[$col] ?? '?';
            $value = $lastRow[$i] ?? '';
            echo "[$i] $col ($desc): '$value'\n";
        }
        
        echo "\n✅ TEST COMPLETADO\n";
    }
}
?>
