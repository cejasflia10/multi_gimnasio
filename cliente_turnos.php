<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = "";
$turnos_disponibles = [];
$reservas_cliente = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);

    $cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    $cliente = $cliente_q->fetch_assoc();

    if (!$cliente) {
        $mensaje = "DNI no encontrado.";
    } else {
        $_SESSION['cliente_id'] = $cliente['id'];
} else {
    // Recuperar datos del cliente si ya está logueado
    $cliente_q = $conexion->query("SELECT * FROM clientes WHERE id = {$_SESSION['cliente_id']}");
    $cliente = $cliente_q->fetch_assoc();
}

        $turnos_q = $conexion->query("
            SELECT t.id, d.nombre AS dia_nombre, h.hora_inicio, h.hora_fin, p.apellido AS profesor
            FROM turnos t
            JOIN dias d ON t.dia = d.id
            JOIN horarios h ON t.horario_id = h.id
            JOIN profesores p ON t.profesor_id = p.id
        ");
        while ($fila = $turnos_q->fetch_assoc()) {
            $turnos_disponibles[] = $fila;
        }

        $hoy = date('Y-m-d');
        $reservas_q = $conexion->query("
            SELECT r.*, d.nombre AS dia, h.hora_inicio, h.hora_fin
            FROM reservas r
            JOIN turnos t ON r.turno_id = t.id
            JOIN dias d ON t.dia = d.id
            JOIN horarios h ON t.horario_id = h.id
            WHERE r.cliente_id = {$cliente['id']} AND fecha_reserva = '$hoy'
        ");
        while ($r = $reservas_q->fetch_assoc()) {
            $reservas_cliente[] = $r;
        }
    }
}

if (isset($_GET['reservar']) && isset($_SESSION['cliente_id'])) {
    $turno_id = intval($_GET['reservar']);
    $fecha_hoy = date('Y-m-d');

    $verif = $conexion->query("
        SELECT * FROM reservas WHERE cliente_id = {$_SESSION['cliente_id']} AND fecha_reserva = '$fecha_hoy'
    ");
    if ($verif->num_rows > 0) {
        $mensaje = "Ya tenés un turno reservado para hoy.";
    } else {
        $conexion->query("
            INSERT INTO reservas (cliente_id, turno_id, fecha_reserva)
            VALUES ({$_SESSION['cliente_id']}, $turno_id, '$fecha_hoy')
        ");
        $mensaje = "Turno reservado correctamente.";
    }

    header("Location: cliente_turnos.php");
    exit;
}

if (isset($_GET['cancelar']) && isset($_SESSION['cliente_id'])) {
    $id_cancelar = intval($_GET['cancelar']);
    $conexion->query("DELETE FROM reservas WHERE id = $id_cancelar AND cliente_id = {$_SESSION['cliente_id']}");
    $mensaje = "Turno cancelado correctamente.";
    header("Location: cliente_turnos.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Mis Turnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#000000">
    <style>
        body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        input, button, select {
            padding: 10px;
            font-size: 16px;
            margin: 10px 0;
            width: 100%;
        }
        .turno {
            background-color: #111;
            margin: 10px 0;
            padding: 10px;
            border: 1px solid gold;
        }
        a.boton {
            background-color: gold;
            color: black;
            padding: 5px 10px;
            text-decoration: none;
            display: inline-block;
            margin-top: 5px;
            font-weight: bold;
        }
    </style>
</head>
<body>
<h2>Reserva de Turnos</h2>
<?php if (!isset($_SESSION['cliente_id'])): ?>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingresar DNI" required>
        <button type="submit">Ver mis turnos</button>
    </form>
<?php else: ?>
    <p><strong>Bienvenido/a:</strong> <?= $cliente['apellido'] . ' ' . $cliente['nombre'] ?></p>
    <h3>Turnos de hoy:</h3>
    <?php if ($reservas_cliente): ?>
        <?php foreach ($reservas_cliente as $res): ?>
            <div class="turno">
                Día: <?= $res['dia'] ?><br>
                Hora: <?= $res['hora_inicio'] ?> a <?= $res['hora_fin'] ?><br>
                <a href="?cancelar=<?= $res['id'] ?>" class="boton" style="background-color:red; color:white;">Cancelar turno</a>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No tenés turnos reservados hoy.</p>
    <?php endif; ?>

    <h3>Turnos disponibles</h3>
    <?php foreach ($turnos_disponibles as $t): ?>
        <div class="turno">
            Día: <?= $t['dia_nombre'] ?><br>
            Hora: <?= $t['hora_inicio'] ?> a <?= $t['hora_fin'] ?><br>
            Profesor: <?= $t['profesor'] ?><br>
            <a href="?reservar=<?= $t['id'] ?>" class="boton">Reservar este turno</a>
        </div>
    <?php endforeach; ?>

    <form method="POST" action="logout_turnos.php">
        <button type="submit">Cerrar sesión</button>
    </form>
<?php endif; ?>
<?php if ($mensaje): ?>
    <p><strong><?= $mensaje ?></strong></p>
<?php endif; ?>
</body>
</html>
