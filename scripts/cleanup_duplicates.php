<?php
/**
 * cleanup_duplicates.php
 * Elimina el registro DUPLICADO de REQ 2026-029558:
 * conserva el de menor excel_row, borra el de mayor excel_row.
 *
 * Uso: php scripts/cleanup_duplicates.php
 */

$dbPath = __DIR__ . '/../storage/requerimientos.db';

if (!file_exists($dbPath)) {
    die("ERROR: No se encontró la BD en: $dbPath\n");
}

$bak = $dbPath . '.bak.' . date('YmdHis');
if (!copy($dbPath, $bak)) {
    die("ERROR: No se pudo crear el backup en: $bak\n");
}
echo "Backup creado: $bak\n\n";

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Buscar todos los duplicados del ticket
$rows = $pdo->query(
    "SELECT excel_row, id, numero_ticket, requerimiento, tipo_pase, fecha
     FROM requerimientos
     WHERE numero_ticket = 'REQ 2026-029558'
     ORDER BY excel_row ASC"
)->fetchAll(PDO::FETCH_ASSOC);

echo count($rows) . " registro(s) encontrado(s) para REQ 2026-029558:\n";
foreach ($rows as $r) {
    echo "  excel_row={$r['excel_row']} | id_interno={$r['id']} | req={$r['requerimiento']} | pase={$r['tipo_pase']} | fecha={$r['fecha']}\n";
}

if (count($rows) <= 1) {
    echo "\nNada que limpiar — ya hay 1 o ningún registro.\n";
    exit(0);
}

// Conservar solo el de menor excel_row, borrar el resto
$keepRow   = $rows[0]['excel_row'];
$deleteRows = array_slice($rows, 1);
$deleteIds  = implode(',', array_column($deleteRows, 'excel_row'));

echo "\nConservando excel_row=$keepRow. Eliminando excel_row(s): $deleteIds\n";
$deleted = $pdo->exec("DELETE FROM requerimientos WHERE excel_row IN ($deleteIds)");
echo "Registros eliminados: $deleted\n";
echo "Total en BD ahora:    " . $pdo->query('SELECT COUNT(*) FROM requerimientos')->fetchColumn() . "\n";
echo "\nListo. Si necesitas revertir:\n  cp $bak $dbPath\n";
