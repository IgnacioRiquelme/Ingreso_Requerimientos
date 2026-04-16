<?php
/**
 * debug_combobox.php — Verificar qué valores hay en la BD para cada campo
 */
session_start();
require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

try {
    $db = new LocalDbAdapter();
    
    $campos = ['solicitante', 'requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'];
    $resultado = [];
    
    foreach ($campos as $field) {
        $valores = $db->getComboboxValues($field);
        $resultado[$field] = [
            'count' => count($valores),
            'valores' => $valores
        ];
    }
    
    echo json_encode(['success' => true, 'datos' => $resultado]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
