<?php
include 'conexion.php';

$nombre = $_POST['nombre'];
$talle = $_POST['talle'];
$precio_compra = $_POST['precio_compra'];
$precio_venta = $_POST['precio_venta'];

$sql = "INSERT INTO productos_proteccion (nombre, talle, precio_compra, precio_venta)
        VALUES ('$nombre', '$talle', '$precio_compra', '$precio_venta')";

if ($conexion->query($sql) === TRUE) {
    header("Location: ventas_proteccion.php");
} else {
    echo "Error: " . $sql . "<br>" . $conexion->error;
}
?>
