<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include("conexion.php");

$fecha = date("Y-m-d");

// Verifica que la sesión tenga gimnasio_id
if (!isset($_SESSION["gimnasio_id"])) {
    echo "<p>Error: no se detectó el gimnasio asignado.</p>";
    exit;
}

$gimnasio_id = $_SESSION["gimnasio_id"];

// Asistencias de clientes
$sql_clientes = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias_clientes a
JOIN clientes c ON a.cliente_id = c.id
WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha'
ORDER BY a.hora DESC
";
$result_clientes = $conexion->query($sql_clientes);

// Asistencias de profesores
$sql_profesores = "
SELECT p.apellido, p.nombre, r.fecha, r.ingreso, r.egreso
FROM rfid_registros r
JOIN profesores p ON r.profesor_id = p.id
WHERE r.id_gimnasio = $gimnasio_id AND r.fecha = '$fecha'
ORDER BY r.ingreso DESC
";
$result_profesores = $conexion->query($sql_profesores);
?>

<div style="display: flex; flex-wrap: wrap; justify-content: space-around; background: #111; color: gold; padding: 20px; border-radius: 10px;">
    <div style="flex: 1 1 45%; margin: 10px;">
        <h3>Clientes Asistieron Hoy</h3>
        <table style="width:100%; background:#222; color:white; border-collapse: collapse;">
            <tr style="background:#444;"><th>Apellido</th><th>Nombre</th><th>Hora</th></tr>
            <?php while($row = $result_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div style="flex: 1 1 45%; margin: 10px;">
        <h3>Profesores Asistieron Hoy</h3>
        <table style="width:100%; background:#222; color:white; border-collapse: collapse;">
            <tr style="background:#444;"><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
            <?php while($row = $result_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['ingreso'] ?></td>
                <td><?= $row['egreso'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
