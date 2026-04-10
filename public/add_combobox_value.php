<?php
/**
 * add_combobox_value.php — Agregar valor nuevo a combobox
 * Requiere autenticación de admin para campos protegidos
 * Guarda en BD local (SQLite) en lugar de CSV
 */
set_time_limit(30);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$response = ['success' => false, 'message' => ''];

// Campos que requieren permiso de admin para agregar
$fields_protected = ['requerimiento', 'negocio', 'ambiente', 'capa', 'servidor', 'estado', 'tipo_solicitud', 'tipo_pase', 'ic'];
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$password = $_POST['password'] ?? '';

if (!$field || !$value) {
    $response['message'] = 'Campo o valor inválido';
    echo json_encode($response);
    exit;
}

try {
    $db = new LocalDbAdapter();
    
    // Si es Solicitante, agregar sin admin
    if ($field === 'solicitante') {
        $existing = $db->getComboboxValues('solicitante');
        
        if (!in_array($value, $existing)) {
            $db->addComboboxValue('solicitante', $value);
            $response['success'] = true;
            $response['message'] = "Nuevo solicitante '$value' agregado correctamente.";
        } else {
            $response['message'] = "El solicitante '$value' ya existe.";
        }
        
        echo json_encode($response);
        exit;
    }
    
    // Para otros campos, verificar admin
    if (!in_array($field, $fields_protected)) {
        $response['message'] = 'Campo no válido';
        echo json_encode($response);
        exit;
    }
    
    // Verificar credenciales de admin
    $admins = json_decode(file_get_contents(__DIR__ . '/../storage/admins.json'), true);
    $adminAutenticado = false;
    
    foreach ($admins as $admin) {
        if (password_verify($password, $admin['password'])) {
            $adminAutenticado = true;
            break;
        }
    }
    
    if (!$adminAutenticado) {
        $response['message'] = 'Credenciales de administrador inválidas';
        echo json_encode($response);
        exit;
    }
    
    // Admin verificado, agregar valor a BD
    $existing = $db->getComboboxValues($field);
    
    if (!in_array($value, $existing)) {
        $db->addComboboxValue($field, $value);
        $response['success'] = true;
        $response['message'] = "Valor '$value' agregado a $field.";
        
        // Log de auditoría
        error_log("[ADMIN_COMBOBOX] Agregó '$value' a $field");
    } else {
        $response['message'] = "El valor '$value' ya existe en $field.";
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

