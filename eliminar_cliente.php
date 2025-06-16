<?php
include 'conexion.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conexion->query("DELETE FROM clientes WHERE id = $id");
    header("Location: ver_clientes.php");
    exit();
} else {
    echo "ID de cliente no válido.";
}
?>
