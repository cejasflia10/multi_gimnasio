
<?php
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$busqueda = $_GET['q'] ?? '';

if (!$busqueda || !$gimnasio_id) {
    echo json_encode([]);
    exit;
}

$stmt = $conexion->prepare("SELECT id, nombre, apellido, dni, rfid FROM clientes WHERE gimnasio_id = ? AND (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ? OR rfid LIKE ?) LIMIT 10");
$param = "%" . $busqueda . "%";
$stmt->bind_param("issss", $gimnasio_id, $param, $param, $param, $param);
$stmt->execute();
$resultado = $stmt->get_result();

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = $fila;
}

echo json_encode($clientes);
?>
