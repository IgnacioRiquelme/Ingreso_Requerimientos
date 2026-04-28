<?php
namespace Requerimiento;

/**
 * LocalDbAdapter — Caché SQLite para lectura rápida
 * Sincroniza con OneDrive en background (async)
 */
class LocalDbAdapter
{
    private $dbPath;
    public $pdo;

    public function __construct()
    {
        $this->dbPath = __DIR__ . '/../storage/requerimientos.db';
        $this->pdo = new \PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        // Asegurar autocommit habilitado para SQLite
        $this->pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS requerimientos (
                id INTEGER PRIMARY KEY,
                excel_row INTEGER UNIQUE NOT NULL,
                turno TEXT,
                fecha TEXT,
                requerimiento TEXT,
                solicitante TEXT,
                negocio TEXT,
                ambiente TEXT,
                capa TEXT,
                servidor TEXT,
                estado TEXT,
                tipo_solicitud TEXT,
                numero_ticket TEXT,
                tipo_pase TEXT,
                ic TEXT,
                cantidad TEXT,
                tiempo_total TEXT,
                tiempo_unidad TEXT,
                observaciones TEXT,
                registro TEXT,
                synced_to_excel INTEGER DEFAULT 0,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');
        
        // Índice para búsquedas rápidas
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_excel_row ON requerimientos(excel_row)');
        
        // Tabla para valores de combobox dinámicos
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS combobox_values (
                id INTEGER PRIMARY KEY,
                field TEXT NOT NULL,
                value TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(field, value)
            )
        ');
        
        // Migrar tabla si tiene constraint incorrecto (value UNIQUE en lugar de UNIQUE(field,value))
        $this->migrateComboboxSchema();
        
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_field_value ON combobox_values(field, value)');
        
        // Tabla para reglas de combobox dinámicos
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS combobox_rules (
                id INTEGER PRIMARY KEY,
                requerimiento TEXT NOT NULL,
                negocio TEXT NOT NULL,
                ambiente TEXT NOT NULL,
                capa TEXT,
                servidor TEXT,
                estado TEXT,
                tipo_solicitud TEXT,
                tipo_pase TEXT,
                ic TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(requerimiento, negocio, ambiente)
            )
        ');
        
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rule_combo ON combobox_rules(requerimiento, negocio, ambiente)');
    }

    /**
     * Migrar schema de combobox_values si tiene UNIQUE(value) incorrecto
     * Recrea la tabla con UNIQUE(field, value) correctamente
     */
    private function migrateComboboxSchema(): void
    {
        // Verificar si la constraint es incorrecta (solo value, no field+value)
        $info = $this->pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='combobox_values'")->fetch(\PDO::FETCH_ASSOC);
        if (!$info) return;
        
        $sql = strtolower($info['sql']);
        // Si tiene 'value text not null unique' en lugar de 'unique(field, value)', migrar
        if (strpos($sql, 'value text not null unique') !== false || strpos($sql, 'value text  not null unique') !== false) {
            // Guardar datos actuales
            $rows = $this->pdo->query('SELECT field, value FROM combobox_values')->fetchAll(\PDO::FETCH_ASSOC);
            
            // Recrear tabla con constraint correcto
            $this->pdo->exec('DROP TABLE combobox_values');
            $this->pdo->exec('
                CREATE TABLE combobox_values (
                    id INTEGER PRIMARY KEY,
                    field TEXT NOT NULL,
                    value TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE(field, value)
                )
            ');
            
            // Reinsertar datos
            $stmt = $this->pdo->prepare('INSERT OR IGNORE INTO combobox_values (field, value) VALUES (?, ?)');
            foreach ($rows as $row) {
                $stmt->execute([trim($row['field']), trim($row['value'])]);
            }
        }
    }

    /**
     * Obtener todos los valores para un combobox (con trim())
     */
    public function getComboboxValues(string $field): array
    {
        $stmt = $this->pdo->prepare('
            SELECT value FROM combobox_values 
            WHERE field = ? 
            ORDER BY value ASC
        ');
        $stmt->execute([$field]);
        return array_map(fn($row) => trim($row['value']), $stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    /**
     * Agregar valor a combobox (con trim())
     */
    public function addComboboxValue(string $field, string $value): bool
    {
        try {
            $value = trim($value);
            if (!$value) return false;
            
            $stmt = $this->pdo->prepare('
                INSERT INTO combobox_values (field, value) VALUES (?, ?)
            ');
            $stmt->execute([$field, $value]);
            return true;
        } catch (\PDOException $e) {
            if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Eliminar valor de combobox
     */
    public function removeComboboxValue(string $field, string $value): bool
    {
        $stmt = $this->pdo->prepare('
            DELETE FROM combobox_values WHERE field = ? AND value = ?
        ');
        $stmt->execute([$field, $value]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Migrar valores de CSV a BD (una sola vez)
     */
    public function migrateCSVToDb(string $field, array $values): void
    {
        foreach ($values as $value) {
            $value = trim($value);
            if ($value) {
                $this->addComboboxValue($field, $value);
            }
        }
    }

    /**
     * Obtener todos los requerimientos desde la BD (instantáneo)
     */
    public function getAllRequerimientos(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM requerimientos 
            ORDER BY excel_row ASC
        ');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un requerimiento por ID (fila Excel)
     */
    public function getRequerimiento(int $excelRow): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM requerimientos WHERE excel_row = ?');
        $stmt->execute([$excelRow]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Insertar nuevo requerimiento (guardar desde submit.php)
     */
    public function insertRequerimiento(int $excelRow, array $data): void
    {
        $cols = ['excel_row', 'synced_to_excel'];
        $vals = [':excel_row', ':synced_to_excel'];
        $binds = [':excel_row' => $excelRow, ':synced_to_excel' => 0];

        foreach ($data as $key => $val) {
            $cols[] = $key;
            $vals[] = ':' . $key;
            $binds[':' . $key] = $val;
        }

        $sql = 'INSERT INTO requerimientos (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($binds);
    }

    /**
     * Actualizar requerimiento existente
     */
    public function updateRequerimiento(int $excelRow, array $data): void
    {
        $data['synced_to_excel'] = 0; // marcar para sincronizar
        $data['last_updated'] = date('Y-m-d H:i:s');

        $setClauses = [];
        $binds = [':excel_row' => $excelRow];
        foreach ($data as $key => $val) {
            $setClauses[] = "$key = :$key";
            $binds[":$key"] = $val;
        }

        $sql = 'UPDATE requerimientos SET ' . implode(', ', $setClauses) . ' WHERE excel_row = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($binds));
    }

    /**
     * Obtener requerimientos no sincronizados (para background sync)
     */
    public function getUnsyncedRequerimientos(): array
    {
        $stmt = $this->pdo->query('
            SELECT * FROM requerimientos 
            WHERE synced_to_excel = 0 
            ORDER BY last_updated ASC
        ');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Marcar como sincronizado
     */
    public function markAsSynced(int $excelRow): void
    {
        $stmt = $this->pdo->prepare('UPDATE requerimientos SET synced_to_excel = 1 WHERE excel_row = ?');
        $stmt->execute([$excelRow]);
    }

    /**
     * Sincronizar BD con Excel (leer datos nuevos)
     * Ejecutar después de que ExcelGraphAdapter escribe
     */
    /**
     * Convertir número de Excel a formato dd/mm/yyyy
     * Maneja tanto fechas ya formateadas como seriales de Excel
     */
    private function excelDateToString($excelDate): string
    {
        // Si es string, verificar si ya está formateado
        if (is_string($excelDate)) {
            $trimmed = trim($excelDate);
            // Si ya es formato dd/mm/yyyy, devolverlo
            if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $trimmed)) {
                return $trimmed;
            }
            // Si es fecha ISO (yyyy-mm-dd), convertir a dd/mm/yyyy
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $trimmed, $matches)) {
                return $matches[3] . '/' . $matches[2] . '/' . $matches[1];
            }
            return $trimmed;
        }
        
        // Si es número (serial de Excel)
        if (is_numeric($excelDate) && $excelDate > 0 && $excelDate < 100000) {
            $unixDate = ($excelDate - 25569) * 86400;
            return date('d/m/Y', $unixDate);
        }
        
        return (string)$excelDate;
    }

    public function syncFromExcel(array $allExcelRows): void
    {
        foreach ($allExcelRows as $rowNum => $row) {
            // Saltar filas de encabezado
            if ($rowNum < 3) continue;
            if (empty($row[0]) && empty($row[1]) && empty($row[2])) continue;

            $excelRow = $rowNum + 1;
            $data = [
                'turno'           => $row[0] ?? '',
                'fecha'           => $this->excelDateToString($row[1] ?? ''),  // Convertir fecha
                'requerimiento'   => $row[2] ?? '',
                'solicitante'     => $row[3] ?? '',
                'negocio'         => $row[4] ?? '',
                'ambiente'        => $row[5] ?? '',
                'capa'            => $row[6] ?? '',
                'servidor'        => $row[7] ?? '',
                'estado'          => $row[8] ?? '',
                'tipo_solicitud'  => $row[9] ?? '',
                'numero_ticket'   => $row[10] ?? '',
                'tipo_pase'       => $row[11] ?? '',
                'ic'              => $row[12] ?? '',
                'cantidad'        => $row[13] ?? '',
                'tiempo_total'    => $row[14] ?? '',
                'tiempo_unidad'   => $row[15] ?? '',
                'observaciones'   => $row[16] ?? '',
                'registro'        => $row[18] ?? '',
            ];

            // Upsert: actualizar si existe, insertar si no
            if ($this->getRequerimiento($excelRow)) {
                $this->updateRequerimiento($excelRow, $data);
            } else {
                $this->insertRequerimiento($excelRow, $data);
            }
        }
    }

    /**
     * Contar requerimientos en la BD
     */
    public function countRequerimientos(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM requerimientos');
        return (int)$stmt->fetchColumn();
    }

    /**
     * Limpiar BD (para reinicializar)
     */
    public function clearAll(): void
    {
        $this->pdo->exec('DELETE FROM requerimientos');
    }

    /**
     * Obtener regla de combobox dinámico
     */
    public function getComboboxRule(string $requerimiento, string $negocio, string $ambiente): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT capa, servidor, estado, tipo_solicitud, tipo_pase, ic 
            FROM combobox_rules 
            WHERE requerimiento = ? AND negocio = ? AND ambiente = ?
        ');
        $stmt->execute([$requerimiento, $negocio, $ambiente]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Agregar regla de combobox dinámico (con trim en todos los valores)
     */
    public function addComboboxRule(string $requerimiento, string $negocio, string $ambiente, array $values): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT OR REPLACE INTO combobox_rules 
                (requerimiento, negocio, ambiente, capa, servidor, estado, tipo_solicitud, tipo_pase, ic) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                trim($requerimiento),
                trim($negocio),
                trim($ambiente),
                trim($values['capa'] ?? ''),
                trim($values['servidor'] ?? ''),
                trim($values['estado'] ?? ''),
                trim($values['tipo_solicitud'] ?? ''),
                trim($values['tipo_pase'] ?? ''),
                trim($values['ic'] ?? ''),
            ]);
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Obtener todas las reglas (para sincronizar en JS)
     */
    /**
     * Obtener todas las reglas (con trim() en todos los valores)
     */
    public function getAllComboboxRules(): array
    {
        $stmt = $this->pdo->query('
            SELECT requerimiento, negocio, ambiente, capa, servidor, estado, tipo_solicitud, tipo_pase, ic 
            FROM combobox_rules 
            ORDER BY requerimiento ASC, negocio ASC, ambiente ASC
        ');
        $rules = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Hacer trim() en todos los campos para eliminar espacios
        return array_map(function($rule) {
            return array_map('trim', $rule);
        }, $rules);
    }

    /**
     * Limpiar todas las reglas
     */
    public function clearComboboxRules(): void
    {
        $this->pdo->exec('DELETE FROM combobox_rules');
    }
}
