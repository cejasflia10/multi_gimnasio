<?php
$host = 'mysql.railway.internal'; // Host real desde Railway
$db = 'railway'; // Base de datos real
$user = 'root';
$pass = 'bZwtwptDJTaiWydjpfMWTBGwcwMzSKTt';
$port = '3306'; // Puerto desde Railway

$conexion = new mysqli($host, $user, $pass, $db, $port);
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
