<?php
include 'conexion.php';

$cliente_id = $_POST['cliente_id'];
$producto_id = $_POST['producto_id'];
$cantidad = $_POST['cantidad'];
$metodo_pago = $_POST['metodo_pago'];
$fecha_venta = date("Y-m-d");

// Buscar el precio del producto desde cualquiera de las tablas
$query = "SELECT precio_venta FROM productos_proteccion WHERE id = $producto_id
          UNION
          SELECT precio_venta FROM productos_indumentaria WHERE id = $producto_id
          UNION
          SELECT precio_venta FROM productos_suplemento WHERE id = $producto_id";

$result = $conexion->query($query);
$row = $result->fetch_assoc();
$precio_unitario = $row['precio_venta'];
$total = $precio_unitario * $cantidad;

// Guardar la venta
$sql = "INSERT INTO ventas_productos (cliente_id, producto_id, cantidad, total, metodo_pago, fecha_venta)
        VALUES ('$cliente_id', '$producto_id', '$cantidad', '$total', '$metodo_pago', '$fecha_venta')";

if ($conexion->query($sql)) {
    echo "<script>alert('Venta registrada con éxito'); window.location.href='ventas_productos.php';</script>";
} else {
    echo "Error al registrar la venta: " . $conexion->error;
}
?>