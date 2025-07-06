<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";
$turnos_disponibles = [];
$dias_semana = [1 => "Lunes", 2 => "Martes", 3 => "Miércoles", 4 => "Jueves", 5 => "Viernes", 6 => "Sábado"];

$cliente_id = $_SESSION['cliente_id'] ?? null;
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$cliente_apellido = $_SESSION['cliente_apellido'] ?? '';

if (!$cliente_id) {
    header("Location: login_cliente.php");
    exit;
}

$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
    AND fecha_vencimiento >= CURDATE() 
    AND clases_disponibles > 0 
    ORDER BY id DESC LIMIT 1");

if ($membresia->num_rows === 0) {
    $mensaje = "⚠️ No tenés una membresía activa o sin clases disponibles.";
    session_destroy();
    $cliente_id = null;
}

$dia_seleccionado = $_GET['dia'] ?? date('N');
$fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_seleccionado - 1) . " days"));

if ($cliente_id && $dia_seleccionado) {
    $turnos_q = $conexion->query("
        SELECT t.id, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupos_maximos,
        (SELECT COUNT(*) FROM reservas r WHERE r.turno_id = t.id AND r.fecha = '$fecha_reserva') AS usados
        FROM turnos t
        JOIN horarios h ON t.id_horario = h.id
        JOIN profesores p ON t.id_profesor = p.id
        WHERE t.dia = '" . $dias_semana[$dia_seleccionado] . "'
        ORDER BY h.hora_inicio
    ");

    while ($fila = $turnos_q->fetch_assoc()) {
        $turnos_disponibles[] = $fila;
    }
}
?>

<!DOCTYPE html>
<html>
    <script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<head>
    <link rel="stylesheet" href="estilo_unificado.css">
    <meta charset="UTF-8">
    <title>Turnos disponibles</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h2 { color: gold; text-align: center; }
        input, select, button { width: 100%; padding: 10px; font-size: 16px; margin: 10px 0; }
        .turno-container { background: #111; border: 1px solid gold; border-radius: 6px; padding: 10px; margin: 10px 0; }
        .boton-turno { background: gold; color: black; font-weight: bold; padding: 10px; border: none; border-radius: 5px; cursor: pointer; }
        .sin-turnos { text-align: center; margin-top: 30px; color: orange; }
    </style>
</head>
<body>
<div class="contenedor">
<h2>📅 Turnos disponibles</h2>

<?php if (!$cliente_id): ?>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
        <button type="submit">Ingresar</button>
    </form>
<?php else: ?>
    <p><strong>Bienvenido/a:</strong> <?= htmlspecialchars($cliente_apellido . ' ' . $cliente_nombre) ?></p>
    <form method="GET">
        <label>Seleccioná un día:</label>
        <select name="dia" onchange="this.form.submit()">
            <?php foreach ($dias_semana as $num => $nombre): ?>
                <option value="<?= $num ?>" <?= $num == $dia_seleccionado ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($turnos_disponibles)): ?>
        <p class="sin-turnos">⚠️ No hay turnos disponibles para este día.</p>
    <?php else: ?>
        <?php foreach ($turnos_disponibles as $t): ?>
            <div class="turno-container">
                <p><strong>Horario:</strong> <?= $t['hora_inicio'] ?> - <?= $t['hora_fin'] ?></p>
                <p><strong>Profesor:</strong> <?= $t['profesor'] ?></p>
                <p><strong>Cupos:</strong> <?= ($t['cupos_maximos'] - $t['usados']) ?> / <?= $t['cupos_maximos'] ?></p>
                <?php if (($t['cupos_maximos'] - $t['usados']) > 0): ?>
                    <a href="cliente_reservas.php?id_turno=<?= $t['id'] ?>" class="boton-turno">Reservar Turno</a>
                <?php else: ?>
                    <p style="color:red;">Sin cupo disponible</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="POST" action="logout_turnos.php">
        <button type="submit">Cerrar sesión</button>
    </form>
<?php endif; ?>

<?php if ($mensaje): ?><p class="sin-turnos"><?= $mensaje ?></p><?php endif; ?>
</div>
</body>
</html>
