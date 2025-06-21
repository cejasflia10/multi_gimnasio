
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$fechaHoy = date('Y-m-d');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre_gimnasio = $_SESSION['nombre_gimnasio'] ?? '';

// Consulta para asistencias de clientes
$consultaClientes = "
    SELECT c.apellido, c.nombre, a.fecha, a.hora
    FROM asistencias_clientes a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE c.gimnasio_id = $gimnasio_id AND a.fecha = '$fechaHoy'
    ORDER BY a.hora DESC
";
$resultadoClientes = $conexion->query($consultaClientes);

// Consulta para asistencias de profesores
$consultaProfesores = "
    SELECT p.apellido, p.nombre, r.fecha_ingreso, r.hora_ingreso, r.hora_salida
    FROM asistencias_profesores r
    JOIN profesores p ON r.profesor_id = p.id
    WHERE p.gimnasio_id = $gimnasio_id AND r.fecha_ingreso = '$fechaHoy'
    ORDER BY r.hora_ingreso DESC
";
$resultadoProfesores = $conexion->query($consultaProfesores);
?>

<?php include 'menu.php'; ?>

<div class="contenido" style="padding: 20px;">
    <h2 style="color: gold; font-size: 24px; text-align: center;">ACADEMY SCORPIONS</h2>
    <h3 style="color: gold; font-size: 20px;">Bienvenido al Panel de Control</h3>
    <h4 style="color: white;">Gimnasio actual: <strong style="color: gold;"><?php echo $nombre_gimnasio; ?></strong></h4>

    <h4 style="color: white; margin-top: 30px;">Asistencias de Clientes - <?php echo $fechaHoy; ?></h4>
    <table style="width: 100%; border-collapse: collapse; background-color: #222; color: #fff;">
        <thead style="background-color: #444;">
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php while($fila = $resultadoClientes->fetch_assoc()): ?>
            <tr>
                <td><?php echo $fila['apellido']; ?></td>
                <td><?php echo $fila['nombre']; ?></td>
                <td><?php echo $fila['fecha']; ?></td>
                <td><?php echo $fila['hora']; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <h4 style="color: white; margin-top: 30px;">Asistencias de Profesores - <?php echo $fechaHoy; ?></h4>
    <table style="width: 100%; border-collapse: collapse; background-color: #222; color: #fff;">
        <thead style="background-color: #444;">
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Hora Ingreso</th>
                <th>Hora Salida</th>
            </tr>
        </thead>
        <tbody>
            <?php while($fila = $resultadoProfesores->fetch_assoc()): ?>
            <tr>
                <td><?php echo $fila['apellido']; ?></td>
                <td><?php echo $fila['nombre']; ?></td>
                <td><?php echo $fila['fecha_ingreso']; ?></td>
                <td><?php echo $fila['hora_ingreso']; ?></td>
                <td><?php echo $fila['hora_salida'] ?? '-'; ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>
