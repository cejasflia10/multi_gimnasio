<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$dia_hoy = date('l');
$nombres_dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$dia_hoy = $nombres_dias[$dia_hoy];
$hora_actual = date('H:i:s');
$fecha_hoy = date('Y-m-d');

// Obtener membresía activa (solo informativa)
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE()
    ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();
$membresia_id = $membresia['id'] ?? null;

// Obtener reservas actuales
$reservas = [];
$res_q = $conexion->query("SELECT turno_id FROM reservas_clientes WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");
while ($r = $res_q->fetch_assoc()) {
    $reservas[$r['turno_id']] = true;
}

// Procesar acción antes de enviar cualquier contenido
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $turno_id = intval($_POST['turno_id']);
    $turno = $conexion->query("SELECT * FROM turnos_disponibles WHERE id = $turno_id")->fetch_assoc();
    $profesor_id = $turno['profesor_id'];
    $hora_inicio = $turno['hora_inicio'];
    $dia_turno = $turno['dia'];
    $fecha_reserva = date('Y-m-d');

    if (isset($_POST['cancelar'])) {
        $conexion->query("DELETE FROM reservas_clientes WHERE cliente_id = $cliente_id AND turno_id = $turno_id");
    } elseif (!isset($reservas[$turno_id])) {
        // Registrar reserva
        $conexion->query("INSERT INTO reservas_clientes 
            (cliente_id, turno_id, dia_semana, hora_inicio, gimnasio_id, profesor_id, fecha_reserva)
            VALUES ($cliente_id, $turno_id, '$dia_turno', '$hora_inicio', $gimnasio_id, $profesor_id, '$fecha_reserva')");

        // Si no tiene membresía o clases, registrar deuda
        if (!$membresia_id || ($membresia['clases_disponibles'] ?? 0) <= 0) {
            $monto = -1000;
            $fecha = date('Y-m-d');
            $conexion->query("INSERT INTO pagos (cliente_id, metodo_pago, monto, fecha, fecha_pago, gimnasio_id)
                VALUES ($cliente_id, 'Cuenta Corriente', $monto, '$fecha', '$fecha', $gimnasio_id)");
            $_SESSION['aviso_deuda'] = true;
        }
    }

    header("Location: ver_turnos_cliente.php");
    exit;
}

// Después de haber procesado el POST, ahora sí se puede incluir contenido
include 'menu_cliente.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

<h2>📅 Turnos de Hoy: <?= $dia_hoy ?></h2>

<?php if (!empty($_SESSION['aviso_deuda'])): ?>
    <div style="color: red; font-weight: bold; text-align: center; margin-bottom: 10px;">
        ⚠️ No tenés clases activas. Se generó una deuda de $1000 en cuenta corriente por esta reserva.
    </div>
    <?php unset($_SESSION['aviso_deuda']); ?>
<?php endif; ?>

<p>📝 Podés reservar aunque no tengas clases. Si no tenés membresía activa, se genera una deuda automática de $1000.</p>
<p>🎫 Clases disponibles (solo informativo): <strong><?= $membresia['clases_disponibles'] ?? 0 ?></strong></p>

<?php if ($turnos->num_rows > 0): ?>
<table>
    <tr>
        <th>Hora</th>
        <th>Profesor</th>
        <th>Acción</th>
    </tr>
    <?php while ($t = $turnos->fetch_assoc()):
        $tid = $t['id'];
        $reservado = isset($reservas[$tid]);
    ?>
    <tr>
        <td><?= substr($t['hora_inicio'], 0, 5) ?> - <?= substr($t['hora_fin'], 0, 5) ?></td>
        <td><?= $t['apellido'] . ' ' . $t['nombre'] ?></td>
        <td>
            <form method="POST">
                <input type="hidden" name="turno_id" value="<?= $tid ?>">
                <?php if ($reservado): ?>
                    <button name="cancelar" class="cancelar">Cancelar</button>
                <?php else: ?>
                    <button name="reservar" class="reservar">Reservar</button>
                <?php endif; ?>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
    <p style="text-align:center;">No hay turnos disponibles para hoy.</p>
<?php endif; ?>

</div>
</body>
</html>
