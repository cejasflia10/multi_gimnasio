<?php
include 'conexion.php';

if (!isset($_GET['cliente_id'])) {
    echo json_encode([]);
    exit;
}

$cliente_id = intval($_GET['cliente_id']);
$query = $conexion->query("
    SELECT m.id, m.fecha_inicio, m.fecha_vencimiento, p.nombre AS plan
    FROM membresias m
    JOIN planes p ON m.plan_id = p.id
    WHERE m.cliente_id = $cliente_id AND m.activa = 1
");

$resultado = [];
while ($row = $query->fetch_assoc()) {
    $resultado[] = $row;
}

echo json_encode($resultado);
