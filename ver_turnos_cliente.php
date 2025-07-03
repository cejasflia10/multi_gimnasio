<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$dia_hoy = date('l');
$nombres_dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$dia_hoy = $nombres_dias[$dia_hoy];
$hora_actual = date('H:i:s');
$fecha_hoy = date('Y-m-d');

// Obtener membresía activa
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id AND clases_disponibles > 0 AND fecha_vencimiento >= CURDATE()
    ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();
$membresia_id = $membresia['id'] ?? null;

// Procesar reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turno_id = $_POST['turno_id'];

    // Obtener turno
    $turno = $conexion->query("SELECT * FROM turnos_disponibles WHERE id = $turno_id")->fetch_assoc();
$profesor_id = $turno['profesor_id'];
    $hora_inicio = $turno['hora_inicio'];
    $dia_turno = $turno['dia'];

   $fecha_reserva = date('Y-m-d');
$conexion->query("INSERT INTO reservas_clientes 
(cliente_id, turno_id, dia_semana, hora_inicio, gimnasio_id, profesor_id, fecha_reserva)
VALUES ($cliente_id, $turno_id, '$dia_turno', '$hora_inicio', $gimnasio_id, $profesor_id, '$fecha_reserva')");

    if (isset($_POST['cancelar'])) {
        // Verificar si falta más de 1h
        $hora_turno_ts = strtotime("$hora_inicio");
        $ahora_ts = strtotime(date("H:i:s"));
        if (($hora_turno_ts - $ahora_ts) >= 3600) {
            // Devolver clase
            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles + 1 WHERE id = $membresia_id");
        }

        // Eliminar reserva
        $conexion->query("DELETE FROM reservas_clientes WHERE cliente_id = $cliente_id AND turno_id = $turno_id");
    }

    header("Location: ver_turnos_cliente.php");
    exit;
}

// Obtener reservas actuales
$reservas = [];
$res_q = $conexion->query("SELECT turno_id FROM reservas_clientes WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");
while ($r = $res_q->fetch_assoc()) {
    $reservas[$r['turno_id']] = true;
}

// Turnos del día
$turnos = $conexion->query("
    SELECT td.*, p.nombre, p.apellido FROM turnos_disponibles td
    JOIN profesores p ON td.profesor_id = p.id
    WHERE td.gimnasio_id = $gimnasio_id AND LOWER(TRIM(td.dia)) = LOWER('$dia_hoy')
    ORDER BY td.hora_inicio
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos Disponibles</title>
    <style>
        body { background: black; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; color: white; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        th { background: #222; }
        td { background: #111; }
        button { padding: 6px 12px; border-radius: 5px; font-weight: bold; }
        .reservar { background: green; color: white; }
        .cancelar { background: red; color: white; }
    </style>
</head>
<body>

<h2>📅 Turnos de Hoy: <?= $dia_hoy ?></h2>
<p>⚠️ Cada clase reservada se descuenta automáticamente. Si cancelás con al menos 1h de anticipación, se devuelve la clase.</p>
<p>🎫 Clases disponibles: <strong><?= $membresia['clases_disponibles'] ?? 0 ?></strong></p>

<table>
    <tr>
        <th>Hora</th>
        <th>Profesor</th>
        <th>Acción</th>
    </tr>
    <?php while ($t = $turnos->fetch_assoc()): 
        $tid = $t['id'];
        $reservado = isset($reservas[$tid]);
        $hora_inicio = $t['hora_inicio'];
        $hora_ts = strtotime($hora_inicio);
        $ahora_ts = strtotime($hora_actual);
    ?>
    <tr>
        <td><?= substr($t['hora_inicio'], 0, 5) ?> - <?= substr($t['hora_fin'], 0, 5) ?></td>
        <td><?= $t['apellido'] . ' ' . $t['nombre'] ?></td>
        <td>
            <form method="POST">
                <input type="hidden" name="turno_id" value="<?= $tid ?>">
                <?php if ($reservado): ?>
                    <button name="cancelar" class="cancelar">Cancelar</button>
                <?php elseif ($membresia && $membresia['clases_disponibles'] > 0): ?>
                    <button name="reservar" class="reservar">Reservar</button>
                <?php else: ?>
                    <span>Sin clases disponibles</span>
                <?php endif; ?>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>

</body>
</html>
