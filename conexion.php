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
?>
