<?php
require_once __DIR__ . '/session_init.php';
require __DIR__ . '/../vendor/autoload.php';

$usersFile = __DIR__ . '/../storage/users.json';
if (!file_exists($usersFile)) file_put_contents($usersFile, json_encode([]));
$users = json_decode(file_get_contents($usersFile), true);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';
    if (!$name || !$email || !$pass) $error = 'Todos los campos son requeridos';
    else {
        // check exists
        foreach ($users as $u) if (strtolower($u['email']) === strtolower($email)) { $error = 'Usuario ya existe'; break; }
        if (!$error) {
            $users[] = ['name' => $name, 'email' => $email, 'password' => password_hash($pass, PASSWORD_DEFAULT)];
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
            $_SESSION['user'] = ['email' => $email, 'name' => $name];
            header('Location: submit.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Cuenta - Sistema de Requerimientos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }
    </style>
</head>
<body>
    <div class="w-full max-w-md">
        <!-- Card principal -->
        <div class="card rounded-2xl shadow-2xl p-8 border border-white/20">
            <!-- Header con ícono -->
            <div class="text-center mb-8">
                <div class="inline-block bg-gradient-to-br from-purple-500 to-pink-500 rounded-full p-4 mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM9 19c-4.3 0-8-1.343-8-3s3.582-3 8-3 8 1.343 8 3-3.582 3-8 3z"></path>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-800 mb-2">Crear Cuenta</h1>
                <p class="text-gray-500 text-sm">Regístrate para acceder al sistema</p>
            </div>

            <!-- Mensajes de error -->
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6 flex items-start">
                    <svg class="w-5 h-5 text-red-600 mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-red-700 text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form method="POST" class="space-y-5">
                <!-- Nombre -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Nombre Completo</label>
                    <div class="relative">
                        <input 
                            type="text" 
                            name="name" 
                            placeholder="Tu nombre"
                            required
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none transition bg-gray-50 focus:bg-white"
                        />
                        <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Email -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Correo Electrónico</label>
                    <div class="relative">
                        <input 
                            type="email" 
                            name="email" 
                            placeholder="tu@email.com"
                            required
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none transition bg-gray-50 focus:bg-white"
                        />
                        <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Contraseña -->
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Contraseña</label>
                    <div class="relative">
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="••••••••"
                            required
                            class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-purple-500 focus:ring-2 focus:ring-purple-200 outline-none transition bg-gray-50 focus:bg-white"
                        />
                        <svg class="absolute right-3 top-3 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                </div>

                <!-- Botón Crear Cuenta -->
                <button 
                    type="submit"
                    class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-semibold py-3 px-4 rounded-lg transition transform hover:scale-105 active:scale-95 shadow-lg mt-6"
                >
                    Crear Cuenta
                </button>
            </form>

            <!-- Divider -->
            <div class="flex items-center my-6">
                <div class="flex-1 border-t border-gray-300"></div>
                <span class="px-3 text-gray-500 text-xs font-medium">O</span>
                <div class="flex-1 border-t border-gray-300"></div>
            </div>

            <!-- Enlace de login -->
            <div class="text-center">
                <p class="text-gray-600 text-sm">
                    ¿Ya tienes cuenta? 
                    <a href="login.php" class="text-purple-600 hover:text-purple-700 font-semibold transition">
                        Inicia sesión
                    </a>
                </p>
            </div>
        </div>

        <!-- Footer info -->
        <div class="text-center mt-8 text-white/80 text-xs">
            <p>© 2026 Sistema de Requerimientos | Clip Tecnología</p>
        </div>
    </div>
</body>
</html>
