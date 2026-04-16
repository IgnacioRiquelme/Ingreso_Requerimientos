<?php
/**
 * debug_session.php — Verificar estado de la sesión actual
 */
session_start();

echo "<h2>Estado de Sesión Actual:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user'])) {
    echo "<h3>Email en sesión:</h3>";
    echo $_SESSION['user']['email'] ?? 'NO ENCONTRADO';
    echo "<br>";
    
    echo "<h3>Comparación:</h3>";
    $emailAdmin = 'ignacio.riquelme@cliptecnologia.com';
    $emailSesion = $_SESSION['user']['email'] ?? '';
    echo "Email sesión: <code>$emailSesion</code><br>";
    echo "Email esperado: <code>$emailAdmin</code><br>";
    echo "¿Coinciden (case-insensitive)? <strong>";
    echo strtolower($emailSesion) === strtolower($emailAdmin) ? 'SÍ ✓' : 'NO ✗';
    echo "</strong>";
} else {
    echo "<p><strong>⚠️ NO HAY SESIÓN INICIADA</strong></p>";
}
