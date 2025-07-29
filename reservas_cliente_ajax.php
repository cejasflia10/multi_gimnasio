<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($gimnasio_id == 0) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');

$stmt = $conexion->prepare("
    SELECT rc.fecha_reserva, rc.hora_inicio, rc.dia_semana,
           c.apellido AS cliente_apellido, c.nombre AS cliente_nombre,
           p.apellido AS profesor_apellido, p.nombre AS profesor_nombre
    FROM reservas_clientes rc
    JOIN clientes c ON rc.cliente_id = c.id
    JOIN profesores p ON rc.profesor_id = p.id
    WHERE rc.fecha_reserva = ? AND rc.gimnasio_id = ?
    ORDER BY rc.hora_inicio, c.apellido
");
$stmt->bind_param("si", $fecha, $gimnasio_id);
$stmt->execute();
$result = $stmt->get_result();

$reservas = [];
while ($row = $result->fetch_assoc()) {
    $reservas[] = $row;
}

header('Content-Type: application/json');
echo json_encode($reservas);
