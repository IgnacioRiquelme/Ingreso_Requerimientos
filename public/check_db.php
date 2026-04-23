<?php
/**
 * check_db.php — Ver exactamente qué hay en la tabla combobox_values
 */
require_once __DIR__ . '/session_init.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Requerimiento\LocalDbAdapter;

if (!isset($_SESSION['user'])) {
    die('No autenticado');
}

try {
    $db = new LocalDbAdapter();
    
    // Query directo a la tabla
    $stmt = $db->pdo->query('SELECT field, value, COUNT(*) as cnt FROM combobox_values GROUP BY field, value ORDER BY field, value');
    $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    echo "<h2>Contenido de combobox_values:</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Value</th><th>Count</th></tr>";
    
    foreach ($filas as $fila) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($fila['field']) . "</td>";
        echo "<td>" . htmlspecialchars($fila['value']) . "</td>";
        echo "<td>" . $fila['cnt'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Específicamente para servidor
    echo "<h3>Valores en 'servidor':</h3>";
    $stmt = $db->pdo->prepare('SELECT value FROM combobox_values WHERE field = ? ORDER BY value');
    $stmt->execute(['servidor']);
    $servidores = $stmt->fetchAll(\PDO::FETCH_COLUMN);
    echo "<pre>";
    print_r($servidores);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
