<?php
include 'conexion.php';
include 'menu_horizontal.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $talle = $_POST['talle'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];

    $conexion->query("INSERT INTO indumentaria (nombre, talle, precio_compra, precio_venta, stock)
                      VALUES ('$nombre', '$talle', '$precio_compra', '$precio_venta', '$stock')");
    header("Location: ver_indumentaria.php");
}
?>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre" required><br>
    <input type="text" name="talle" placeholder="Talle"><br>
    <input type="number" step="0.01" name="precio_compra" placeholder="Precio compra"><br>
    <input type="number" step="0.01" name="precio_venta" placeholder="Precio venta"><br>
    <input type="number" name="stock" placeholder="Stock"><br>
    <button type="submit">Agregar</button>
</form>
