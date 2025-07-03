<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

if (!isset($_GET['reserva_id'])) {
    echo "Reserva no especificada.";
    exit;
}

$reserva_id = intval($_GET['reserva_id']);

// Obtener datos del turno reservado
$datos = $conexion->query("
    SELECT r.*, t.horario_inicio, t.dia, t.id AS turno_id
    FROM reservas r
    JOIN turnos_profesor t ON r.turno_id = t.id
    WHERE r.id = $reserva_id AND r.cliente_id = $cliente_id
")->fetch_assoc();

if (!$datos) {
    echo "Reserva no encontrada.";
    exit;
}

$hora_turno = strtotime($datos['horario_inicio']);
$hora_actual = time();
$hora_dia_actual = strtotime(date('H:i:s'));

if ($hora_turno - $hora_dia_actual < 3600) {
    echo "❌ No se puede cancelar el turno con menos de 1 hora de anticipación.";
    exit;
}

// Eliminar reserva
$conexion->query("DELETE FROM reservas WHERE id = $reserva_id");

// Devolver clase a la membresía más reciente
$membresia = $conexion->query("
    SELECT id FROM membresias 
    WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id
    ORDER BY fecha_inicio DESC LIMIT 1
")->fetch_assoc();

if ($membresia) {
    $conexion->query("
        UPDATE membresias SET clases_disponibles = clases_disponibles + 1 
        WHERE id = {$membresia['id']}
    ");
}

header("Location: ver_turnos_cliente.php?cancelado=1");
exit;
?>
