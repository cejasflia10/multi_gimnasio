<?php
$host = 'containers-us-west-57.railway.app'; // Host real desde Railway
$db = 'railway'; // Base de datos real
$user = 'root';
$pass = 'hIKvNIqNeenIZNeYEuvPYczIhahGiTBR';
$port = '48029'; // Puerto desde Railway

$conexion = new mysqli($host, $user, $pass, $db, $port);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
