<?php
include '../conexion.php'; // o ajustá según tu estructura

$busqueda = $_GET['q'] ?? '';
$sql = "SELECT id, apellido, nombre, dni, rfid_uid FROM clientes 
        WHERE dni LIKE ? OR apellido LIKE ? OR rfid_uid LIKE ?
        LIMIT 10";

$stmt = $conexion->prepare($sql);
$like = "%$busqueda%";
$stmt->bind_param("sss", $like, $like, $like);
$stmt->execute();
$resultado = $stmt->get_result();

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = [
        "id" => $fila["id"],
        "text" => "{$fila['apellido']} {$fila['nombre']} - {$fila['dni']} / {$fila['rfid_uid']}"
    ];
}

echo json_encode(["results" => $clientes]);
?>
