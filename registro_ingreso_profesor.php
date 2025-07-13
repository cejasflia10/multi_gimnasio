<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$mensaje = '';
$datos_profesor = null;
$asistencias = [];
$alumnos = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = trim($_POST['dni']);
    $fecha = date('Y-m-d');
    $hora_actual = date('H:i:s');

    $prof = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE dni = '$dni'")->fetch_assoc();

    if ($prof) {
        $profesor_id = $prof['id'];
        $datos_profesor = $prof;

        $registro = $conexion->query("SELECT * FROM asistencia_profesor 
                                       WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_salida IS NULL")
                              ->fetch_assoc();

        if ($registro) {
            $hora_entrada = strtotime($registro['hora_entrada']);
            $hora_salida = strtotime($hora_actual);
            $diferencia = round(($hora_salida - $hora_entrada) / 3600, 2);

            $conexion->query("UPDATE asistencia_profesor 
                              SET hora_salida = '$hora_actual', horas_trabajadas = $diferencia 
                              WHERE id = {$registro['id']}");

            $mensaje = "✅ Egreso registrado para {$prof['apellido']} {$prof['nombre']} | $diferencia hs.";
        } else {
            $conexion->query("INSERT INTO asistencia_profesor (profesor_id, fecha, hora_entrada) 
                              VALUES ($profesor_id, '$fecha', '$hora_actual')");

            $mensaje = "✅ Ingreso registrado para {$prof['apellido']} {$prof['nombre']} a las $hora_actual.";
        }

        $asis = $conexion->query("SELECT hora_entrada, hora_salida, horas_trabajadas 
                                  FROM asistencia_profesor 
                                  WHERE profesor_id = $profesor_id AND fecha = '$fecha' 
                                  ORDER BY id DESC LIMIT 5");
        while ($row = $asis->fetch_assoc()) {
            $asistencias[] = $row;
        }

        $reserva = $conexion->query("SELECT COUNT(*) AS cantidad FROM reservas r
                                      JOIN turnos t ON r.turno_id = t.id
                                      WHERE r.fecha = '$fecha' AND t.id_profesor = $profesor_id")->fetch_assoc();

        $total_alumnos = $reserva['cantidad'];

        $alumnos_query = $conexion->query("SELECT c.apellido, c.nombre FROM reservas r
                                            JOIN turnos t ON r.turno_id = t.id
                                            JOIN clientes c ON r.cliente_id = c.id
                                            WHERE r.fecha = '$fecha' AND t.id_profesor = $profesor_id
                                            ORDER BY c.apellido");
        while ($al = $alumnos_query->fetch_assoc()) {
            $alumnos[] = $al;
        }
    } else {
        $mensaje = "❌ Profesor no encontrado con DNI: $dni";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Ingreso/Egreso Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
<h1>🕘 Registro Ingreso / Egreso de Profesor</h1>
<form method="POST">
    <input type="text" name="dni" placeholder="Escanear DNI o QR" autofocus required>
    <br>
    <input type="submit" value="Registrar">
</form>

<?php if ($mensaje): ?><div class="mensaje"><?= $mensaje ?></div><?php endif; ?>

<?php if ($datos_profesor): ?>
<div class="seccion">
    <h2>📋 Asistencias de Hoy</h2>
    <table>
        <tr><th>Hora Ingreso</th><th>Hora Salida</th><th>Horas Trabajadas</th></tr>
        <?php foreach ($asistencias as $a): ?>
        <tr>
            <td><?= $a['hora_entrada'] ?? '-' ?></td>
            <td><?= $a['hora_salida'] ?? '-' ?></td>
            <td><?= $a['horas_trabajadas'] ?? '-' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<div class="seccion">
    <h2>👥 Alumnos Registrados Hoy (<?= count($alumnos) ?>)</h2>
    <table>
        <tr><th>Apellido</th><th>Nombre</th></tr>
        <?php foreach ($alumnos as $a): ?>
        <tr><td><?= $a['apellido'] ?></td><td><?= $a['nombre'] ?></td></tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>
</body>
</html>
