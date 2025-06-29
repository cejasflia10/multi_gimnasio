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
    // Validar membresía
    $membresia = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE() AND clases_disponibles > 0 ORDER BY id DESC LIMIT 1");
    if ($membresia->num_rows === 0) {
        $mensaje = "⚠️ No tenés una membresía activa o sin clases disponibles.";
        session_destroy();
        $cliente_id = null;
    }
}

// Obtener días
$dias = $conexion->query("SELECT * FROM dias");
$dia_seleccionado = $_GET['dia'] ?? date('N'); // 1 = Lunes

// Cargar turnos disponibles por día
if ($cliente_id && $dia_seleccionado) {
    $fecha_reserva = date('Y-m-d', strtotime("this week +" . ($dia_seleccionado - 1) . " days"));

    $turnos_q = $conexion->query("
        SELECT t.id, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupos_maximos,
        (SELECT COUNT(*) FROM reservas r WHERE r.turno_id = t.id AND r.fecha = '$fecha_reserva') AS usados
        FROM turnos t
        JOIN horarios h ON t.horario_id = h.id
        JOIN profesores p ON t.profesor_id = p.id
        WHERE t.dia_id = $dia_seleccionado
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
    <title>Mis Turnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
<h2>🗓️ Turnos disponibles</h2>

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
            <?php while ($d = $dias->fetch_assoc()): ?>
                <option value="<?= $d['id'] ?>" <?= $d['id'] == $dia_seleccionado ? 'selected' : '' ?>>
                    <?= $d['nombre'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <?php if ($turnos_disponibles): ?>
        <table border="1" cellpadding="10" style="width:100%; margin-top:20px; border-color:gold;">
            <tr style="background-color: #222;">
                <th>Horario</th>
                <th>Profesor</th>
                <th>Cupos</th>
                <th>Acción</th>
            </tr>
            <?php foreach ($turnos_disponibles as $t): ?>
                <tr>
                    <td><?= $t['hora_inicio'] ?> - <?= $t['hora_fin'] ?></td>
                    <td><?= $t['profesor'] ?></td>
                    <td><?= $t['cupos_maximos'] - $t['usados'] ?> / <?= $t['cupos_maximos'] ?></td>
                    <td>
                        <?php if (($t['cupos_maximos'] - $t['usados']) > 0): ?>
                            <a class="boton" href="cliente_reservas.php?id_turno=<?= $t['id'] ?>">Reservar</a>
                        <?php else: ?>
                            <span style="color:red;">Sin cupo</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No hay turnos disponibles para este día.</p>
    <?php endif; ?>

    <form method="POST" action="logout_turnos.php">
        <button type="submit">Cerrar sesión</button>
    </form>
<?php endif; ?>

<?php if ($mensaje): ?>
    <p style="color: yellow;"><?= $mensaje ?></p>
<?php endif; ?>
</body>
</html>
