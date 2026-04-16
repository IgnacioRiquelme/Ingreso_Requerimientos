<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
use Requerimiento\ExcelGraphAdapter;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// ── Admin-consent redirect (no lleva code) ──────────────────────────────────
if (!empty($_GET['admin_consent']) && $_GET['admin_consent'] === 'True') {
    // El admin ya aprobó. Mostrar una página de confirmación.
    ?><!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Consentimiento otorgado</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
<div class="bg-white rounded-xl shadow-lg p-8 max-w-md w-full text-center">
    <div class="text-green-500 text-5xl mb-4">✓</div>
    <h1 class="text-2xl font-bold text-gray-900 mb-2">Consentimiento otorgado</h1>
    <p class="text-gray-600 mb-6">
        El administrador ha aprobado los permisos para la aplicación.<br>
        Los usuarios ya pueden iniciar sesión con Microsoft.
    </p>
    <a href="login.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg">
        Ir al inicio de sesión
    </a>
</div>
</body></html>
<?php
    exit;
}

// ── Error de Azure ───────────────────────────────────────────────────────────
if (!empty($_GET['error'])) {
    $err = htmlspecialchars($_GET['error']);
    $desc = htmlspecialchars($_GET['error_description'] ?? '');
    die("<h2>Error de Azure: $err</h2><p>$desc</p><a href='auth.php'>Reintentar</a>");
}

// ── Flujo OAuth normal (code) ────────────────────────────────────────────────
if (empty($_GET['code'])) {
    header('Location: auth.php');
    exit;
}

$adapter = new ExcelGraphAdapter();

try {
    $adapter->handleCallback();
    header('Location: requerimientos.php');
    exit;
} catch (\Exception $e) {
    $msg = htmlspecialchars($e->getMessage());
    die("<h2>Error al obtener token</h2><p>$msg</p><a href='auth.php'>Reintentar</a>");
}
