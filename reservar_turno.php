<?php
if (session_status() === PHP_SESSION_NONE) session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

function mostrar_error($mensaje) {
    echo "<!DOCTYPE html><html lang='es'><head>
        <meta charset='UTF-8'>
        <title>Error</title>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <link rel='stylesheet' href='estilo_unificado.css'>
    </head><body>
    <div class='contenedor'>
        <h2 style='color:red;'>$mensaje</h2>
        <a href='ver_turnos_cliente.php' class='btn-principal'>Volver</a>
    </div></body></html>";
    exit;
}

function calcular_proxima_fecha($dia_nombre) {
    $dias = [
        'Lunes' => 1,
        'Martes' => 2,
        'Miércoles' => 3,
        'Jueves' => 4,
        'Viernes' => 5,
        'Sábado' => 6,
        'Domingo' => 7
    ];
    $hoy = date('N');
    $objetivo = $dias[$dia_nombre] ?? 1;
    $diferencia = ($objetivo - $hoy + 7) % 7;
    return date('Y-m-d', strtotime("+$diferencia days"));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    mostrar_error("❌ Solicitud inválida.");
}

if (!isset($_POST['turno_id']) || !is_numeric($_POST['turno_id'])) {
    mostrar_error("❌ Turno no especificado.");
}

$turno_id = intval($_POST['turno_id']);

$turno = $conexion->query("SELECT * FROM turnos_disponibles WHERE id = $turno_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();
if (!$turno) {
    mostrar_error("❌ Turno no encontrado.");
}

$dia = $turno['dia'];
$hora_inicio = $turno['hora_inicio'];
$profesor_id = $turno['profesor_id'];
$fecha_turno = calcular_proxima_fecha($dia);

// Si hoy es el mismo día del turno, usar hoy
if (date('N') == date('N', strtotime($fecha_turno))) {
    $fecha_turno = date('Y-m-d');
}

// Evitar duplicados
$ya_reservado = $conexion->query("
    SELECT id FROM reservas_clientes 
    WHERE cliente_id = $cliente_id AND turno_id = $turno_id AND fecha_reserva = '$fecha_turno'
")->fetch_assoc();

if ($ya_reservado) {
    mostrar_error("⚠️ Ya reservaste este turno.");
}

// Registrar reserva (sin descontar clases)
$conexion->query("
    INSERT INTO reservas_clientes 
    (cliente_id, turno_id, profesor_id, dia_semana, hora_inicio, fecha_reserva, gimnasio_id)
    VALUES ($cliente_id, $turno_id, $profesor_id, '$dia', '$hora_inicio', '$fecha_turno', $gimnasio_id)
");

// Verificar membresía activa y clases disponibles para registrar deuda si no tiene membresía o clases
$membresia = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
      AND fecha_vencimiento >= CURDATE()
      AND gimnasio_id = $gimnasio_id
    ORDER BY fecha_inicio DESC LIMIT 1
")->fetch_assoc();

$hoy = date('Y-m-d');
$monto_deuda_por_clase = -1000; // monto negativo por clase (ajustalo a tu valor)

if ($membresia) {
    if ($membresia['clases_disponibles'] <= 0) {
        // No tiene clases, registrar deuda
        $conexion->query("
            INSERT INTO pagos (cliente_id, metodo_pago, monto, fecha, fecha_pago, gimnasio_id)
            VALUES ($cliente_id, 'Cuenta Corriente', $monto_deuda_por_clase, '$hoy', '$hoy', $gimnasio_id)
        ");
        $_SESSION['aviso_deuda'] = true;
    }
} else {
    // No tiene membresía activa, registrar deuda
    $conexion->query("
        INSERT INTO pagos (cliente_id, metodo_pago, monto, fecha, fecha_pago, gimnasio_id)
        VALUES ($cliente_id, 'Cuenta Corriente', $monto_deuda_por_clase, '$hoy', '$hoy', $gimnasio_id)
    ");
    $_SESSION['aviso_deuda'] = true;
}

header("Location: ver_turnos_cliente.php?ok=1");
exit;
?>
