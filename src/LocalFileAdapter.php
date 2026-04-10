<?php
namespace Requerimiento;

class LocalFileAdapter
{
    private $storagePath;

    public function __construct($storagePath)
    {
        $this->storagePath = $storagePath;
    }

    public function appendRowToWorksheet($name, $row)
    {
        $filePath = $this->storagePath . '/' . $name . '.csv';
        
        // Crear directorio si no existe
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        // Abrir archivo en modo append
        $file = fopen($filePath, 'a');
        if (!$file) {
            throw new \Exception("No se puede abrir el archivo: $filePath");
        }

        // Escribir fila (escapar comillas dobles)
        foreach ($row as &$field) {
            if (is_null($field)) {
                $field = '';
            }
        }

        fputcsv($file, $row);
        fclose($file);

        return true;
    }
}
