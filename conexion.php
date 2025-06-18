<?php
$conexion = new mysqli("localhost", "usuario", "clave", "multi_gimnasio");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
?>
