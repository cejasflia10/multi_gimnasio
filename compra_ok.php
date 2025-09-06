<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$pedido_id = isset($_GET['pedido_id']) ? (int)$_GET['pedido_id'] : 0;
if ($pedido_id<=0){ http_response_code(400); exit('Falta pedido_id'); }

$sql="SELECT
        p.id,p.evento_id,p.comprador_nombre,p.comprador_email,p.comprador_tel,p.total,p.metodo_pago,
        p.alias_usado,p.cuenta_destino,p.comprobante_path,p.estado,p.created_at,
        e.titulo AS evento_titulo, e.fecha AS evento_fecha, e.hora AS evento_hora, e.lugar AS evento_lugar
      FROM pedidos p
      LEFT JOIN eventos_deportivos e ON e.id=p.evento_id
      WHERE p.id=? LIMIT 1";
$st=$conexion->prepare($sql);
if(!$st){ http_response_code(500); exit('SQL prepare error: '.$conexion->error); }
$st->bind_param('i',$pedido_id); $st->execute(); $p=$st->get_result()->fetch_assoc(); $st->close();
if(!$p){ http_response_code(404); exit('Pedido no encontrado'); }

/* Tickets (si emitidos tras aprobación) */
$st=$conexion->prepare("SELECT t.id, t.code, tt.nombre AS tipo FROM tickets t LEFT JOIN tickets_tipos tt ON tt.id=t.tipo_id WHERE t.pedido_id=? ORDER BY t.id ASC");
$st->bind_param('i',$pedido_id); $st->execute(); $tickets=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$aprobado = in_array((string)$p['estado'], ['aprobado','pagado'], true);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pedido #<?= (int)$pedido_id ?></title>
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  body{background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
  .wrap{max-width:880px;margin:18px auto;padding:14px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
  .muted{color:#9ecbff}
  .ok{background:#0f251b;border:1px solid #164b31;color:#b6f3d1;border-radius:10px;padding:10px;margin:8px 0}
  .warn{background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;border-radius:10px;padding:10px;margin:8px 0}
  .btn{padding:8px 12px;border-radius:8px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
  table{width:100%;border-collapse:collapse;margin-top:8px}
  th,td{border-bottom:1px solid #1c2a36;padding:8px;text-align:left}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 6px">🧾 Pedido #<?= (int)$pedido_id ?> — <?= h($p['evento_titulo'] ?? 'Evento') ?></h2>
    <div class="muted">Estado: <b><?= h($p['estado']) ?></b> · Total: $<?= number_format((float)$p['total'],2,',','.') ?> · <?= h($p['created_at']) ?></div>

    <?php if(isset($_SESSION['ok_msg'])): ?><div class="ok"><?= h($_SESSION['ok_msg']); unset($_SESSION['ok_msg']); ?></div><?php endif; ?>
    <?php if(isset($_SESSION['flash_error'])): ?><div class="warn"><?= h($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div><?php endif; ?>

    <?php if(!$aprobado): ?>
      <div class="warn">Tu solicitud está <b>pendiente</b>. El organizador revisará tu comprobante.<br>Te enviaremos las entradas (PDF con QR) a <b><?= h($p['comprador_email']) ?></b> cuando se apruebe.</div>
      <?php if(!empty($p['comprobante_path'])): ?>
        <p class="muted">Comprobante subido: <a class="btn" href="<?= h($p['comprobante_path']) ?>" target="_blank">Ver</a></p>
      <?php endif; ?>
    <?php else: ?>
      <div class="ok">✅ ¡Aprobado! Abajo podés descargar tus entradas (PDF con QR).</div>
      <?php if($tickets): ?>
        <table>
          <thead><tr><th>#</th><th>Tipo</th><th>Código</th><th>QR/PDF</th></tr></thead>
          <tbody>
          <?php foreach($tickets as $t): ?>
            <tr>
              <td><?= (int)$t['id'] ?></td>
              <td><?= h($t['tipo'] ?? '-') ?></td>
              <td><code><?= h($t['code']) ?></code></td>
              <td><a class="btn" href="ticket_pdf.php?code=<?= urlencode($t['code']) ?>" target="_blank">📄 PDF</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted">Aprobado pero aún sin emisión. Actualizá en unos segundos.</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
