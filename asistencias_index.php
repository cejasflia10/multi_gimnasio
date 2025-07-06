<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

if (!isset($_SESSION['gimnasio_id'])) {
    $_SESSION['gimnasio_id'] = 1; // Modo prueba
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$hoy = date('Y-m-d');

// Consulta de asistencias del día
$query = "
SELECT c.apellido, c.nombre, a.fecha, a.hora
FROM asistencias a
INNER JOIN clientes c ON c.id = a.cliente_id
WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$hoy'
ORDER BY a.hora DESC
";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="estilo_unificado.css">

    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  
</head>
<body>
<div class="contenedor">

<h2>Asistencias del Día</h2>
<div class="info">Gimnasio: <?= $gimnasio_id ?> | Fecha: <?= $hoy ?></div>

<?php if (!$resultado || $resultado->num_rows === 0): ?>
    <div class="no-result">Sin resultados para hoy.</div>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>Apellido</th>
                <th>Nombre</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['apellido']) ?></td>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= htmlspecialchars($row['fecha']) ?></td>
                    <td><?= htmlspecialchars($row['hora']) ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php endif; ?>
</div>

</body>
</html>
