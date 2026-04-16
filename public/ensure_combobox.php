<?php
/**
 * ensure_combobox.php — Asegurar que los combobox existan en BD
 * Ejecutar al iniciar la app si están vacíos
 * Accesible via GET para debug: /ensure_combobox.php?debug=1
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

function ensureComboboxValuesInDB() {
    try {
        $db = new LocalDbAdapter();
        $storagePath = __DIR__ . '/../storage';
        
        $campos = [
            'solicitante'    => 'tipos_solicitante.csv',
            'requerimiento'  => 'tipos_requerimientos.csv',
            'negocio'        => 'tipos_negocios.csv',
            'ambiente'       => 'tipos_ambientes.csv',
            'capa'           => 'tipos_capa.csv',
            'servidor'       => 'tipos_servidor.csv',
            'estado'         => 'tipos_estado.csv',
            'tipo_solicitud' => 'tipos_solicitud.csv',
            'tipo_pase'      => 'tipos_pase.csv',
            'ic'             => 'tipos_ic.csv',
        ];
        
        $resultados = [];
        
        foreach ($campos as $field => $archivo) {
            $existentes = $db->getComboboxValues($field);
            $ruta = $storagePath . '/' . $archivo;
            
            if (count($existentes) === 0 && file_exists($ruta)) {
                // Leer CSV y llenar BD
                $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $insertados = 0;
                
                foreach ($lineas as $linea) {
                    $linea = trim($linea);
                    if ($linea && $db->addComboboxValue($field, $linea)) {
                        $insertados++;
                    }
                }
                
                $resultados[$field] = [
                    'status' => 'inicializado',
                    'insertados' => $insertados
                ];
            } else {
                $resultados[$field] = [
                    'status' => 'ok',
                    'valores' => count($existentes)
                ];
            }
        }
        
        return ['success' => true, 'resultados' => $resultados];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// Ejecutar silenciosamente (solo cuando se require, no devuelve nada)
ensureComboboxValuesInDB();

// Solo responder JSON si viene via GET con ?debug=1
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['debug'])) {
    header('Content-Type: application/json');
    echo json_encode(ensureComboboxValuesInDB());
}

