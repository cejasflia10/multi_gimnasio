<?php
$host = 'shuttle.proxy.rlwy.net';
$db = 'railway';
$user = 'root';
$pass = 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$port = '51676';

$conexion = new mysqli($host, $user, $pass, $db, $port);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
