<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";
$turnos_disponibles = [];
$dias_semana = [1 => "Lunes", 2 => "Martes", 3 => "Miércoles", 4 => "Jueves", 5 => "Viernes", 6 => "Sábado"];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $cliente_q->fetch_assoc();

    if (!$cliente) {
        $mensaje = "DNI no encontrado.";
    } else {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_dni'] = $cliente['dni'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'];
        $_SESSION['cliente_apellido'] = $cliente['apellido'];
        header("Location: cliente_turnos.php");
        exit;
    }
}

$cliente_id = $_SESSION['cliente_id'] ?? null;
$cliente_nombre = $_SESSION['cliente_nombre'] ?? '';
$cliente_apellido = $_SESSION['cliente_apellido'] ?? '';

if ($cliente_id) {
    $membresia = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE() AND clases_disponibles > 0 ORDER BY id DESC LIMIT 1");
    if ($membresia->num_rows === 0) {
        $mensaje = "⚠️ No tenés una membresía activa o sin clases disponibles.";
        session_destroy();
        $cliente_id = null;
    }
}

$dia_seleccionado = $_GET['dia'] ?? date('N');
$fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_seleccionado - 1) . " days"));

if ($cliente_id && $dia_seleccionado) {
    $turnos_q = $conexion->query("
        SELECT t.id, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupos_maximos,
        (SELECT COUNT(*) FROM reservas r WHERE r.turno_id = t.id AND r.fecha = '$fecha_reserva') AS usados
        FROM turnos t
        JOIN horarios h ON t.horario_id = h.id
        JOIN profesores p ON t.profesor_id = p.id
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
<head>
    <meta charset="UTF-8">
    <title>Turnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        input, button, select { padding: 10px; font-size: 16px; margin: 10px 0; width: 100%; }
        .turno-container { background-color: #111; color: gold; padding: 15px; margin: 15px 0; border: 1px solid gold; border-radius: 8px; }
        .turno-container h4 { margin: 0 0 10px; font-size: 18px; }
        .turno-detalle { display: flex; justify-content: space-between; flex-wrap: wrap; font-size: 16px; }
        .turno-detalle div { flex: 1 1 45%; margin-bottom: 8px; }
        .boton-turno { background-color: gold; color: black; font-weight: bold; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; width: 100%; }
        @media (max-width: 600px) { .turno-detalle div { flex: 1 1 100%; } }
    </style>
</head>
<body>
<h2>📅 Turnos disponibles</h2>
<?php if (!$cliente_id): ?>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
        <button type="submit">Ingresar</button>
    </form>
<?php else: ?>
    <p><strong>Bienvenido/a:</strong> <?= $cliente_apellido . ' ' . $cliente_nombre ?></p>
    <form method="GET">
        <label>Seleccioná un día:</label>
        <select name="dia" onchange="this.form.submit()">
            <?php foreach ($dias_semana as $num => $nombre): ?>
                <option value="<?= $num ?>" <?= $num == $dia_seleccionado ? 'selected' : '' ?>><?= $nombre ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php foreach ($turnos_disponibles as $t): ?>
        <div class="turno-container">
            <h4>🗓️ <?= $dias_semana[$dia_seleccionado] . ' ' . date('d/m', strtotime($fecha_reserva)) ?></h4>
            <div class="turno-detalle">
                <div><strong>Horario:</strong> <?= $t['hora_inicio'] ?> - <?= $t['hora_fin'] ?></div>
                <div><strong>Profesor:</strong> <?= $t['profesor'] ?></div>
                <div><strong>Cupos:</strong> <?= ($t['cupos_maximos'] - $t['usados']) ?> / <?= $t['cupos_maximos'] ?></div>
            </div>
            <?php if (($t['cupos_maximos'] - $t['usados']) > 0): ?>
                <a href="cliente_reservas.php?id_turno=<?= $t['id'] ?>" class="boton-turno">Reservar Turno</a>
            <?php else: ?>
                <p style="color:red;">Sin cupo disponible</p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <form method="POST" action="logout_turnos.php">
        <button type="submit">Cerrar sesión</button>
    </form>
<?php endif; ?>
<?php if ($mensaje): ?><p><strong><?= $mensaje ?></strong></p><?php endif; ?>
</body>
</html>
