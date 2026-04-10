<?php
/**
 * get_combobox_rules.php — API para obtener reglas de combobox dinámicos
 * Siempre reinicia las reglas con los valores correctos
 */
date_default_timezone_set('America/Santiago');
require __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

header('Content-Type: application/json');

try {
    $db = new LocalDbAdapter();
    
    // Limpiar reglas anteriores
    $db->clearComboboxRules();
    
    // Definir reglas con valores limpios
    $defaultRules = [
        [
            'requerimiento' => 'Pase a QA',
            'negocio' => 'BCI Seguros',
            'ambiente' => 'AS400',
            'capa' => 'Aplicativo',
            'servidor' => 'Ascerbci',
            'estado' => 'Exitoso',
            'tipo_solicitud' => 'Proactivanet',
            'tipo_pase' => 'Normal',
            'ic' => 'Si'
        ],
        [
            'requerimiento' => 'Pase a QA',
            'negocio' => 'ZENIT Seguros',
            'ambiente' => 'AS400',
            'capa' => 'Aplicativo',
            'servidor' => 'Ascerzen',
            'estado' => 'Exitoso',
            'tipo_solicitud' => 'Proactivanet',
            'tipo_pase' => 'Normal',
            'ic' => 'Si'
        ],
        [
            'requerimiento' => 'Pase a Producción',
            'negocio' => 'BCI Seguros',
            'ambiente' => 'AS400',
            'capa' => 'Aplicativo',
            'servidor' => 'Concorde',
            'estado' => 'Exitoso',
            'tipo_solicitud' => 'Proactivanet',
            'tipo_pase' => 'Normal',
            'ic' => 'Si'
        ],
        [
            'requerimiento' => 'Pase a Producción',
            'negocio' => 'ZENIT Seguros',
            'ambiente' => 'AS400',
            'capa' => 'Aplicativo',
            'servidor' => 'Breton',
            'estado' => 'Exitoso',
            'tipo_solicitud' => 'Proactivanet',
            'tipo_pase' => 'Normal',
            'ic' => 'Si'
        ]
    ];
    
    // Insertar reglas con trim() y valores exactos
    foreach ($defaultRules as $rule) {
        $db->addComboboxRule(
            trim($rule['requerimiento']),
            trim($rule['negocio']),
            trim($rule['ambiente']),
            [
                'capa' => trim($rule['capa']),
                'servidor' => trim($rule['servidor']),
                'estado' => trim($rule['estado']),
                'tipo_solicitud' => trim($rule['tipo_solicitud']),
                'tipo_pase' => trim($rule['tipo_pase']),
                'ic' => trim($rule['ic'])
            ]
        );
    }
    
    // Obtener todas las reglas insertas
    $rules = $db->getAllComboboxRules();
    
    $response = [
        'success' => true,
        'rules' => $rules,
        'defaults' => [
            'estado' => 'Exitoso',
            'tipo_solicitud' => 'Proactivanet',
            'tipo_pase' => 'Normal',
            'ic' => 'No'
        ]
    ];
    
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
