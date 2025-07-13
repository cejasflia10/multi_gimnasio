<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$id = intval($_GET['id'] ?? 0);

// Validar que el producto pertenezca al gimnasio
$check = $conexion->query("SELECT id FROM productos WHERE id = $id AND gimnasio_id = $gimnasio_id");
if ($check->num_rows === 0) {
    echo "<div class='contenedor'><p>❌ Producto no válido o no pertenece a este gimnasio.</p><a href='ver_productos.php'>Volver</a></div>";
    exit;
}

// Eliminar producto
$conexion->query("DELETE FROM productos WHERE id = $id AND gimnasio_id = $gimnasio_id");

header("Location: ver_productos.php");
exit;
?>
