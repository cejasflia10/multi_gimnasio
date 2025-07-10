<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['profesor_id']) || !isset($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado.";
    exit;
}

include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'];
$gimnasio_id = $_SESSION['gimnasio_id'];

// Datos del profesor
$prof = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id")->fetch_assoc();

// Asistencias del día
$asistencias_hoy = $conexion->query("
    SELECT hora_ingreso, hora_salida 
    FROM asistencias_profesores 
    WHERE profesor_id = $profesor_id 
      AND fecha = CURDATE()
");

// Alumnos que ingresaron hoy
$alumnos_hoy = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora 
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = CURDATE()
      AND c.gimnasio_id = $gimnasio_id
    ORDER BY a.hora
");

// Reservas del día (sin usar r.gimnasio_id)
$reservas_hoy = $conexion->query("
    SELECT t.hora_inicio, t.hora_fin, c.apellido, c.nombre 
    FROM reservas r
    JOIN turnos_profesor t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE r.fecha_reserva = CURDATE()
      AND t.profesor_id = $profesor_id
    ORDER BY t.hora_inicio, c.apellido
");

// Total horas trabajadas este mes
$turnos_mes = $conexion->query("
    SELECT hora_ingreso, hora_salida 
    FROM asistencias_profesores 
    WHERE profesor_id = $profesor_id 
      AND MONTH(fecha) = MONTH(CURDATE()) 
      AND YEAR(fecha) = YEAR(CURDATE())
");

$total_horas = 0;
while ($fila = $turnos_mes->fetch_assoc()) {
    if ($fila['hora_ingreso'] && $fila['hora_salida']) {
        $ini = strtotime($fila['hora_ingreso']);
        $fin = strtotime($fila['hora_salida']);
        if ($fin > $ini) {
            $total_horas += ($fin - $ini) / 3600;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
    <h2>👨‍🏫 Bienvenido <?= $prof['apellido'] . ' ' . $prof['nombre'] ?></h2>

    <div class="cuadro">
        <h3>📆 Asistencias del Día</h3>
        <?php if ($asistencias_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($a = $asistencias_hoy->fetch_assoc()): ?>
                    <li>Ingreso: <b><?= $a['hora_ingreso'] ?></b> | Salida: <b><?= $a['hora_salida'] ?: '-' ?></b></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No registraste asistencia hoy.</p>
        <?php endif; ?>
    </div>

    <div class="cuadro">
        <h3>🧍 Alumnos que ingresaron Hoy</h3>
        <?php if ($alumnos_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($al = $alumnos_hoy->fetch_assoc()): ?>
                    <li><?= $al['apellido'] . ' ' . $al['nombre'] ?> - ⏰ <?= $al['hora'] ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No se registraron ingresos de alumnos hoy.</p>
        <?php endif; ?>
    </div>

    <div class="cuadro">
        <h3>📅 Reservas del Día</h3>
        <?php if ($reservas_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($res = $reservas_hoy->fetch_assoc()): ?>
                    <li>🕒 <?= $res['hora_inicio'] ?> a <?= $res['hora_fin'] ?> - 👤 <?= $res['apellido'] ?> <?= $res['nombre'] ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No hay reservas para hoy.</p>
        <?php endif; ?>
    </div>

    <div class="cuadro">
        <h3>🕒 Total Horas Trabajadas en el Mes</h3>
        <p><strong><?= round($total_horas, 2) ?> horas</strong></p>
    </div>
</div>
</body>
</html>
