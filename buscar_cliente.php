<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$termino = $_GET['term'] ?? '';
$termino = $conexion->real_escape_string($termino);

$query = "SELECT id, dni, apellido, nombre FROM clientes 
          WHERE gimnasio_id = $gimnasio_id AND 
          (dni LIKE '%$termino%' OR apellido LIKE '%$termino%' OR nombre LIKE '%$termino%') 
          LIMIT 10";
$resultado = $conexion->query($query);

$clientes = [];
while ($row = $resultado->fetch_assoc()) {
    $clientes[] = [
        'id' => $row['id'],
        'texto' => "{$row['apellido']}, {$row['nombre']} - DNI {$row['dni']}"
    ];
}
header('Content-Type: application/json');
echo json_encode($clientes);
?>