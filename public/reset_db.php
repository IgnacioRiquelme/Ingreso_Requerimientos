<?php
/**
 * reset_db.php - DESHABILITADO
 * Operacion deshabilitada para proteger los datos.
 * La BD SQLite es la fuente de verdad unica. No se reimporta desde Excel.
 */
require_once __DIR__ . '/session_init.php';
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}
header('Location: requerimientos.php');
exit;