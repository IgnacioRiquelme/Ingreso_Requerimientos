<?php
/**
 * test_add_otros.php — Prueba de inserción de "Otros"
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$db = new LocalDbAdapter();

// 1. Eliminar "Otros" si existe
echo "<h3>1. Limpiando 'Otros' de la BD...</h3>";
$stmt = $db->pdo->prepare('DELETE FROM combobox_values WHERE field = ? AND value = ?');
$stmt->execute(['servidor', 'Otros']);
echo "✓ Eliminado (si existía)\n";

// 2. Verificar que no exista
echo "<h3>2. Verificando que no existe...</h3>";
$existing = $db->getComboboxValues('servidor');
echo "Valores en servidor: " . json_encode($existing) . "\n";
echo "¿Otros está? " . (in_array('Otros', $existing) ? 'SÍ (ERROR)' : 'NO (Correcto)') . "\n";

// 3. Intentar insertar
echo "<h3>3. Insertando 'Otros'...</h3>";
$result = $db->addComboboxValue('servidor', 'Otros');
echo "Resultado de addComboboxValue: " . ($result ? 'TRUE ✓' : 'FALSE ✗') . "\n";

// 4. Verificar que esté
echo "<h3>4. Verificando que sí existe...</h3>";
$existing = $db->getComboboxValues('servidor');
echo "Valores en servidor: " . json_encode($existing) . "\n";
echo "¿Otros está? " . (in_array('Otros', $existing) ? 'SÍ (✓ Correcto)' : 'NO (ERROR)') . "\n";

// 5. Revisar error.log
echo "<h3>5. Últimas líneas de error.log:</h3>";
$logFile = ini_get('error_log');
if ($logFile && file_exists($logFile)) {
    $lines = array_slice(file($logFile), -10);
    echo "<pre>" . implode('', $lines) . "</pre>";
} else {
    echo "Error log no encontrado: $logFile\n";
}
