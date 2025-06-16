<?php
include 'conexion.php';

// Consultar todos los clientes ordenados por apellido
$resultado = $conexion->query("SELECT id, apellido, nombre, dni FROM clientes ORDER BY apellido ASC");

while ($fila = $resultado->fetch_assoc()) {
    $valor = "{$fila['apellido']} {$fila['nombre']} - DNI: {$fila['dni']}";
    echo "<option value=\"$valor\">";
}
?>
