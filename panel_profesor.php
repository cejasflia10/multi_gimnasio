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
$dia_semana = date('l'); // 'Monday', 'Tuesday', etc.
$dias_es = [
    'Monday' => 'Lunes',
    'Tuesday' => 'Martes',
    'Wednesday' => 'Miércoles',
    'Thursday' => 'Jueves',
    'Friday' => 'Viernes',
    'Saturday' => 'Sábado',
    'Sunday' => 'Domingo'
];
$dia_actual = $dias_es[$dia_semana];

$reservas_hoy = $conexion->query("
    SELECT t.hora_inicio, t.hora_fin, c.apellido, c.nombre 
    FROM reservas r
    JOIN turnos_profesor t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.dia = '$dia_actual'
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
 <!-- Reservas del Día -->
    <div style="flex:1; min-width:300px; background:#222; padding:15px; border-radius:10px;">
        <h3 style="color:gold;">Reservas del Día</h3>
        <?php
        $reservas_q = $conexion->query("
            SELECT r.dia_semana AS dia, r.hora_inicio, td.hora_fin,
                   c.nombre, c.apellido,
                   CONCAT(p.apellido, ' ', p.nombre) AS profesor
            FROM reservas_clientes r
            JOIN clientes c ON r.cliente_id = c.id
            JOIN profesores p ON r.profesor_id = p.id
            JOIN turnos_disponibles td ON r.turno_id = td.id
            WHERE r.fecha_reserva = CURDATE()
              AND r.gimnasio_id = $gimnasio_id
            ORDER BY r.hora_inicio
        ");

        if ($reservas_q->num_rows > 0) {
            while ($res = $reservas_q->fetch_assoc()) {
                echo "<p style='color:white; margin:5px 0;'>
                    🕒 {$res['hora_inicio']} a {$res['hora_fin']}<br>
                    👤 {$res['apellido']} {$res['nombre']}<br>
                    👨‍🏫 Prof. {$res['profesor']}
                </p>";
            }
        } else {
            echo "<p style='color:gray;'>No hay reservas registradas para hoy.</p>";
        }
        ?>
    </div>
</div>
</body>
</html>
