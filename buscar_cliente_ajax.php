<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!isset($_GET['query']) || empty($_GET['query'])) {
    echo json_encode([]);
    exit;
}

$query = $conexion->real_escape_string($_GET['query']);

$sql = "SELECT id, nombre, apellido, dni FROM clientes 
        WHERE gimnasio_id = $gimnasio_id 
        AND (dni LIKE '%$query%' OR nombre LIKE '%$query%' OR apellido LIKE '%$query%' OR rfid LIKE '%$query%') 
        LIMIT 10";

$resultado = $conexion->query($sql);

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = [
        "id" => $fila['id'],
        "nombre" => $fila['nombre'] . " " . $fila['apellido'] . " (" . $fila['dni'] . ")"
    ];
}

echo json_encode($clientes);
?>
