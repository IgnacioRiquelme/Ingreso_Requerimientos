<?php
namespace Requerimiento;

class CsvComboboxAdapter
{
    private $storagePath;

    public function __construct(string $storagePath = null)
    {
        $this->storagePath = $storagePath ?? __DIR__ . '/../storage/data';
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0775, true);
        }
    }

    public function read(string $name): array
    {
        $file = $this->storagePath . '/' . $name . '.csv';
        if (!file_exists($file)) return [];
        $rows = [];
        if (($handle = fopen($file, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = $data[0] ?? null;
            }
            fclose($handle);
        }
        return $rows;
    }

    public function append(string $name, string $value): bool
    {
        $file = $this->storagePath . '/' . $name . '.csv';
        $handle = fopen($file, 'a');
        if ($handle === false) return false;
        fputcsv($handle, [$value]);
        fclose($handle);
        return true;
    }
}
