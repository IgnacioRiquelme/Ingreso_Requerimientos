<?php
session_start();

require_once __DIR__ . '/../vendor/autoload.php';
use Requerimiento\ExcelGraphAdapter;

$adapter = new ExcelGraphAdapter();

// ── Callback de Azure (viene con ?code=) ────────────────────────────────────
if (!empty($_GET['code'])) {
    try {
        $adapter->handleCallback();
        header('Location: requerimientos.php');
        exit;
    } catch (\Exception $e) {
        $error = $e->getMessage();
    }
}

// ── Si ya hay token guardado, no se necesita volver a autenticar ─────────────
if ($adapter->hasStoredToken()) {
    header('Location: requerimientos.php');
    exit;
}

$authUrl = $adapter->getAuthorizationUrl();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Microsoft - Requerimientos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-lg p-8 max-w-md w-full">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración inicial</h1>
            <p class="text-gray-500 mt-1 text-sm">Paso único de autorización Microsoft</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <p class="text-blue-800 text-sm">
                <strong>Este paso solo es necesario una vez.</strong><br>
                El administrador de la cuenta Microsoft debe iniciar sesión para autorizar 
                que la aplicación acceda al archivo Excel en OneDrive.
                Después de esto, <strong>todos los usuarios</strong> podrán usar el sistema 
                sin autenticación adicional de Microsoft.
            </p>
        </div>

        <div class="text-center">
            <a href="<?= htmlspecialchars($authUrl) ?>" 
               class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                Autorizar con Microsoft (cuenta de ignacio)
            </a>
        </div>

        <div class="mt-6 pt-4 border-t border-gray-200">
            <p class="text-xs text-gray-500 text-center">
                Inicia sesión con <strong>ignacio.riquelme@cliptecnologia.com</strong><br>
                para que la app tenga acceso al archivo en su OneDrive.
            </p>
        </div>
    </div>
</body>
</html>
