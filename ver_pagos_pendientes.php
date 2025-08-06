<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = intval($_SESSION['gimnasio_id'] ?? 0);
$mensaje = "";

if ($gimnasio_id <= 0) {
    die("Acceso denegado.");
}

// Acción: Aprobar o Rechazar (uso de prepared statements donde aplica)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id > 0 && $accion === 'aprobar') {
        $stmt = $conexion->prepare("SELECT * FROM pagos_pendientes WHERE id = ? AND gimnasio_id = ? LIMIT 1");
        $stmt->bind_param('ii', $id, $gimnasio_id);
        $stmt->execute();
        $pago = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($pago) {
            $plan_id = intval($pago['plan_id']);
            $cliente_id = intval($pago['cliente_id']);
            $total = floatval($pago['monto']);

            $stmt2 = $conexion->prepare("SELECT * FROM planes WHERE id = ? AND gimnasio_id = ? LIMIT 1");
            $stmt2->bind_param('ii', $plan_id, $gimnasio_id);
            $stmt2->execute();
            $plan = $stmt2->get_result()->fetch_assoc();
            $stmt2->close();

            if ($plan) {
                $clases = intval($plan['clases_disponibles']);
                $duracion = intval($plan['duracion_meses']);
                $fecha_inicio = date('Y-m-d');

                // Verificar si el cliente tiene membresía activa
                $stmt3 = $conexion->prepare("SELECT * FROM membresias WHERE cliente_id = ? AND gimnasio_id = ? AND fecha_vencimiento >= CURDATE() ORDER BY fecha_vencimiento DESC LIMIT 1");
                $stmt3->bind_param('ii', $cliente_id, $gimnasio_id);
                $stmt3->execute();
                $membresia_activa = $stmt3->get_result()->fetch_assoc();
                $stmt3->close();

                if ($membresia_activa) {
                    // Renovar membresía existente
                    $nueva_fecha_vencimiento = date('Y-m-d', strtotime($membresia_activa['fecha_vencimiento'] . " +$duracion months"));
                    $nuevas_clases = intval($membresia_activa['clases_disponibles']) + $clases;
                    $nuevo_total = floatval($membresia_activa['total_pagado']) + $total;

                    $upd = $conexion->prepare("UPDATE membresias SET fecha_vencimiento = ?, clases_disponibles = ?, total_pagado = ? WHERE id = ? AND gimnasio_id = ?");
                    $upd->bind_param('siidi', $nueva_fecha_vencimiento, $nuevas_clases, $nuevo_total, $membresia_activa['id'], $gimnasio_id);
                    $upd->execute();
                    $upd->close();
                } else {
                    // Crear nueva membresía
                    $fecha_vencimiento = date('Y-m-d', strtotime("+$duracion months"));

                    $ins = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, total_pagado, metodo_pago, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $metodo = 'Transferencia (comprobante)';
                    $ins->bind_param('iissdssi', $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases, $total, $metodo, $gimnasio_id);
                    $ins->execute();
                    $ins->close();
                }

                // Marcar el pago como aprobado (con control de gimnasio)
                $updPago = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'aprobado' WHERE id = ? AND gimnasio_id = ?");
                $updPago->bind_param('ii', $id, $gimnasio_id);
                $updPago->execute();
                $updPago->close();

                $mensaje = "<p style='color:lime;'>✅ Pago aprobado correctamente.</p>";
            }
        }

    } elseif ($id > 0 && $accion === 'rechazar') {
        $upd = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'rechazado' WHERE id = ? AND gimnasio_id = ?");
        $upd->bind_param('ii', $id, $gimnasio_id);
        $upd->execute();
        $upd->close();
        $mensaje = "<p style='color:red;'>❌ Pago rechazado.</p>";
    }
}

// Consultar pagos pendientes del gimnasio logueado
$stmt_list = $conexion->prepare("SELECT p.*, c.apellido, c.nombre, pl.nombre AS nombre_plan FROM pagos_pendientes p JOIN clientes c ON p.cliente_id = c.id JOIN planes pl ON p.plan_id = pl.id WHERE p.estado = 'pendiente' AND p.gimnasio_id = ? ORDER BY p.fecha_envio DESC");
$stmt_list->bind_param('i', $gimnasio_id);
$stmt_list->execute();
$pagos = $stmt_list->get_result();
$stmt_list->close();
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
<div class="mensaje"><?php echo $mensaje; ?></div>

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
            <td><?php echo htmlspecialchars($p['apellido'] . ', ' . $p['nombre']); ?></td>
            <td><?php echo htmlspecialchars($p['nombre_plan']); ?></td>
            <td>$<?php echo number_format($p['monto'], 2, ',', '.'); ?></td>
            <td><?php echo date('d/m/Y', strtotime($p['fecha_envio'])); ?></td>
            <td>
                <?php if (!empty($p['archivo_comprobante'])): ?>
                    <!-- Enlace seguro al comprobante -->
                    <a href="ver_comprobante.php?id=<?php echo $p['id']; ?>" target="_blank" style="color:deepskyblue;">📄 Ver</a>
                <?php else: ?>
                    Sin archivo
                <?php endif; ?>
            </td>
            <td>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <button class="btn-aprobar" onclick="return confirm('¿Aprobar este pago?')">✅ Aprobar</button>
                </form>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
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
