<?php
$codigo = $_POST['codigo'] ?? '';
header('Content-Type: application/json');

$conexion = new mysqli("localhost", "usuario", "clave", "licencias");

$sql = $conexion->prepare("SELECT estado, vencimiento FROM licencias WHERE codigo = ?");
$sql->bind_param("s", $codigo);
$sql->execute();
$result = $sql->get_result();

if ($fila = $result->fetch_assoc()) {
    $hoy = date('Y-m-d');
    if ($fila['estado'] === 'activo' && $fila['vencimiento'] >= $hoy) {
        echo json_encode(["estado" => "activo", "vencimiento" => $fila['vencimiento']]);
    } else {
        echo json_encode(["estado" => "bloqueado"]);
    }
} else {
    echo json_encode(["estado" => "bloqueado"]);
}
