<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date("Y-m-d");

// Obtener asistencias de clientes (por DNI)
$clientes = $conexion->query("
    SELECT c.apellido, c.nombre, a.fecha_hora 
    FROM asistencias a 
    JOIN clientes c ON a.dni = c.dni 
    WHERE DATE(a.fecha_hora) = '$hoy'
    ORDER BY a.fecha_hora DESC
");

// Obtener asistencias de profesores
$profesores = $conexion->query("
    SELECT p.apellido, p.nombre, r.fecha_hora, r.tipo 
    FROM rfid_registros r 
    JOIN profesores p ON r.profesor_id = p.id 
    WHERE DATE(r.fecha_hora) = '$hoy'
    ORDER BY r.fecha_hora DESC
");
?>

<div style="padding: 20px; color: gold;">
    <h2>📋 Asistencias del día - <?php echo date("d/m/Y"); ?></h2>

    <h3>👥 Clientes</h3>
    <ul>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <li><?php echo $c['apellido'] . ", " . $c['nombre'] . " - " . date("H:i", strtotime($c['fecha_hora'])); ?></li>
        <?php endwhile; ?>
    </ul>

    <h3>👨‍🏫 Profesores</h3>
    <ul>
        <?php while ($p = $profesores->fetch_assoc()): ?>
            <li>
                <?php
                echo $p['apellido'] . ", " . $p['nombre'] . " - " .
                date("H:i", strtotime($p['fecha_hora'])) . " (" . strtoupper($p['tipo']) . ")";
                ?>
            </li>
        <?php endwhile; ?>
    </ul>
</div>
