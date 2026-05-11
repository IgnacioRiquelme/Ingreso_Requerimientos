<?php
// Configuración de sesión: Persistente indefinida (no caduca)
// - No caduca aunque cierre navegador (cookie se guarda en disco)
// - No se elimina al desconectar/reconectar VPN
// - Solo caduca si ejecuta logout.php explícitamente
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 315360000; // 10 años en segundos (prácticamente indefinida)
    ini_set('session.gc_maxlifetime', 315360000); // Mismo valor en servidor
    ini_set('session.gc_probability', 0); // Deshabilitar garbage collection automático
    session_set_cookie_params([
        'lifetime' => $lifetime,  // Cookie se guarda en disco y persiste tras cerrar navegador
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
