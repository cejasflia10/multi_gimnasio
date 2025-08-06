<?php
// panel_profesor.php (corregido)
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['profesor_id']) || empty($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado. Por favor inicie sesión.";
    exit;
}

include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = intval($_SESSION['profesor_id']);
$gimnasio_id = intval($_SESSION['gimnasio_id']);

// Datos del profesor
$prof_q = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id LIMIT 1");
$prof = $prof_q ? $prof_q->fetch_assoc() : ['apellido' => '', 'nombre' => ''];

// Asistencias del día (del profesor)
$asistencias_hoy = $conexion->query("
    SELECT hora_ingreso, hora_salida 
    FROM asistencias_profesores 
    WHERE profesor_id = $profesor_id 
      AND fecha = CURDATE()
");

// Alumnos que ingresaron hoy (todo el gimnasio)
$alumnos_hoy = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora 
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = CURDATE()
      AND c.gimnasio_id = $gimnasio_id
    ORDER BY a.hora
");

// Reservas del día (para todo el gimnasio). NOTA: uso td.hora_fin como hora de fin del turno
$reservas_sql = "
    SELECT r.id, r.hora_inicio, td.hora_fin AS turno_hora_fin, r.turno_id,
           c.apellido AS cliente_apellido, c.nombre AS cliente_nombre,
           p.apellido AS prof_apellido, p.nombre AS prof_nombre
    FROM reservas_clientes r
    LEFT JOIN clientes c ON r.cliente_id = c.id
    LEFT JOIN profesores p ON r.profesor_id = p.id
    LEFT JOIN turnos_disponibles td ON r.turno_id = td.id
    WHERE r.fecha_reserva = CURDATE()
      AND r.gimnasio_id = $gimnasio_id
    ORDER BY r.hora_inicio, cliente_apellido
";
$reservas_q = $conexion->query($reservas_sql);

// Total horas trabajadas este mes (del profesor)
$turnos_mes = $conexion->query("
    SELECT hora_ingreso, hora_salida 
    FROM asistencias_profesores 
    WHERE profesor_id = $profesor_id 
      AND MONTH(fecha) = MONTH(CURDATE()) 
      AND YEAR(fecha) = YEAR(CURDATE())
");

$total_horas = 0;
if ($turnos_mes) {
    while ($fila = $turnos_mes->fetch_assoc()) {
        if (!empty($fila['hora_ingreso']) && !empty($fila['hora_salida'])) {
            $ini = strtotime($fila['hora_ingreso']);
            $fin = strtotime($fila['hora_salida']);
            if ($fin > $ini) {
                $total_horas += ($fin - $ini) / 3600;
            }
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
    <style>
        .contenedor { display:flex; gap:20px; flex-wrap:wrap; }
        .cuadro { background:#222; padding:15px; border-radius:10px; min-width:280px; flex:1; color:gold; }
        .sub { color:lightgray; font-size:0.95em; }
        .res-item { color:lightgoldenrodyellow; margin-bottom:10px; }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cuadro" style="flex-basis:100%;">
        <h2>👨‍🏫 Bienvenido <?= htmlspecialchars(trim(($prof['apellido'] ?? '') . ' ' . ($prof['nombre'] ?? ''))) ?></h2>
        <p class="sub">Gimnasio ID: <?= $gimnasio_id ?> | Profesor ID: <?= $profesor_id ?></p>
    </div>

    <div class="cuadro">
        <h3>📆 Tu asistencia (hoy)</h3>
        <?php if ($asistencias_hoy && $asistencias_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($a = $asistencias_hoy->fetch_assoc()): ?>
                    <li>Ingreso: <b><?= htmlspecialchars($a['hora_ingreso']) ?></b> | Salida: <b><?= htmlspecialchars($a['hora_salida'] ?: '-') ?></b></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No registraste asistencia hoy.</p>
        <?php endif; ?>
    </div>

    <div class="cuadro">
        <h3>🧍 Alumnos que ingresaron hoy (todo el gimnasio)</h3>
        <?php if ($alumnos_hoy && $alumnos_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($al = $alumnos_hoy->fetch_assoc()): ?>
                    <li><?= htmlspecialchars($al['apellido'] . ' ' . $al['nombre']) ?> - ⏰ <?= htmlspecialchars($al['hora']) ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No se registraron ingresos de alumnos hoy.</p>
        <?php endif; ?>
    </div>

    <div class="cuadro">
        <h3>📋 Reservas del Día (todo el gimnasio)</h3>
        <?php
        if ($reservas_q === false) {
            echo "<p style='color:salmon;'>Error al consultar reservas: " . htmlspecialchars($conexion->error) . "</p>";
        } elseif ($reservas_q->num_rows > 0) {
            while ($r = $reservas_q->fetch_assoc()) {
                $hora_i = htmlspecialchars($r['hora_inicio'] ?? '');
                $hora_f = htmlspecialchars($r['turno_hora_fin'] ?? '');
                $cliente = trim(htmlspecialchars(($r['cliente_apellido'] ?? '') . ' ' . ($r['cliente_nombre'] ?? '')));
                $profesor = trim(htmlspecialchars(($r['prof_apellido'] ?? '') . ' ' . ($r['prof_nombre'] ?? '')));
                if ($profesor === '') $profesor = '—';
                echo "<div class='res-item'>
                        🕒 {$hora_i}" . ($hora_f ? " - {$hora_f}" : "") . "<br>
                        👤 {$cliente} <br>
                        👨‍🏫 Prof: {$profesor}
                      </div>";
            }
        } else {
            echo "<p style='color:gray;'>No hay reservas registradas para hoy.</p>";
        }
        ?>
    </div>

    <div class="cuadro">
        <h3>⏱ Horas trabajadas (mes)</h3>
        <p><?= round($total_horas, 2) ?> hs</p>
    </div>
</div>
</body>
</html>
