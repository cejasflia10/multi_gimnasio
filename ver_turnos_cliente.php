<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_cliente.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

if ($gimnasio_id == 0 || $cliente_id == 0) {
    echo "<div style='color:red; text-align:center;'>❌ Acceso denegado</div>";
    exit;
}

// Obtener turnos del gimnasio
$turnos = $conexion->query("
    SELECT t.*, p.apellido AS apellido_profesor, p.nombre AS nombre_profesor 
    FROM turnos_profesor t
    JOIN profesores p ON t.profesor_id = p.id
    WHERE t.gimnasio_id = $gimnasio_id
    ORDER BY FIELD(t.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), t.horario_inicio
");

// Organizar turnos por hora y día
$tabla = [];
while ($t = $turnos->fetch_assoc()) {
    $hora = substr($t['horario_inicio'], 0, 5) . " - " . substr($t['horario_fin'], 0, 5);
    $tabla[$hora][$t['dia']] = $t;
}

$dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
$horas = [];
for ($h = 8; $h < 23; $h++) {
    $inicio = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
    $fin = str_pad($h + 1, 2, "0", STR_PAD_LEFT) . ":00";
    $horas[] = "$inicio - $fin";
}
?>

<style>
    table { width: 100%; border-collapse: collapse; color: gold; }
    th, td { border: 1px solid gold; padding: 5px; text-align: center; }
    body { background: black; }
    button { background: blue; color: white; border: none; padding: 5px 10px; border-radius: 5px; }
</style>

<h2 style="text-align:center; color: gold;">📅 Turnos Semanales</h2>

<table>
    <tr>
        <th>Hora</th>
        <?php foreach ($dias as $d) echo "<th>$d</th>"; ?>
    </tr>

    <?php foreach ($horas as $hora): ?>
    <tr>
        <td><?= $hora ?></td>
        <?php foreach ($dias as $dia): ?>
            <td>
                <?php 
                if (isset($tabla[$hora][$dia])) {
                    $t = $tabla[$hora][$dia];
                    echo $t['apellido_profesor'] . ' ' . $t['nombre_profesor'] . "<br>";

                    // Calcular si faltan mínimo 2 horas
                    $turnoFechaHora = date('Y-m-d') . " " . $t['horario_inicio'];
                    $turnoTimestamp = strtotime("$dia this week " . $t['horario_inicio']);
                    $ahora = time();

                    if ($turnoTimestamp - $ahora > 7200) { // 7200 seg = 2 horas
                        echo "<form method='POST' action='reservar_turno.php'>
                            <input type='hidden' name='turno_id' value='" . $t['id'] . "'>
                            <button type='submit'>Reservar</button>
                        </form>";
                    } else {
                        echo "<span style='color:gray;'>Cerrado</span>";
                    }
                } else {
                    echo "-";
                }
                ?>
            </td>
        <?php endforeach; ?>
    </tr>
    <?php endforeach; ?>
</table>
