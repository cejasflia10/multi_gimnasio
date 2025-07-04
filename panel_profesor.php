<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['profesor_id']) || empty($_SESSION['profesor_id'])) {
    echo "Acceso denegado.";
    exit;
}

include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'];
$gimnasio_id = $_SESSION['gimnasio_id'];
$fecha_hoy = date('Y-m-d');

$prof = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = $profesor_id")->fetch_assoc();

// Obtener alumnos del día
$alumnos = $conexion->query("
    SELECT c.apellido, c.nombre
    FROM reservas r
    JOIN turnos t ON r.turno_id = t.id
    JOIN clientes c ON r.cliente_id = c.id
    WHERE t.id_profesor = $profesor_id AND r.fecha = '$fecha_hoy'
    ORDER BY c.apellido
");

// Calcular horas trabajadas
$ingresos = $conexion->query("
    SELECT fecha, hora_ingreso, hora_egreso
    FROM asistencias_profesor
    WHERE profesor_id = $profesor_id AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            color: gold;
        }
        .cuadro {
            border: 1px solid gold;
            padding: 10px;
            margin-top: 15px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <h2>👨‍🏫 Bienvenido <?= $prof['apellido'] . ' ' . $prof['nombre'] ?></h2>

    <div class="cuadro">
<?php echo "<h3>📌 Alumnos del día</h3>"; ?>
<ul>
    <?php while ($a = $alumnos->fetch_assoc()): ?>
        <li><?= $a['apellido'] . ' ' . $a['nombre'] ?></li>
    <?php endwhile; ?>
</ul>

<!-- Ingresos del Día -->
<div style="flex:1; min-width:300px; background:#222; padding:15px; border-radius:10px;">
    <h3 style="color:gold;">Ingresos del Día</h3>
    <?php
    $alumnos_q = $conexion->query("
    SELECT c.apellido, c.nombre, ap.hora_ingreso
    FROM clientes c
    JOIN asistencias_profesor ap ON ap.profesor_id = $profesor_id
        AND ap.cliente_id = c.id
        AND ap.fecha = CURDATE()
        AND ap.gimnasio_id = $gimnasio_id
    ORDER BY ap.hora_ingreso ASC
");


    if ($alumnos_q && $alumnos_q->num_rows > 0) {
        while ($row = $alumnos_q->fetch_assoc()) {
            echo "<p style='color:white; margin:5px 0;'>🧍 {$row['apellido']} {$row['nombre']}<br>🕒 {$row['hora_ingreso']}</p>";
        }
    } else {
        echo "<p style='color:gray;'>No se escanearon alumnos hoy.</p>";
    }
    ?>
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

    <div class="cuadro">
        <h3>🕒 Horas trabajadas este mes</h3>
        <ul>
            <?php
            $total_horas = 0;
            while ($i = $ingresos->fetch_assoc()):
                if ($i['hora_ingreso'] && $i['hora_egreso']) {
                    $inicio = strtotime($i['hora_ingreso']);
                    $fin = strtotime($i['hora_egreso']);
                    $horas = ($fin - $inicio) / 3600;
                    $total_horas += $horas;
                }
            endwhile;
            ?>
            <li>Total: <?= round($total_horas, 2) ?> horas</li>
        </ul>
    </div>
</body>
</html>
