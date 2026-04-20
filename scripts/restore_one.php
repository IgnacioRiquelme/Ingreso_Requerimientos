<?php
/**
 * restore_one.php
 * 1. Restaura UN registro de REQ 2026-029558 (fue borrado en exceso)
 * 2. Elimina el duplicado excel_row=265 de REQ 2026-029944
 * Uso: php scripts/restore_one.php
 */
$dbPath = __DIR__ . '/../storage/requerimientos.db';

// Buscar el backup más reciente automáticamente
$backups = glob(__DIR__ . '/../storage/requerimientos.db.bak.*');
if (empty($backups)) {
    die("ERROR: No se encontró ningún backup en storage/\n");
}
sort($backups);
$bakPath = end($backups);
echo "Usando backup: $bakPath\n\n";

$pdo    = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoBak = new PDO('sqlite:' . $bakPath);
$pdoBak->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── PASO 1: Restaurar REQ 2026-029558 (el de menor excel_row) ─────────────
echo "=== PASO 1: Restaurar REQ 2026-029558 ===\n";
$existing = $pdo->query(
    "SELECT COUNT(*) FROM requerimientos WHERE numero_ticket = 'REQ 2026-029558'"
)->fetchColumn();

if ($existing > 0) {
    echo "Ya existe en BD ($existing registro). No es necesario restaurar.\n";
} else {
    $row = $pdoBak->query(
        "SELECT * FROM requerimientos WHERE numero_ticket = 'REQ 2026-029558' ORDER BY excel_row ASC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "ADVERTENCIA: No se encontró REQ 2026-029558 en el backup.\n";
    } else {
        $cols  = array_keys($row);
        $vals  = array_map(fn($c) => ":$c", $cols);
        $stmt  = $pdo->prepare(
            'INSERT OR IGNORE INTO requerimientos (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'
        );
        $binds = [];
        foreach ($row as $k => $v) { $binds[":$k"] = $v; }
        $stmt->execute($binds);
        echo "Restaurado: excel_row={$row['excel_row']} | ticket={$row['numero_ticket']} | req={$row['requerimiento']} | fecha={$row['fecha']}\n";
    }
}

// ── PASO 2: Eliminar duplicado excel_row=265 (REQ 2026-029944) ────────────
echo "\n=== PASO 2: Eliminar duplicado excel_row=265 (REQ 2026-029944) ===\n";
$dup = $pdo->query(
    "SELECT excel_row, numero_ticket, requerimiento, tipo_pase, fecha FROM requerimientos WHERE excel_row = 265"
)->fetch(PDO::FETCH_ASSOC);

if (!$dup) {
    echo "excel_row=265 no existe en la BD. Nada que eliminar.\n";
} else {
    echo "Eliminando: excel_row={$dup['excel_row']} | ticket={$dup['numero_ticket']} | req={$dup['requerimiento']} | fecha={$dup['fecha']}\n";
    $pdo->exec("DELETE FROM requerimientos WHERE excel_row = 265");
    echo "Eliminado.\n";
}

$total = $pdo->query('SELECT COUNT(*) FROM requerimientos')->fetchColumn();
echo "\nTotal en BD ahora: $total\n";

// Insertar de vuelta (si ya existe por alguna razón, ignorar)
$cols  = array_keys($row);
$vals  = array_map(fn($c) => ":$c", $cols);
$stmt  = $pdo->prepare(
    'INSERT OR IGNORE INTO requerimientos (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')'
);
$binds = [];
foreach ($row as $k => $v) { $binds[":$k"] = $v; }
$stmt->execute($binds);

echo "Restaurado: excel_row=259 | ticket={$row['numero_ticket']} | req={$row['requerimiento']} | pase={$row['tipo_pase']} | fecha={$row['fecha']}\n";
echo "Total en BD ahora: " . $pdo->query('SELECT COUNT(*) FROM requerimientos')->fetchColumn() . "\n";

// Verificar que 260 sigue eliminado
$chk = $pdo->query("SELECT excel_row FROM requerimientos WHERE excel_row = 260")->fetch();
echo "excel_row 260 en BD: " . ($chk ? "EXISTE (inesperado)" : "NO EXISTE (correcto)") . "\n";
