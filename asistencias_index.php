<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? null;
if (!$gimnasio_id) {
    die("Error: No se pudo identificar el gimnasio.");
}

$fecha = date('Y-m-d');

// CLIENTES
$sql_clientes = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias_clientes a
JOIN clientes c ON a.cliente_id = c.id
WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha'
ORDER BY a.hora DESC
";
$result_clientes = $conexion->query($sql_clientes);

// PROFESORES
$sql_profesores = "
SELECT p.apellido, p.nombre, r.fecha_ingreso, r.hora_ingreso, r.hora_salida
FROM rfid_registros r
JOIN profesores p ON r.profesor_id = p.id
WHERE r.id_gimnasio = $gimnasio_id AND r.fecha_ingreso = '$fecha'
ORDER BY r.hora_ingreso DESC
";
$result_profesores = $conexion->query($sql_profesores);
?>

<div style='padding: 20px; color: #f1f1f1; font-family: Arial; background-color: #111;'>
    <h2>Asistencias del Día - Clientes</h2>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; background:#222; color:#fff;">
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Hora</th>
        </tr>
        <?php while($row = $result_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['apellido']) ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['fecha']) ?></td>
                <td><?= htmlspecialchars($row['hora']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2 style='margin-top:40px;'>Asistencias del Día - Profesores</h2>
    <table border="1" cellpadding="8" cellspacing="0" style="width:100%; background:#222; color:#fff;">
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Hora Ingreso</th>
            <th>Hora Salida</th>
        </tr>
        <?php while($row = $result_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['apellido']) ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['fecha_ingreso']) ?></td>
                <td><?= htmlspecialchars($row['hora_ingreso']) ?></td>
                <td><?= htmlspecialchars($row['hora_salida']) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
