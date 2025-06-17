<?php
include 'conexion.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $conexion->query("DELETE FROM gimnasios WHERE id = $id");
}
header("Location: gimnasios.php");
exit;
?>
