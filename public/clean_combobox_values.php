<?php
/**
 * clean_combobox_values.php — Limpiar espacios de combobox_values
 * Elimina duplicados y espacios adicionales de todos los valores
 */
date_default_timezone_set('America/Santiago');
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

header('Content-Type: application/json');

try {
    $db = new LocalDbAdapter();
    
    // Obtener todos los valores actuales sin limpiar
    $stmt = $db->pdo->query('SELECT DISTINCT field, value FROM combobox_values ORDER BY field, value');
    $allValues = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Agrupar por field
    $valuesByField = [];
    foreach ($allValues as $row) {
        $field = $row['field'];
        $value = trim($row['value']);  // Limpiar espacios
        
        if ($value) {  // Solo si no está vacío
            if (!isset($valuesByField[$field])) {
                $valuesByField[$field] = [];
            }
            // Evitar duplicados
            if (!in_array($value, $valuesByField[$field])) {
                $valuesByField[$field][] = $value;
            }
        }
    }
    
    // Limpiar toda la tabla
    $db->pdo->exec('DELETE FROM combobox_values');
    
    // Reinsertar valores limpios
    $insertCount = 0;
    foreach ($valuesByField as $field => $values) {
        foreach ($values as $value) {
            try {
                $db->addComboboxValue($field, trim($value));
                $insertCount++;
            } catch (Exception $e) {
                // Ignorar duplicados
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Limpieza completada: $insertCount valores sin espacios",
        'fields_processed' => array_keys($valuesByField)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
