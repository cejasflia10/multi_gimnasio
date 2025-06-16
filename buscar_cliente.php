<?php
include 'conexion.php'; // Asegurate de que esta ruta sea correcta

$busqueda = $_GET['q'] ?? '';

$sql = "SELECT id, apellido, nombre, dni, rfid_uid FROM clientes 
        WHERE apellido LIKE ? 
        ORDER BY apellido ASC 
        LIMIT 10";

$stmt = $conexion->prepare($sql);
$like = "%$busqueda%";
$stmt->bind_param("s", $like);
$stmt->execute();
$resultado = $stmt->get_result();

$clientes = [];
while ($fila = $resultado->fetch_assoc()) {
    $clientes[] = [
        "id" => $fila["id"],
        "text" => "{$fila['apellido']} {$fila['nombre']} - DNI: {$fila['dni']} - RFID: {$fila['rfid_uid']}"
    ];
}

echo json_encode(["results" => $clientes]);
?>
git add nueva_membresia.php
