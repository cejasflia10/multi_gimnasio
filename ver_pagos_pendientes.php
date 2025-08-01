<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$mensaje = "";

// Acción: Aprobar o Rechazar
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'aprobar') {
        $pago = $conexion->query("SELECT * FROM pagos_pendientes WHERE id = $id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

        if ($pago) {
            $plan_id = intval($pago['plan_id']);
            $cliente_id = intval($pago['cliente_id']);
            $total = floatval($pago['monto']);

            $plan = $conexion->query("SELECT * FROM planes WHERE id = $plan_id AND gimnasio_id = $gimnasio_id")->fetch_assoc();

            if ($plan) {
                $clases = intval($plan['clases']);
                $duracion = intval($plan['duracion']);
                $fecha_inicio = date('Y-m-d');
                $fecha_vencimiento = date('Y-m-d', strtotime("+$duracion months"));

                $conexion->query("INSERT INTO membresias 
                    (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, total, metodo_pago, gimnasio_id) 
                    VALUES ($cliente_id, $plan_id, '$fecha_inicio', '$fecha_vencimiento', $clases, $total, 'Transferencia (comprobante)', $gimnasio_id)");

                $conexion->query("UPDATE pagos_pendientes SET estado = 'aprobado' WHERE id = $id");
                $mensaje = "<p style='color:lime;'>✅ Pago aprobado correctamente.</p>";
            }
        }
    } elseif ($accion === 'rechazar') {
        $conexion->query("UPDATE pagos_pendientes SET estado = 'rechazado' WHERE id = $id AND gimnasio_id = $gimnasio_id");
        $mensaje = "<p style='color:red;'>❌ Pago rechazado.</p>";
    }
}

// Consultar pagos pendientes del gimnasio logueado
$pagos = $conexion->query("SELECT p.*, c.apellido, c.nombre, pl.nombre AS nombre_plan 
    FROM pagos_pendientes p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN planes pl ON p.plan_id = pl.id
    WHERE p.estado = 'pendiente' AND p.gimnasio_id = $gimnasio_id
    ORDER BY p.fecha_envio DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos Pendientes</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial; padding: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; background: #222; }
        th, td { border: 1px solid gold; padding: 10px; text-align: center; }
        th { background: #333; }
        button { padding: 6px 12px; border: none; font-weight: bold; cursor: pointer; }
        .btn-aprobar { background-color: limegreen; color: black; }
        .btn-rechazar { background-color: crimson; color: white; }
        .mensaje { text-align: center; font-size: 18px; }
    </style>
</head>
<body>

<h2 style="text-align:center;">📥 Pagos Pendientes</h2>
<div class="mensaje"><?= $mensaje ?></div>

<?php if ($pagos && $pagos->num_rows > 0): ?>
<table>
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Plan</th>
            <th>Monto</th>
            <th>Fecha</th>
            <th>Comprobante</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($p = $pagos->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) ?></td>
            <td><?= htmlspecialchars($p['nombre_plan']) ?></td>
            <td>$<?= number_format($p['monto'], 2, ',', '.') ?></td>
            <td><?= date('d/m/Y', strtotime($p['fecha_envio'])) ?></td>
            <td>
                <?php if (!empty($p['archivo_comprobante'])): ?>
                    <a href="<?= htmlspecialchars($p['archivo_comprobante']) ?>" target="_blank" style="color:deepskyblue;">📄 Ver</a>
                <?php else: ?>
                    Sin archivo
                <?php endif; ?>
            </td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <button class="btn-aprobar" onclick="return confirm('¿Aprobar este pago?')">✅ Aprobar</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                    <input type="hidden" name="accion" value="rechazar">
                    <button class="btn-rechazar" onclick="return confirm('¿Rechazar este pago?')">❌ Rechazar</button>
                </form>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php else: ?>
<p style="text-align:center; color: orange;">No hay pagos pendientes.</p>
<?php endif; ?>

</body>
</html>
