<?php
// Configuración de sesión: Persistente indefinida (no caduca)
// IMPORTANTE: Usamos un directorio de sesiones PROPIO del proyecto para evitar
// que el cron del sistema de Ubuntu/Debian (/etc/cron.d/php) borre las sesiones
// de /var/lib/php/sessions cada ~24 min ignorando gc_maxlifetime.
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 315360000; // 10 años en segundos

    // Directorio de sesiones dentro del proyecto (fuera del alcance del cron del sistema)
    $sessionPath = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionPath)) {
        mkdir($sessionPath, 0700, true);
    }

    ini_set('session.save_path', $sessionPath);
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.gc_probability', 0);  // Deshabilitar garbage collection automático
    ini_set('session.gc_divisor', 1);
    session_set_cookie_params([
        'lifetime' => $lifetime,  // Cookie persiste en disco tras cerrar el navegador
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}
