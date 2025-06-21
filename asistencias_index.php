<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date("Y-m-d");

// Asistencias de clientes
$resultado_clientes = $conexion->query("
    SELECT c.apellido, c.nombre, a.fecha, a.hora
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$hoy'
    ORDER BY a.hora DESC
");

// Asistencias de profesores (usando rfid_registros)
$resultado_profesores = $conexion->query("
    SELECT p.apellido, r.fecha, r.hora
    FROM rfid_registros r
    JOIN profesores p ON r.profesor_id = p.id
    WHERE r.id_gimnasio = $gimnasio_id AND r.fecha = '$hoy'
    ORDER BY r.hora DESC
");
?>

<div style='padding: 10px;'>
    <h2>Asistencias del día - Clientes</h2>
    <table border='1' style='width: 100%; text-align: center; margin-bottom: 20px;'>
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
        <?php while($row = $resultado_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Asistencias del día - Profesores</h2>
    <table border='1' style='width: 100%; text-align: center;'>
        <tr><th>Apellido</th><th>Fecha</th><th>Hora</th></tr>
        <?php while($row = $resultado_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
