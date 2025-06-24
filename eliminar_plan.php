<?php
include 'conexion.php';
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $conexion->query("DELETE FROM planes WHERE id = $id");
}
header("Location: planes.php");
exit;