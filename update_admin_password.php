<?php
/**
 * Script para actualizar contraseña de admin
 * Ejecutar una sola vez: php update_admin_password.php
 */

$nuevaContraseña = 'Nacho1507';
$admins = json_decode(file_get_contents(__DIR__ . '/storage/admins.json'), true);

// Actualizar contraseña del primer admin (Ignacio)
$admins[0]['password'] = password_hash($nuevaContraseña, PASSWORD_DEFAULT);

// Guardar
file_put_contents(
    __DIR__ . '/storage/admins.json',
    json_encode($admins, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
);

echo "✓ Contraseña actualizada correctamente a: $nuevaContraseña\n";
