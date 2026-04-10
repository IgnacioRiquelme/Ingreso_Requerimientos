<?php
/**
 * Normalizar datos en la base de datos
 * - Trim en todos los valores
 * - Estandarizar "As400" → "AS400"
 */

date_default_timezone_set('America/Santiago');
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$db = new LocalDbAdapter();

try {
    // 1. Obtener y limpiar combobox_values
    echo "=== Limpiando combobox_values ===\n";
    $stmt = $db->pdo->query("SELECT DISTINCT value FROM combobox_values");
    $rawValues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $normalizedValues = [];
    foreach ($rawValues as $row) {
        $cleaned = trim($row['value']);
        if ($cleaned && !in_array($cleaned, $normalizedValues)) {
            $normalizedValues[] = $cleaned;
        }
    }
    
    // Limpiar tabla
    $db->pdo->exec("DELETE FROM combobox_values");
    
    // Re-insertar valores normalizados
    foreach ($normalizedValues as $value) {
        $insertStmt = $db->pdo->prepare("INSERT INTO combobox_values (value, created_at) VALUES (?, datetime('now'))");
        $insertStmt->execute([$value]);
        echo "✓ Insertado: '$value'\n";
    }
    
    // 2. Normalizar combobox_rules
    echo "\n=== Normalizando combobox_rules ===\n";
    $stmt = $db->pdo->query("SELECT * FROM combobox_rules");
    $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $db->pdo->exec("DELETE FROM combobox_rules");
    
    foreach ($rules as $rule) {
        $ambiente = strtoupper(trim($rule['ambiente'])); // "As400" → "AS400"
        
        $insertStmt = $db->pdo->prepare("
            INSERT INTO combobox_rules 
            (requerimiento, negocio, ambiente, capa, servidor, estado, tipo_solicitud, tipo_pase, ic, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        $insertStmt->execute([
            trim($rule['requerimiento']),
            trim($rule['negocio']),
            $ambiente,
            trim($rule['capa']),
            trim($rule['servidor']),
            trim($rule['estado']),
            trim($rule['tipo_solicitud']),
            trim($rule['tipo_pase']),
            trim($rule['ic'])
        ]);
        echo "✓ Normalizado: {$rule['requerimiento']} | {$rule['negocio']} | $ambiente\n";
    }
    
    echo "\n✅ Normalización completada exitosamente\n";
    echo "Total reglas normalizadas: " . count($rules) . "\n";
    echo "Total valores únicos: " . count($normalizedValues) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
