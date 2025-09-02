<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__.'/conexion.php';
require __DIR__.'/menu_horizontal.php';

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$mensaje = "";

if ($gimnasio_id <= 0) { http_response_code(403); die("Acceso denegado."); }

@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return '$'.number_format((float)$n, 2, ',', '.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $accion = $_POST['accion'] ?? '';

    if ($id > 0 && $accion === 'aprobar') {

        // --- Traer pago pendiente ---
        $stmt = $conexion->prepare("SELECT * FROM pagos_pendientes WHERE id = ? AND gimnasio_id = ? LIMIT 1");
        if (!$stmt) { $mensaje = "❌ Error preparando pago: ".$conexion->error; }
        else {
            $stmt->bind_param('ii', $id, $gimnasio_id);
            $stmt->execute();
            $pago = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!empty($pago)) {
            $plan_id    = (int)$pago['plan_id'];
            $cliente_id = (int)$pago['cliente_id'];
            $total      = (float)$pago['monto']; // monto cobrado
            $fecha_hoy  = date('Y-m-d');

            // --- Traer plan ---
            $stmt2 = $conexion->prepare("SELECT * FROM planes WHERE id = ? AND gimnasio_id = ? LIMIT 1");
            if (!$stmt2) { $mensaje = "❌ Error preparando plan: ".$conexion->error; }
            else {
                $stmt2->bind_param('ii', $plan_id, $gimnasio_id);
                $stmt2->execute();
                $plan = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();
            }

            if (!empty($plan)) {
                $clases   = (int)$plan['clases_disponibles'];
                $duracion = (int)$plan['duracion_meses'];

                // --- Buscar membresía activa (vence hoy o después) ---
                $stmt3 = $conexion->prepare("
                    SELECT * FROM membresias
                    WHERE cliente_id = ? AND gimnasio_id = ? AND fecha_vencimiento >= CURDATE()
                    ORDER BY fecha_vencimiento DESC
                    LIMIT 1
                ");
                if (!$stmt3) { $mensaje = "❌ Error preparando membresía: ".$conexion->error; }
                else {
                    $stmt3->bind_param('ii', $cliente_id, $gimnasio_id);
                    $stmt3->execute();
                    $membresia = $stmt3->get_result()->fetch_assoc();
                    $stmt3->close();
                }

                // --- Transacción para coherencia ---
                $conexion->begin_transaction();
                try {
                    if (!empty($membresia)) {
                        // Renovar membresía existente
                        $nueva_fecha_venc = date('Y-m-d', strtotime($membresia['fecha_vencimiento']." +{$duracion} months"));
                        $nuevas_clases    = (int)$membresia['clases_disponibles'] + $clases;
                        $nuevo_total      = (float)$membresia['total_pagado'] + $total;

                        $upd = $conexion->prepare("
                            UPDATE membresias
                               SET fecha_vencimiento = ?, clases_disponibles = ?, total_pagado = ?
                             WHERE id = ? AND gimnasio_id = ?
                        ");
                        if (!$upd) { throw new Exception("Error preparando UPDATE membresías: ".$conexion->error); }

                        // TIPOS CORRECTOS: s (fecha), i (clases), d (total), i (id), i (gimnasio)
                        $upd->bind_param('sidii', $nueva_fecha_venc, $nuevas_clases, $nuevo_total, $membresia['id'], $gimnasio_id);
                        if (!$upd->execute()) { throw new Exception("Error ejecutando UPDATE membresías: ".$upd->error); }
                        $upd->close();
                    } else {
                        // Crear nueva membresía
                        $fecha_inicio      = $fecha_hoy;
                        $fecha_vencimiento = date('Y-m-d', strtotime("+{$duracion} months"));
                        $metodo            = 'Transferencia (comprobante)';

                        $ins = $conexion->prepare("
                            INSERT INTO membresias
                                (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, total_pagado, metodo_pago, gimnasio_id)
                            VALUES
                                (?,          ?,       ?,            ?,                 ?,                 ?,            ?,           ?)
                        ");
                        if (!$ins) { throw new Exception("Error preparando INSERT membresías: ".$conexion->error); }

                        // TIPOS CORRECTOS: i (cliente), i (plan), s (ini), s (venc), i (clases), d (total), s (metodo), i (gimnasio)
                        $ins->bind_param('iissidsi',
                            $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases, $total, $metodo, $gimnasio_id
                        );
                        if (!$ins->execute()) { throw new Exception("Error ejecutando INSERT membresías: ".$ins->error); }
                        $ins->close();
                    }

                    // Marcar pago como aprobado
                    $updPago = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'aprobado' WHERE id = ? AND gimnasio_id = ?");
                    if (!$updPago) { throw new Exception("Error preparando UPDATE pagos_pendientes: ".$conexion->error); }
                    $updPago->bind_param('ii', $id, $gimnasio_id);
                    if (!$updPago->execute()) { throw new Exception("Error ejecutando UPDATE pagos_pendientes: ".$updPago->error); }
                    $updPago->close();

                    $conexion->commit();
                    $mensaje = "<p style='color:lime;'>✅ Pago aprobado y membresía actualizada.</p>";
                } catch (Throwable $e) {
                    $conexion->rollback();
                    $mensaje = "<p style='color:red;'>❌ No se pudo aprobar: ".h($e->getMessage())."</p>";
                }
            }
        }

    } elseif ($id > 0 && $accion === 'rechazar') {
        $upd = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'rechazado' WHERE id = ? AND gimnasio_id = ?");
        if ($upd) {
            $upd->bind_param('ii', $id, $gimnasio_id);
            $upd->execute();
            $upd->close();
            $mensaje = "<p style='color:red;'>❌ Pago rechazado.</p>";
        } else {
            $mensaje = "<p style='color:red;'>❌ Error rechazando: ".h($conexion->error)."</p>";
        }
    }
}

// --- Listado de pagos pendientes ---
$stmt_list = $conexion->prepare("
    SELECT p.*, c.apellido, c.nombre, pl.nombre AS nombre_plan
    FROM pagos_pendientes p
    JOIN clientes c ON p.cliente_id = c.id
    JOIN planes pl   ON p.plan_id   = pl.id
    WHERE p.estado = 'pendiente' AND p.gimnasio_id = ?
    ORDER BY p.fecha_envio DESC
");
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
  body { background:#111; color:gold; font-family:Arial; padding:20px; }
  table { width:100%; border-collapse:collapse; margin-top:20px; background:#222; }
  th, td { border:1px solid gold; padding:10px; text-align:center; }
  th { background:#333; }
  button { padding:6px 12px; border:none; font-weight:bold; cursor:pointer; }
  .btn-aprobar { background:limegreen; color:black; }
  .btn-rechazar{ background:crimson; color:white; }
  .mensaje { text-align:center; font-size:18px; }
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
      <td><?= h($p['apellido'].', '.$p['nombre']) ?></td>
      <td><?= h($p['nombre_plan']) ?></td>
      <td><?= money($p['monto']) ?></td>
      <td><?= h(date('d/m/Y', strtotime($p['fecha_envio']))) ?></td>
      <td>
        <?php if (!empty($p['archivo_comprobante'])): ?>
          <a href="ver_comprobante.php?id=<?= (int)$p['id'] ?>" target="_blank" style="color:deepskyblue;">📄 Ver</a>
        <?php else: ?>
          <span class="muted">Sin archivo</span>
        <?php endif; ?>
      </td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="accion" value="aprobar">
          <button class="btn-aprobar" onclick="return confirm('¿Aprobar este pago?')">✅ Aprobar</button>
        </form>
        <form method="post" style="display:inline;">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="accion" value="rechazar">
          <button class="btn-rechazar" onclick="return confirm('¿Rechazar este pago?')">❌ Rechazar</button>
        </form>
      </td>
    </tr>
  <?php endwhile; ?>
  </tbody>
</table>
<?php else: ?>
  <p style="text-align:center; color:orange;">No hay pagos pendientes.</p>
<?php endif; ?>

</body>
</html>
