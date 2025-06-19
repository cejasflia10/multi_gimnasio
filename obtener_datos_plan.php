
<?php
include "conexion.php";
$id = $_GET['id'] ?? 0;
$query = $conexion->query("SELECT precio, cantidad_clases, duracion_meses FROM planes WHERE id = $id");
echo json_encode($query->fetch_assoc());
?>
