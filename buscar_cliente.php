<?php
include "conexion.php";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$q = $_GET['q'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

$stmt = $conexion->prepare("SELECT id, nombre, apellido, dni FROM clientes WHERE (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ?) AND gimnasio_id = ?");
$buscar = "%$q%";
$stmt->bind_param("sssi", $buscar, $buscar, $buscar, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = [
        'id' => $row['id'],
        'text' => $row['apellido'] . ', ' . $row['nombre'] . ' - ' . $row['dni'],
        'nombre' => $row['nombre'],
        'apellido' => $row['apellido'],
        'dni' => $row['dni']
    ];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
