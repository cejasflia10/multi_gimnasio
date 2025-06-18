<?php
// Evitar errores por salida previa
ob_start();

// Mostrar errores en pantalla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$host = 'shuttle.proxy.rlwy.net';
$puerto = 51676;
$usuario = 'root';
$contrasena = 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$basedatos = 'railway';

$conexion = new mysqli($host, $usuario, $contrasena, $basedatos, $puerto);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
