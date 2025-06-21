<?php
$conexion = new mysqli("localhost", "usuario", "clave", "gymfight");

if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}
?>
