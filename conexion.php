<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Datos de conexión iguales a los que usás en Tape
$host = 'shuttle.proxy.rlwy.net';
$puerto = 51676;
$usuario = 'root';
$contrasena = 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$basedatos = 'railway';

// Conexión
$conexion = new mysqli($host, $usuario, $contrasena, $basedatos, $puerto);
if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}

// ✅ Zona horaria para PHP y MySQL
date_default_timezone_set('America/Argentina/Buenos_Aires');
$conexion->query("SET time_zone = '-03:00'");
?>
