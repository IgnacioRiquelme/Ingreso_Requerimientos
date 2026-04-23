<?php
/**
 * add_combobox_value.php — Agregar valor nuevo a combobox
 * Solicitante: cualquiera puede agregar
 * Otros campos: solo admin (ignacio.riquelme@cliptecnologia.com) sin necesidad de contraseña
 */
set_time_limit(30);
require_once __DIR__ . '/session_init.php';
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$response = ['success' => false, 'message' => ''];

// Leer JSON desde php://input (no desde $_POST)
$jsonData = json_decode(file_get_contents('php://input'), true);
$field = $jsonData['field'] ?? '';
$value = trim($jsonData['value'] ?? '');

// Debug: registrar qué se recibe
error_log('add_combobox_value.php: field=' . var_export($field, true) . ', value=' . var_export($value, true));

if (!$field || !$value) {
    $response['message'] = 'Campo o valor inválido';
    echo json_encode($response);
    exit;
}

try {
    $db = new LocalDbAdapter();
    
    // Si es Solicitante, agregar sin restricciones (cualquiera puede agregar)
    if ($field === 'solicitante') {
        $existing = $db->getComboboxValues('solicitante');
        
        if (!in_array($value, $existing)) {
            $db->addComboboxValue('solicitante', $value);
            $response['success'] = true;
            $response['message'] = "✓ Nuevo solicitante '$value' agregado correctamente.";
        } else {
            $response['message'] = "⚠️ El solicitante '$value' ya existe.";
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Para otros campos, verificar que el usuario sea admin
    $emailAdminEsperado = 'ignacio.riquelme@cliptecnologia.com';
    if (!isset($_SESSION['user']['email']) || strtolower($_SESSION['user']['email']) !== strtolower($emailAdminEsperado)) {
        $response['message'] = 'Acceso denegado. Solo el administrador puede agregar a este campo.';
        http_response_code(403);
        echo json_encode($response);
        exit;
    }
    
    // Admin verificado, agregar valor a BD
    $existing = $db->getComboboxValues($field);
    
    if (!in_array($value, $existing)) {
        if ($db->addComboboxValue($field, $value)) {
            $response['success'] = true;
            $response['message'] = "✓ Valor '$value' agregado a $field correctamente.";
            
            // Log de auditoría
            error_log("[ADMIN_COMBOBOX_ADD] {$_SESSION['user']['name']} agregó '$value' a $field");
        } else {
            $response['message'] = "❌ No se pudo guardar '$value' en la BD. Intenta de nuevo.";
        }
    } else {
        $response['message'] = "⚠️ El valor '$value' ya existe en $field.";
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

