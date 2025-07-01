<?php
include 'conexion.php';

if (!isset($_GET['id'])) {
    die("ID no especificado.");
}

$id = intval($_GET['id']);
$mensaje = "";

// Obtener datos actuales del turno
$consulta = $conexion->prepare("SELECT * FROM turnos WHERE id = ?");
$consulta->bind_param("i", $id);
$consulta->execute();
$resultado = $consulta->get_result();
$turno = $resultado->fetch_assoc();

if (!$turno) {
    die("Turno no encontrado.");
}

// Obtener opciones de horarios y profesores
$horarios = $conexion->query("SELECT * FROM horarios ORDER BY hora_inicio");
$profesores = $conexion->query("SELECT * FROM profesores ORDER BY apellido");

// Actualizar turno
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dia = $_POST['dia'];
    $id_profesor = $_POST['id_profesor'];
    $id_horario = $_POST['id_horario'];
    $cupo_maximo = $_POST['cupo_maximo'];

    $stmt = $conexion->prepare("UPDATE turnos SET dia = ?, id_profesor = ?, id_horario = ?, cupo_maximo = ? WHERE id = ?");
    $stmt->bind_param("siiii", $dia, $id_profesor, $id_horario, $cupo_maximo, $id);
    $stmt->execute();

    $mensaje = "✅ Turno actualizado correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Turno</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        form {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 20px;
            border: 1px solid gold;
            border-radius: 10px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        select, input[type="number"], button {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border-radius: 6px;
            border: none;
        }
        button {
            background: gold;
            color: black;
            font-weight: bold;
            margin-top: 20px;
            cursor: pointer;
        }
        .mensaje {
            text-align: center;
            margin-top: 20px;
            color: lightgreen;
        }
    </style>
</head>
<body>

<h1>Editar Turno</h1>

<form method="POST">
    <label>Día:</label>
    <select name="dia" required>
        <?php
        $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        foreach ($dias as $dia) {
            $sel = ($turno['dia'] === $dia) ? 'selected' : '';
            echo "<option value='$dia' $sel>$dia</option>";
        }
        ?>
    </select>

    <label>Horario:</label>
    <select name="id_horario" required>
        <?php while ($h = $horarios->fetch_assoc()):
            $sel = ($turno['id_horario'] == $h['id']) ? 'selected' : '';
            echo "<option value='{$h['id']}' $sel>{$h['hora_inicio']} - {$h['hora_fin']}</option>";
        endwhile; ?>
    </select>

    <label>Profesor:</label>
    <select name="id_profesor" required>
        <?php while ($p = $profesores->fetch_assoc()):
            $nombre = $p['apellido'] . ", " . $p['nombre'];
            $sel = ($turno['id_profesor'] == $p['id']) ? 'selected' : '';
            echo "<option value='{$p['id']}' $sel>$nombre</option>";
        endwhile; ?>
    </select>

    <label>Cupo máximo:</label>
    <input type="number" name="cupo_maximo" value="<?= $turno['cupo_maximo'] ?>" min="1" required>

    <button type="submit">Guardar cambios</button>
</form>

<?php if ($mensaje): ?>
    <p class="mensaje"><?= $mensaje ?></p>
<?php endif; ?>

</body>
</html>


<?php
// Mostrar almanaque semanal debajo del formulario
echo "<h2 style='text-align:center; margin-top:40px;'>📅 Almanaque semanal de este turno</h2>";

$dia_base = new DateTime();  // hoy
$lunes = clone $dia_base;
$lunes->modify('monday this week');  // lunes actual

$semanal = [];

for ($i = 0; $i < 6; $i++) {
    $fecha = clone $lunes;
    $fecha->modify("+{$i} day");
    $fecha_str = $fecha->format('Y-m-d');

    // Obtener cantidad de reservas para ese turno en ese día
    $reservas = $conexion->query("SELECT COUNT(*) AS total FROM reservas WHERE turno_id = $id AND fecha = '$fecha_str'");
    $cantidad = $reservas->fetch_assoc()['total'];

    $estado = ($cantidad >= $turno['cupo_maximo']) ? 'COMPLETO' : 'Disponible';

    $dia_esp = $fecha->format('l');
$dia_esp = str_replace('Monday', 'Lunes', $dia_esp);
$dia_esp = str_replace('Tuesday', 'Martes', $dia_esp);
$dia_esp = str_replace('Wednesday', 'Miércoles', $dia_esp);
$dia_esp = str_replace('Thursday', 'Jueves', $dia_esp);
$dia_esp = str_replace('Friday', 'Viernes', $dia_esp);
$dia_esp = str_replace('Saturday', 'Sábado', $dia_esp);
$dia_esp = str_replace('Sunday', 'Domingo', $dia_esp);
    $semanal[] = [
        'dia' => $dia_esp,
        'fecha' => $fecha_str,
        'reservas' => $cantidad,
        'estado' => $estado
    ];
}
?>

<table style='width:100%; margin-top:20px; border-collapse: collapse;'>
    <thead>
        <tr style='background:#222; color:gold;'>
            <th style='padding:10px; border:1px solid gold;'>Día</th>
            <th style='padding:10px; border:1px solid gold;'>Fecha</th>
            <th style='padding:10px; border:1px solid gold;'>Reservas</th>
            <th style='padding:10px; border:1px solid gold;'>Estado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($semanal as $d): ?>
        <tr>
            <td style='padding:10px; border:1px solid gold;'><?= ucfirst($d['dia']) ?></td>
            <td style='padding:10px; border:1px solid gold;'><?= $d['fecha'] ?></td>
            <td style='padding:10px; border:1px solid gold;'><?= $d['reservas'] ?>/<?= $turno['cupo_maximo'] ?></td>
            <td style='padding:10px; border:1px solid gold; color: <?= $d['estado']=='COMPLETO'?'red':'lightgreen' ?>;'><?= $d['estado'] ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
