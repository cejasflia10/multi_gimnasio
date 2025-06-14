<?php
include 'conexion.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = $_POST['nombre'];
    $tipo = $_POST['tipo'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];

    $conexion->query("INSERT INTO suplementos (nombre, tipo, precio_compra, precio_venta, stock)
                      VALUES ('$nombre', '$tipo', '$precio_compra', '$precio_venta', '$stock')");
    header("Location: ver_suplementos.php");
}
?>

<form method="POST">
    <input type="text" name="nombre" placeholder="Nombre" required><br>
    <input type="text" name="tipo" placeholder="Tipo (Proteína, Creatina, etc.)"><br>
    <input type="number" step="0.01" name="precio_compra" placeholder="Precio compra"><br>
    <input type="number" step="0.01" name="precio_venta" placeholder="Precio venta"><br>
    <input type="number" name="stock" placeholder="Stock"><br>
    <button type="submit">Agregar</button>
</form>
