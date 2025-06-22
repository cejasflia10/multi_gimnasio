<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

if (!isset($_SESSION['gimnasio_id'])) {
    die("Gimnasio no definido.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date('Y-m-d');

// CONSULTA CORREGIDA
$query = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias a
INNER JOIN clientes c ON c.id = a.cliente_id
WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$hoy'
ORDER BY a.hora DESC
";

$resultado = $conexion->query($query);
?>

<div style='padding:20px; color:#fff; font-family:Arial, sans-serif;'>
    <h3>Asistencias de Clientes - <?php echo $hoy; ?></h3>
    <table border="1" cellpadding="5" cellspacing="0" style='width:100%; background-color:#222; color:#fff; border-collapse: collapse;'>
        <tr style='background-color:#444;'>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Fecha</th>
            <th>Hora</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?php echo htmlspecialchars($row['apellido']); ?></td>
            <td><?php echo htmlspecialchars($row['nombre']); ?></td>
            <td><?php echo htmlspecialchars($row['fecha']); ?></td>
            <td><?php echo htmlspecialchars($row['hora']); ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
