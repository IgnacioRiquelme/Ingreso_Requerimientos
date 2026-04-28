<?php
/**
 * get_combobox_rules.php — API para obtener reglas de combobox dinámicos
 * Las reglas están definidas en PHP y no requieren escritura en la DB
 */
date_default_timezone_set('America/Santiago');

header('Content-Type: application/json');

// Reglas definidas en PHP (fuente de verdad, no dependen de la DB)
$defaultRules = [
    [
        'requerimiento' => 'Pase a QA',
        'negocio'       => 'BCI Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Ascerbci',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'Si'
    ],
    [
        'requerimiento' => 'Pase a QA',
        'negocio'       => 'ZENIT Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Ascerzen',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'Si'
    ],
    [
        'requerimiento' => 'Pase a Producción',
        'negocio'       => 'BCI Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Concorde',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'Si'
    ],
    [
        'requerimiento' => 'Pase a Producción',
        'negocio'       => 'ZENIT Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Breton',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'Si'
    ],
    // Reglas de 2 padres (req + negocio) — ambiente se pre-rellena como hijo
    [
        'match_fields'  => ['requerimiento', 'negocio'],
        'requerimiento' => 'Desvinculación de Usuario',
        'negocio'       => 'BCI Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Concorde',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ],
    [
        'match_fields'  => ['requerimiento', 'negocio'],
        'requerimiento' => 'Desvinculación de Usuario',
        'negocio'       => 'ZENIT Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Breton',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ],
    [
        'match_fields'  => ['requerimiento', 'negocio'],
        'requerimiento' => 'Creación de usuario',
        'negocio'       => 'BCI Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Concorde',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ],
    [
        'match_fields'  => ['requerimiento', 'negocio'],
        'requerimiento' => 'Creación de usuario',
        'negocio'       => 'ZENIT Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Breton',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ],
    // Reglas de 3 padres (req + negocio + ambiente)
    [
        'requerimiento' => 'Ejecucion de Script',
        'negocio'       => 'BCI Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Concorde',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ],
    [
        'requerimiento' => 'Ejecucion de Script',
        'negocio'       => 'ZENIT Seguros',
        'ambiente'      => 'As400',
        'capa'          => 'Aplicativo',
        'servidor'      => 'Breton',
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ]
];

echo json_encode([
    'success'  => true,
    'rules'    => $defaultRules,
    'defaults' => [
        'estado'        => 'Exitoso',
        'tipo_solicitud'=> 'Proactivanet',
        'tipo_pase'     => 'Normal',
        'ic'            => 'No'
    ]
]);
