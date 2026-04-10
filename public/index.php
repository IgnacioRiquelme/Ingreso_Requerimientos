<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Ingreso Requerimientos</title></head>
<body>
<h2>Bienvenido, <?=htmlspecialchars($user['name'])?></h2>
<p><a href="submit.php">Ingresar nuevo requerimiento</a> | <a href="logout.php">Cerrar sesión</a></p>

<p>Ejecuta desde terminal para probar: <code>php -S localhost:8081 -t public</code></p>

</body>
</html>
