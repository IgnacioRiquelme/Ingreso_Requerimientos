<?php
/**
 * remove_combobox_value.php — Eliminar valor de combobox
 * Solo el admin (ignacio.riquelme@cliptecnologia.com) puede eliminar
 * Verifica sesión sin necesidad de contraseña adicional
 */
set_time_limit(30);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$response = ['success' => false, 'message' => ''];

// Verificar que el usuario logueado sea el admin
$emailAdminEsperado = 'ignacio.riquelme@cliptecnologia.com';
if (!isset($_SESSION['user']['email']) || strtolower($_SESSION['user']['email']) !== strtolower($emailAdminEsperado)) {
    $response['message'] = 'Acceso denegado. Solo el administrador puede realizar esta acción.';
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// Leer JSON desde php://input (no desde $_POST)
$jsonData = json_decode(file_get_contents('php://input'), true);
$field = $jsonData['field'] ?? '';
$value = trim($jsonData['value'] ?? '');

// Debug: registrar qué se recibe
error_log('remove_combobox_value.php: field=' . var_export($field, true) . ', value=' . var_export($value, true));

if (!$field || !$value) {
    $response['message'] = 'Campo o valor inválido';
    echo json_encode($response);
    exit;
}

try {
    $db = new LocalDbAdapter();
    
    // Admin verificado, eliminar valor de BD
    if ($db->removeComboboxValue($field, $value)) {
        $response['success'] = true;
        $response['message'] = "✓ Valor '$value' eliminado de $field correctamente.";
        
        // Log de auditoría
        error_log("[ADMIN_COMBOBOX_REMOVE] {$_SESSION['user']['name']} eliminó '$value' de $field");
    } else {
        $response['message'] = "⚠️ El valor '$value' no existe en $field.";
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

