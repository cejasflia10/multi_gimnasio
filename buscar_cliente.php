<?php
include 'conexion.php';
session_start();
$gimnasio_id = $_SESSION['gimnasio_id'];

$term = mysqli_real_escape_string($conexion, $_GET['term']);
$sql = "SELECT id, nombre, apellido, dni FROM clientes 
        WHERE (dni LIKE '%$term%' OR nombre LIKE '%$term%' OR apellido LIKE '%$term%') 
        AND gimnasio_id = $gimnasio_id 
        LIMIT 10";
$result = $conexion->query($sql);

$clientes = [];
while ($row = $result->fetch_assoc()) {
    $clientes[] = $row;
}
echo json_encode($clientes);
