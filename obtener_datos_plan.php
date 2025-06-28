<?php
include 'conexion.php';

if (!isset($_GET['plan_id'])) {
    echo json_encode(['error' => 'ID de plan no especificado']);
    exit;
}

$plan_id = intval($_GET['plan_id']);
$query = $conexion->query("SELECT precio, clases AS clases_disponibles, duracion_meses FROM planes WHERE id = $plan_id");

if ($query && $query->num_rows > 0) {
    $datos = $query->fetch_assoc();
    echo json_encode($datos);
} else {
    echo json_encode(['error' => 'Plan no encontrado']);
}
