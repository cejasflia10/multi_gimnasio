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
            $total      = (float)$pago['monto'];
            $fecha_hoy  = date('Y-m-d');

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

                $conexion->begin_transaction();
                try {
                    if (!empty($membresia)) {
                        $nueva_fecha_venc = date('Y-m-d', strtotime($membresia['fecha_vencimiento']." +{$duracion} months"));
                        $nuevas_clases    = (int)$membresia['clases_disponibles'] + $clases;
                        $nuevo_total      = (float)$membresia['total_pagado'] + $total;

                        $upd = $conexion->prepare("
                            UPDATE membresias
                               SET fecha_vencimiento = ?, clases_disponibles = ?, total_pagado = ?
                             WHERE id = ? AND gimnasio_id = ?
                        ");
                        if (!$upd) { throw new Exception("Error preparando UPDATE membresías: ".$conexion->error); }
                        $upd->bind_param('sidii', $nueva_fecha_venc, $nuevas_clases, $nuevo_total, $membresia['id'], $gimnasio_id);
                        if (!$upd->execute()) { throw new Exception("Error ejecutando UPDATE membresías: ".$upd->error); }
                        $upd->close();
                    } else {
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
                        $ins->bind_param('iissidsi',
                            $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases, $total, $metodo, $gimnasio_id
                        );
                        if (!$ins->execute()) { throw new Exception("Error ejecutando INSERT membresías: ".$ins->error); }
                        $ins->close();
                    }

                    $updPago = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'aprobado' WHERE id = ? AND gimnasio_id = ?");
                    if (!$updPago) { throw new Exception("Error preparando UPDATE pagos_pendientes: ".$conexion->error); }
                    $updPago->bind_param('ii', $id, $gimnasio_id);
                    if (!$updPago->execute()) { throw new Exception("Error ejecutando UPDATE pagos_pendientes: ".$updPago->error); }
                    $updPago->close();

                    $conexion->commit();
                    $mensaje = "<p class='msg ok'>✅ Pago aprobado y membresía actualizada.</p>";
                } catch (Throwable $e) {
                    $conexion->rollback();
                    $mensaje = "<p class='msg err'>❌ No se pudo aprobar: ".h($e->getMessage())."</p>";
                }
            }
        }
    } elseif ($id > 0 && $accion === 'rechazar') {
        $upd = $conexion->prepare("UPDATE pagos_pendientes SET estado = 'rechazado' WHERE id = ? AND gimnasio_id = ?");
        if ($upd) {
            $upd->bind_param('ii', $id, $gimnasio_id);
            $upd->execute();
            $upd->close();
            $mensaje = "<p class='msg err'>❌ Pago rechazado.</p>";
        } else {
            $mensaje = "<p class='msg err'>❌ Error rechazando: ".h($conexion->error)."</p>";
        }
    }
}

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  /* ====== NADA de forzar negro/oro: dejamos que mande el tema del index ====== */

  .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }
  .page-card{
    background:var(--card); border:1px solid var(--stroke);
    border-radius:18px; box-shadow:var(--shadow); padding:16px;
  }
  .page-title{
    margin:0 0 12px 0; text-align:center; font-weight:900; letter-spacing:.4px;
    background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
    -webkit-background-clip:text; background-clip:text; color:transparent;
  }

  .msg{ text-align:center; margin:8px 0 14px; font-weight:700; }
  .msg.ok{ color:#16a34a; }
  .msg.err{ color:#b91c1c; }

  .toolbar{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; justify-content:center; margin-bottom:12px; }

  /* ====== Tabla “tipo index” ====== */
  .table-wrap{ width:100%; overflow-x:auto; -webkit-overflow-scrolling:touch; }
  table.tabla{ width:100%; min-width:900px; border-collapse:collapse; background:#fff; }
  .tabla thead th{
    position:sticky; top:0; z-index:1; background:#f7fafc; color:#0f172a;
    border-bottom:1px solid var(--stroke);
  }
  .tabla th, .tabla td{
    padding:10px 12px; border-bottom:1px solid var(--stroke); text-align:center; white-space:nowrap;
  }
  .tabla tbody tr:hover{ background:#f9fafb; }

  .acciones{ display:flex; gap:8px; align-items:center; justify-content:center; }

  .btn{
    background:linear-gradient(180deg,#fff,#f7fafc);
    border:1px solid var(--stroke); border-radius:10px;
    color:var(--ink); padding:8px 12px; font-weight:700; cursor:pointer;
    text-decoration:none; display:inline-block;
  }
  .btn:hover{ box-shadow:0 6px 16px rgba(2,6,23,.06); }
  .btn-aprobar{ border-color:rgba(22,163,74,.35); }
  .btn-rechazar{ border-color:rgba(239,68,68,.35); }

  .muted{ color:#64748b; }
</style>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <h2 class="page-title">📥 Pagos Pendientes</h2>
      <div class="mensaje"><?= $mensaje ?></div>

      <?php if ($pagos && $pagos->num_rows > 0): ?>
      <div class="table-wrap">
        <table class="tabla">
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
              <td style="text-align:right"><?= money($p['monto']) ?></td>
              <td><?= h(date('d/m/Y', strtotime($p['fecha_envio']))) ?></td>
              <td>
                <?php if (!empty($p['archivo_comprobante'])): ?>
                  <a class="link-inline" href="ver_comprobante.php?id=<?= (int)$p['id'] ?>" target="_blank">📄 Ver</a>
                <?php else: ?>
                  <span class="muted">Sin archivo</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="acciones">
                  <form method="post" onsubmit="return confirm('¿Aprobar este pago?')" style="display:inline;">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="accion" value="aprobar">
                    <button class="btn btn-aprobar" type="submit">✅ Aprobar</button>
                  </form>
                  <form method="post" onsubmit="return confirm('¿Rechazar este pago?')" style="display:inline;">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="accion" value="rechazar">
                    <button class="btn btn-rechazar" type="submit">❌ Rechazar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <p class="muted" style="text-align:center;">No hay pagos pendientes.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
