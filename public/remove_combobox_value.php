<?php
/**
 * remove_combobox_value.php — Eliminar valor de combobox
 * Requiere autenticación de admin
 * Lee/escribe de BD local (SQLite)
 */
set_time_limit(30);
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

$response = ['success' => false, 'message' => ''];

$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$password = $_POST['password'] ?? '';

if (!$field || !$value) {
    $response['message'] = 'Campo o valor inválido';
    echo json_encode($response);
    exit;
}

// No permitir eliminar de Solicitante
if ($field === 'solicitante') {
    $response['message'] = 'No se pueden eliminar solicitantes.';
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

try {
    $db = new LocalDbAdapter();
    
    // Admin verificado, eliminar valor de BD
    if ($db->removeComboboxValue($field, $value)) {
        $response['success'] = true;
        $response['message'] = "Valor '$value' eliminado de $field.";
        
        // Log de auditoría
        error_log("[ADMIN_COMBOBOX] Eliminó '$value' de $field");
    } else {
        $response['message'] = "El valor '$value' no existe en $field.";
    }
    
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

echo json_encode($response);

