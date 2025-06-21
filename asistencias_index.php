
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date('Y-m-d');

// Consulta de asistencias de clientes
$sql1 = "SELECT c.apellido, c.nombre, a.fecha, a.hora
         FROM clientes c
         INNER JOIN asistencias_clientes a ON c.id = a.cliente_id
         WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$hoy'
         ORDER BY a.hora DESC";

$result1 = $conexion->query($sql1);

// Consulta de asistencias de profesores
$sql2 = "SELECT p.apellido, r.fecha, r.hora_ingreso, r.hora_egreso
         FROM profesores p
         INNER JOIN rfid_registros r ON p.id = r.profesor_id
         WHERE r.id_gimnasio = $gimnasio_id AND r.fecha = '$hoy'
         ORDER BY r.hora_ingreso DESC";

$result2 = $conexion->query($sql2);
?>

<div style='padding: 20px; color: #fff; background-color: #111;'>
    <h3>Asistencias de Clientes (<?php echo $hoy; ?>)</h3>
    <table border='1' cellpadding='5' cellspacing='0' style='width:100%; background:#222; color:#fff;'>
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
        <?php while($row = $result1->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['apellido']; ?></td>
                <td><?php echo $row['nombre']; ?></td>
                <td><?php echo $row['fecha']; ?></td>
                <td><?php echo $row['hora']; ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h3 style='margin-top:40px;'>Asistencias de Profesores (<?php echo $hoy; ?>)</h3>
    <table border='1' cellpadding='5' cellspacing='0' style='width:100%; background:#222; color:#fff;'>
        <tr><th>Apellido</th><th>Fecha</th><th>Ingreso</th><th>Egreso</th></tr>
        <?php while($row = $result2->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['apellido']; ?></td>
                <td><?php echo $row['fecha']; ?></td>
                <td><?php echo $row['hora_ingreso']; ?></td>
                <td><?php echo $row['hora_egreso']; ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</div>
