
<?php
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
