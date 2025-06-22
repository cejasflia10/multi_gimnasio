<?php
include "conexion.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$filtro = $_GET['filtro'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

if (empty($filtro)) {
    echo json_encode([]);
    exit;
}

$filtro = "%$filtro%";

$stmt = $conexion->prepare("
    SELECT id, nombre, apellido, dni, disciplina 
    FROM clientes 
    WHERE (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ?) 
      AND gimnasio_id = ?
    LIMIT 5
");
$stmt->bind_param("sssi", $filtro, $filtro, $filtro, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = $row;
}

header('Content-Type: application/json');
echo json_encode($data);
