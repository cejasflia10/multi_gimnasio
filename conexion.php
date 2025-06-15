<?php
$host = 'mysql.internal';  // ⚠️ Verificá si es 'mysql.internal' o 'mysql-railway.internal' según tu Railway
$db   = 'railway';         // o el valor de MYSQLDATABASE
$user = 'root';            // o el valor de MYSQLUSER
$pass = 'hIKvNIqNeenIZNeYEuvPYczIhahGiTBR';  // Tu password de Railway
$port = '3306';            // Puerto por defecto

$conexion = new mysqli($host, $user, $pass, $db, $port);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
