<?php
$host = 'mysql-railway.internal'; // Viene de MYSQLHOST
$db   = 'railway';                // Viene de MYSQLDATABASE
$user = 'root';                   // Viene de MYSQLUSER
$pass = 'hIKvNIqNeenIZNeYEuvPYczIhahGiTBR'; // Tu MYSQLPASSWORD exacto (sin espacios)
$port = '3306';                   // Viene de MYSQLPORT

$conexion = new mysqli($host, $user, $pass, $db, $port);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
