<?php
include "conexion.php";
session_start();

$filtro = $_GET['filtro'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

$stmt = $conexion->prepare("
    SELECT id, nombre, apellido, dni 
    FROM clientes 
    WHERE (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR rfid_uid LIKE ?) 
      AND gimnasio_id = ?
");
$like = "%$filtro%";
$stmt->bind_param("ssssi", $like, $like, $like, $like, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
        'dni' => $row['dni']
    ];
}

echo json_encode($data);
?>
