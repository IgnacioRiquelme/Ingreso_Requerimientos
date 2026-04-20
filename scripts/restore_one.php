<?php
// Restaura UN registro desde el backup más reciente (el de excel_row menor) y elimina el otro
// Uso: php scripts/restore_one.php [excel_row_a_restaurar]
$dbPath   = __DIR__ . '/../storage/requerimientos.db';

// Buscar el backup más reciente automáticamente
$backups = glob(__DIR__ . '/../storage/requerimientos.db.bak.*');
if (empty($backups)) {
    die("ERROR: No se encontró ningún backup en storage/\n");
}
sort($backups);
$bakPath = end($backups); // el más reciente
echo "Usando backup: $bakPath\n";

$pdo    = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdoBak = new PDO('sqlite:' . $bakPath);
$pdoBak->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Buscar el excel_row de menor valor entre los duplicados del ticket REQ 2026-029558
$row = $pdoBak->query(
    "SELECT * FROM requerimientos WHERE numero_ticket = 'REQ 2026-029558' ORDER BY excel_row ASC LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    die("ERROR: No se encontró REQ 2026-029558 en el backup\n");
}

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
