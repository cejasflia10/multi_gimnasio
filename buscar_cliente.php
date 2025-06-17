<?php
include 'conexion.php';

$busqueda = $_GET['q'] ?? '';

$sql = "SELECT id, apellido, nombre, dni, rfid_uid 
        FROM clientes 
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
        "apellido" => $fila["apellido"],
        "nombre" => $fila["nombre"],
        "dni" => $fila["dni"],
        "rfid" => $fila["rfid_uid"]
    ];
}

header('Content-Type: application/json');
echo json_encode($clientes);
?>
