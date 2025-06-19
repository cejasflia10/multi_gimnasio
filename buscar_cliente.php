
<?php
include "conexion.php";
$q = $_GET['q'] ?? '';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 1;

$stmt = $conexion->prepare("SELECT id, nombre, apellido, dni FROM clientes WHERE (dni LIKE ? OR nombre LIKE ? OR apellido LIKE ?) AND gimnasio_id = ?");
$q = "%$q%";
$stmt->bind_param("sssi", $q, $q, $q, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[] = ['id' => $row['id'], 'text' => $row['apellido'] . ', ' . $row['nombre'] . ' - ' . $row['dni']];
}
echo json_encode($data);
?>
