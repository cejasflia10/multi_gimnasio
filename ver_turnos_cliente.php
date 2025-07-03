<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$horas = [];
for ($h = 8; $h < 23; $h++) {
    $hora_inicio = str_pad($h, 2, '0', STR_PAD_LEFT) . ":00:00";
    $hora_fin = str_pad($h + 1, 2, '0', STR_PAD_LEFT) . ":00:00";
    $horas[] = ['inicio' => $hora_inicio, 'fin' => $hora_fin];
}

// Procesar reserva y cancelación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia = $_POST['dia'];
    $hora = $_POST['hora'];

    if (isset($_POST['reservar'])) {
        // Verificar cupo y que no tenga más de 2 reservas ese día
        $reservas_dia = $conexion->query("SELECT COUNT(*) AS total FROM reservas_clientes
            WHERE cliente_id = $cliente_id AND dia_semana = '$dia' AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'];

        $cupo_actual = $conexion->query("SELECT COUNT(*) AS total FROM reservas_clientes
            WHERE dia_semana = '$dia' AND hora_inicio = '$hora' AND gimnasio_id = $gimnasio_id")->fetch_assoc()['total'];

        if ($reservas_dia < 2 && $cupo_actual < 15) {
            $profesorQ = $conexion->query("SELECT profesor_id FROM turnos_profesor 
                WHERE dia = '$dia' AND hora_inicio = '$hora' AND gimnasio_id = $gimnasio_id");
            $profesor = $profesorQ->fetch_assoc();
            if ($profesor) {
                $conexion->query("INSERT INTO reservas_clientes
                    (cliente_id, profesor_id, dia_semana, hora_inicio, fecha_reserva, gimnasio_id)
                    VALUES ($cliente_id, {$profesor['profesor_id']}, '$dia', '$hora', CURDATE(), $gimnasio_id)");
            }
        }
    }

    if (isset($_POST['cancelar'])) {
        if ($hora > date("H:i:s", strtotime('+1 hour'))) {
            $conexion->query("DELETE FROM reservas_clientes 
                WHERE cliente_id = $cliente_id AND dia_semana = '$dia' 
                AND hora_inicio = '$hora' AND gimnasio_id = $gimnasio_id");
        }
    }

    header("Location: ver_turnos_cliente.php");
    exit;
}

// Obtener turnos del gimnasio
$turnosQ = $conexion->query("
    SELECT tp.dia, tp.hora_inicio, tp.hora_fin, p.nombre, p.apellido, tp.profesor_id
    FROM turnos_profesor tp
    JOIN profesores p ON tp.profesor_id = p.id
    WHERE tp.gimnasio_id = $gimnasio_id
");

$turnos = [];
while ($t = $turnosQ->fetch_assoc()) {
    $hora_key = $t['hora_inicio'];
    $turnos[$t['dia']][$hora_key] = $t['apellido'] . ' ' . $t['nombre'];
}

// Obtener reservas del cliente
$resQ = $conexion->query("
    SELECT dia_semana, hora_inicio FROM reservas_clientes
    WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id
");

$reservas = [];
while ($r = $resQ->fetch_assoc()) {
    $reservas[$r['dia_semana']][$r['hora_inicio']] = true;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Turnos Semanales</title>
    <style>
        body { background: black; color: gold; font-family: Arial; padding: 20px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid gold; padding: 8px; text-align: center; vertical-align: middle; }
        th { background: #222; }
        td { background: #111; color: white; }
        button { padding: 5px 10px; border: none; border-radius: 4px; }
    </style>
</head>
<body>

<h2>📅 Turnos Semanales</h2>

<table>
    <tr>
        <th>Horario</th>
        <?php foreach ($dias as $dia): ?>
            <th><?= $dia ?></th>
        <?php endforeach; ?>
    </tr>
    <?php foreach ($horas as $h): ?>
        <tr>
            <td><?= substr($h['inicio'], 0, 5) ?> - <?= substr($h['fin'], 0, 5) ?></td>
            <?php foreach ($dias as $dia): ?>
                <td>
                    <?php
                    $profesor = $turnos[$dia][$h['inicio']] ?? null;
                    $reservado = $reservas[$dia][$h['inicio']] ?? false;
                    $cupo_actual = $conexion->query("
                        SELECT COUNT(*) as total FROM reservas_clientes
                        WHERE dia_semana = '$dia' AND hora_inicio = '{$h['inicio']}' AND gimnasio_id = $gimnasio_id
                    ")->fetch_assoc()['total'];

                    if ($profesor && !$reservado && $cupo_actual < 15) {
                        echo "<form method='post'>
                                <input type='hidden' name='dia' value='$dia'>
                                <input type='hidden' name='hora' value='{$h['inicio']}'>
                                <button type='submit' name='reservar' style='background:blue;color:white;'>Reservar</button><br>
                              </form><small>$profesor</small>";
                    } elseif ($reservado) {
                        echo "<form method='post'>
                                <input type='hidden' name='dia' value='$dia'>
                                <input type='hidden' name='hora' value='{$h['inicio']}'>
                                <button type='submit' name='cancelar' style='background:orange;color:black;'>Cancelar</button><br>
                              </form><small>$profesor</small>";
                    } elseif ($cupo_actual >= 15) {
                        echo "<span style='color:red;'>Cupo completo</span><br><small>$profesor</small>";
                    } elseif (!$profesor) {
                        echo "-";
                    }
                    ?>
                </td>
            <?php endforeach; ?>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
