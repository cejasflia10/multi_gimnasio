<?php
include 'conexion.php';
$id = $_GET['id'];
mysqli_query($conexion, "DELETE FROM disciplinas WHERE id = $id");
header("Location: disciplinas.php");
?>
