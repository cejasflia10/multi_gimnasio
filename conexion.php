<?php
// Mostrar errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Datos reales desde Railway (verificados con tus capturas)
$host = 'shuttle.proxy.rlwy.net';
$puerto = 51676;
$usuario = 'root';
$contrasena = 'bzkntwpED3TaihydJpFM7B6wcWtSKTf3'; // Copialo exactamente desde Railway
$basedatos = 'railway';

$conexion = new mysqli($host, $usuario, $contrasena, $basedatos, $puerto);
if ($conexion->connect_error) {
    die("❌ Error de conexión: " . $conexion->connect_error);
}
?>
