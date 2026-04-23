<?php
// Configuración de sesión: dura 24 horas aunque se desconecte la VPN
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 86400; // 24 horas en segundos
    ini_set('session.gc_maxlifetime', $lifetime);
    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
