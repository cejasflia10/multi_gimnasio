<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$dia_hoy_en = date('l');
$nombres_dias = ['Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado','Sunday'=>'Domingo'];
$dia_hoy = $nombres_dias[$dia_hoy_en] ?? 'Lunes'; // Default
$dia_seleccionado = $_GET['dia'] ?? $dia_hoy;

// Obtener membresía activa
$membresia = $conexion->query("SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE()
    ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();

// Obtener reservas actuales (por turno_disponible_id)
$reservas = [];
$res_q = $conexion->query("SELECT turno_id FROM reservas_clientes 
    WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id 
    ");

while ($r = $res_q->fetch_assoc()) {
    $reservas[$r['turno_id']] = true;
}

include 'menu_cliente.php';

// Cargar turnos disponibles del día
$turnos = $conexion->query("
    SELECT td.*, p.nombre, p.apellido 
    FROM turnos_disponibles td
    JOIN profesores p ON td.profesor_id = p.id
    WHERE td.gimnasio_id = $gimnasio_id 
    AND LOWER(TRIM(td.dia)) = LOWER('$dia_seleccionado')
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

    <h2>📅 Turnos de: <?= htmlspecialchars($dia_seleccionado) ?></h2>

    <!-- Filtro de día -->
    <form method="GET" style="margin-bottom: 20px;">
        <label for="dia">Seleccionar día:</label>
        <select name="dia" id="dia" onchange="this.form.submit()">
            <?php
            $dias_mostrar = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
            foreach ($dias_mostrar as $dia_opcion) {
                $sel = $dia_opcion == $dia_seleccionado ? 'selected' : '';
                echo "<option value='$dia_opcion' $sel>$dia_opcion</option>";
            }
            ?>
        </select>
    </form>

    <?php if (!empty($_SESSION['aviso_deuda'])): ?>
        <div style="color: red; font-weight: bold; text-align: center; margin-bottom: 10px;">
            ⚠️ No tenés clases activas. Se generó una deuda de $1000 en cuenta corriente por esta reserva.
        </div>
        <?php unset($_SESSION['aviso_deuda']); ?>
    <?php endif; ?>

    <p>📝 Podés reservar aunque no tengas clases. Si no tenés membresía activa, se genera una deuda automática de $1000.</p>
    <p>🎫 Clases disponibles: <strong><?= $membresia['clases_disponibles'] ?? 0 ?></strong></p>

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
            <td><?= htmlspecialchars($t['apellido'] . ' ' . $t['nombre']) ?></td>
            <td>
                <?php if ($reservado): ?>
                    <form method="POST" action="cancelar_reserva.php">
                        <input type="hidden" name="turno_id" value="<?= $tid ?>">
                        <button type="submit" class="cancelar">Cancelar</button>
                    </form>
                <?php else: ?>
                    <form method="POST" action="reservar_turno.php">
                        <input type="hidden" name="turno_id" value="<?= $tid ?>">
                        <button type="submit" class="reservar">Reservar</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?>
        <p style="text-align:center;">No hay turnos disponibles para <?= htmlspecialchars($dia_seleccionado) ?>.</p>
    <?php endif; ?>

</div>
</body>
</html>
