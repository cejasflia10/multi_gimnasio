<?php
include 'conexion.php';
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'];
$q = isset($_GET['q']) ? $conexion->real_escape_string($_GET['q']) : '';

$sql = "SELECT id, nombre, apellido, dni FROM clientes 
        WHERE gimnasio_id = ? AND (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR rfid_uid LIKE ?)
        LIMIT 10";
$stmt = $conexion->prepare($sql);
$search = "%$q%";
$stmt->bind_param("sssss", $gimnasio_id, $search, $search, $search, $search);
$stmt->execute();
$resultado = $stmt->get_result();

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = $fila;
}

echo json_encode($clientes);
?>
