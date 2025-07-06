<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($cliente_id == 0 || $gimnasio_id == 0) {
    mostrar_error("⛔ Acceso denegado.");
    exit;
}

function mostrar_error($mensaje) {
    echo "<!DOCTYPE html><html lang='es'><head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Error</title>
        <link rel='stylesheet' href='estilo_unificado.css'>
    </head><body>
    <div class='contenedor'>
        <h2 style='color: red;'>$mensaje</h2>
        <a href='ver_turnos_cliente.php' class='btn-principal'>Volver</a>
    </div>
    </body></html>";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $turno_id = intval($_POST['turno_id'] ?? 0);

    // Validar turno
    $turno = $conexion->query("SELECT * FROM turnos_profesor WHERE id = $turno_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
    if (!$turno) {
        mostrar_error("❌ Turno no encontrado.");
        exit;
    }

    $dia = $turno['dia'];
    $hora_inicio = $turno['horario_inicio'];

    // Verificar reservas actuales del mismo día
    $reservas_dia = $conexion->query("
        SELECT t.horario_inicio FROM reservas r
        JOIN turnos_profesor t ON r.turno_id = t.id
        WHERE r.cliente_id = $cliente_id AND t.dia = '$dia'
    ");

    $horarios_reservados = [];
    while ($r = $reservas_dia->fetch_assoc()) {
        $horarios_reservados[] = $r['horario_inicio'];
    }

    if (in_array($hora_inicio, $horarios_reservados)) {
        mostrar_error("⚠️ Ya reservaste este turno.");
        exit;
    }

    if (count($horarios_reservados) >= 2) {
        mostrar_error("⚠️ Ya tenés 2 turnos para ese día.");
        exit;
    }

    if (count($horarios_reservados) == 1) {
        $hora_ya = strtotime($horarios_reservados[0]);
        $hora_nueva = strtotime($hora_inicio);
        $diff = abs($hora_nueva - $hora_ya);
        if ($diff != 3600) {
            mostrar_error("⚠️ Solo se permiten 2 turnos seguidos. No se permiten separados.");
            exit;
        }
    }

    // Validar membresía
    $membresia = $conexion->query("
        SELECT * FROM membresias 
        WHERE cliente_id = $cliente_id 
        AND clases_disponibles > 0 
        AND fecha_vencimiento >= CURDATE()
        AND gimnasio_id = $gimnasio_id
        ORDER BY fecha_inicio DESC LIMIT 1
    ")->fetch_assoc();

    if (!$membresia) {
        mostrar_error("❌ No tenés clases disponibles.");
        exit;
    }

    $membresia_id = $membresia['id'];

    // Registrar reserva
    $conexion->query("
        INSERT INTO reservas (cliente_id, turno_id, fecha_reserva)
        VALUES ($cliente_id, $turno_id, CURDATE())
    ");

    // Descontar clase
    $conexion->query("
        UPDATE membresias SET clases_disponibles = clases_disponibles - 1 
        WHERE id = $membresia_id
    ");

    header("Location: ver_turnos_cliente.php?ok=1");
    exit;
}
?>
