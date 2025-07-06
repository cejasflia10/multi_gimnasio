<?php
include 'conexion.php';
include 'menu_horizontal.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $accion = $_POST['accion'];

    if ($accion === 'aprobar') {
        $pago = $conexion->query("SELECT * FROM pagos_pendientes WHERE id = $id")->fetch_assoc();
        $plan = $conexion->query("SELECT * FROM planes WHERE id = " . $pago['plan_id'])->fetch_assoc();

        $fecha_inicio = date('Y-m-d');
        $fecha_vencimiento = date('Y-m-d', strtotime("+{$plan['duracion']} months"));
        $clases = $plan['clases'];
        $total = $pago['monto'];

        $conexion->query("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, total, metodo_pago) VALUES (
            {$pago['cliente_id']}, {$pago['plan_id']}, '$fecha_inicio', '$fecha_vencimiento', $clases, $total, 'Transferencia (comprobante)'
        )");

        $conexion->query("UPDATE pagos_pendientes SET estado = 'aprobado' WHERE id = $id");
    } elseif ($accion === 'rechazar') {
        $conexion->query("UPDATE pagos_pendientes SET estado = 'rechazado' WHERE id = $id");
    }
}

$pagos = $conexion->query("
    SELECT pp.*, c.apellido, c.nombre, p.nombre AS nombre_plan
    FROM pagos_pendientes pp
    JOIN clientes c ON pp.cliente_id = c.id
    JOIN planes p ON pp.plan_id = p.id
    WHERE pp.estado = 'pendiente'
    ORDER BY pp.fecha_envio DESC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">

<h1>📤 Pagos Pendientes de Aprobación</h1>

<?php if ($pagos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Monto</th>
            <th>Comprobante</th>
            <th>Fecha</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($p = $pagos->fetch_assoc()): ?>
        <tr>
            <td><?= $p['apellido'] ?>, <?= $p['nombre'] ?></td>
            <td><?= $p['nombre_plan'] ?></td>
            <td>$<?= number_format($p['monto'], 2, ',', '.') ?></td>
            <td><a href="<?= $p['archivo_comprobante'] ?>" target="_blank">Ver</a></td>
            <td><?= $p['fecha_envio'] ?></td>
            <td>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <button type="submit">✅ Aprobar</button>
                </form>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="accion" value="rechazar">
                    <button type="submit">❌ Rechazar</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align: center;">No hay pagos pendientes.</p>
<?php endif; ?>

</div>
</body>
</html>
