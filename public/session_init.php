<?php
// Configuración de sesión: Persistente indefinida (no caduca)
// IMPORTANTE: Usamos un directorio de sesiones PROPIO del proyecto para evitar
// que el cron del sistema de Ubuntu/Debian (/etc/cron.d/php) borre las sesiones
// de /var/lib/php/sessions cada ~24 min ignorando gc_maxlifetime.
if (session_status() === PHP_SESSION_NONE) {
    $lifetime = 315360000; // 10 años en segundos

    // Directorio de sesiones dentro del proyecto (fuera del alcance del cron del sistema)
    // Usar realpath para obtener ruta absolutamente resolverida
    $sessionPath = realpath(__DIR__ . '/..') . '/storage/sessions';
    
    // Asegurar que el directorio existe y tiene permisos correctos
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);  // Usar session_save_path() en lugar de ini_set
    }
    
    ini_set('session.gc_maxlifetime', $lifetime);
    ini_set('session.gc_probability', 0);  // Deshabilitar garbage collection automático
    ini_set('session.gc_divisor', 1);
    ini_set('session.use_strict_mode', '1');  // Rechazar IDs de sesión desconocidas
    ini_set('session.name', 'INGRESO_REQUERIMIENTOS');  // Nombre único para la cookie
    
    session_set_cookie_params([
        'lifetime' => $lifetime,  // Cookie persiste en disco tras cerrar el navegador
        'path'     => '/',
        'domain'   => '',  // Cookie para cualquier dominio
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    
    session_start();
}
