<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

if (!isset($_SESSION['gimnasio_id']) && $_SESSION['rol'] !== 'admin') {
    echo "Acceso denegado.";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$es_admin = ($_SESSION['rol'] === 'admin');

$query = $es_admin
    ? "SELECT p.*, g.nombre AS gimnasio_nombre FROM pagos_gimnasio p JOIN gimnasios g ON p.gimnasio_id = g.id ORDER BY p.fecha DESC"
    : "SELECT p.*, g.nombre AS gimnasio_nombre FROM pagos_gimnasio p JOIN gimnasios g ON p.gimnasio_id = g.id WHERE p.gimnasio_id = $gimnasio_id ORDER BY p.fecha DESC";

$resultado = $conexion->query($query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos de Gimnasios</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; background: #222; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; border: 1px solid gold; text-align: center; }
        h2 { text-align: center; color: white; }
    </style>
</head>
<body>

<h2>💳 Historial de Pagos de Gimnasios</h2>

<?php if ($resultado->num_rows > 0): ?>
    <table>
        <tr>
            <th>Fecha</th>
            <th>Monto</th>
            <th>Método de Pago</th>
            <th>Gimnasio</th>
            <th>Observaciones</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($fila['fecha']) ?></td>
            <td>$<?= number_format($fila['monto'], 2, ',', '.') ?></td>
            <td><?= htmlspecialchars($fila['metodo_pago']) ?></td>
            <td><?= htmlspecialchars($fila['gimnasio_nombre']) ?></td>
            <td><?= htmlspecialchars($fila['observaciones']) ?></td>
        </tr>
        <?php endwhile; ?>
    </table>
<?php else: ?>
    <p style="color: white; text-align: center;">No se encontraron pagos registrados.</p>
<?php endif; ?>

</body>
</html>
