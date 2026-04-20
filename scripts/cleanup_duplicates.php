<?php
/**
 * cleanup_duplicates.php
 * Elimina registros duplicados con excel_row 259 y 260
 * (ambos son el mismo ticket REQ 2026-029558, Pase a QA, 15/04/2026)
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

$targets = [259, 260];

echo "--- Registros a eliminar ---\n";
foreach ($targets as $er) {
    $row = $pdo->query("SELECT id, excel_row, numero_ticket, requerimiento, tipo_pase, fecha FROM requerimientos WHERE excel_row = $er")->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "  excel_row={$row['excel_row']} | id_interno={$row['id']} | ticket={$row['numero_ticket']} | req={$row['requerimiento']} | pase={$row['tipo_pase']} | fecha={$row['fecha']}\n";
    } else {
        echo "  excel_row=$er → NO ENCONTRADO (ya fue eliminado o no existe)\n";
    }
}

$in      = implode(',', $targets);
$deleted = $pdo->exec("DELETE FROM requerimientos WHERE excel_row IN ($in)");
echo "\nRegistros eliminados: $deleted\n";
echo "Total en BD ahora:    " . $pdo->query('SELECT COUNT(*) FROM requerimientos')->fetchColumn() . "\n";
echo "\nListo. Si necesitas revertir, restaura el backup:\n  cp $bak $dbPath\n";
