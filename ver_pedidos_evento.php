<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { die('Sin BD'); }
@$conexion->set_charset('utf8mb4');
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$evento_id = isset($_GET['evento_id'])?(int)$_GET['evento_id']:0;
if ($evento_id<=0) die('Falta evento_id');

$ev = $conexion->query("SELECT id,titulo FROM eventos_deportivos WHERE id=".$evento_id)->fetch_assoc();
if(!$ev) die('Evento no existe');

$pedidos = [];
$sql="SELECT p.id,p.comprador_nombre,p.comprador_email,p.total,p.metodo_pago,p.comprobante_path,p.estado,p.created_at
      FROM pedidos p WHERE p.evento_id=? ORDER BY p.id DESC";
$st=$conexion->prepare($sql); $st->bind_param('i',$evento_id);
$st->execute(); $pedidos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><title>Pedidos — <?= h($ev['titulo']) ?></title>
<style>body{font-family:system-ui;margin:20px} table{border-collapse:collapse;width:100%} th,td{border:1px solid #ddd;padding:8px} .btn{padding:6px 10px;background:#0e7ad1;color:#fff;text-decoration:none;border-radius:6px} .pill{padding:3px 6px;border:1px solid #999;border-radius:999px}</style>
</head><body>
<h2>🧾 Pedidos — <?= h($ev['titulo']) ?></h2>
<p><a class="btn" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a></p>
<table>
  <thead><tr><th>#</th><th>Comprador</th><th>Importe</th><th>Método</th><th>Comprobante</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
  <tbody>
  <?php if(!$pedidos): ?>
    <tr><td colspan="8">Sin pedidos</td></tr>
  <?php else: foreach($pedidos as $p): ?>
    <tr>
      <td><?= (int)$p['id'] ?></td>
      <td><?= h($p['comprador_nombre']).'<br><small>'.h($p['comprador_email']).'</small>' ?></td>
      <td>$<?= number_format((float)$p['total'],2,'.','') ?></td>
      <td><?= h($p['metodo_pago']) ?></td>
      <td><?php if(!empty($p['comprobante_path'])): ?><a class="btn" href="<?= h($p['comprobante_path']) ?>" target="_blank">Ver</a><?php endif; ?></td>
      <td><span class="pill"><?= h($p['estado']) ?></span></td>
      <td><?= h($p['created_at']) ?></td>
      <td>
        <a class="btn" href="ver_pedido_detalle.php?pedido_id=<?= (int)$p['id'] ?>">Detalle</a>
        <?php if($p['estado']==='pendiente'): ?>
          <a class="btn" href="aprobar_pedido.php?pedido_id=<?= (int)$p['id'] ?>" onclick="return confirm('Aprobar y emitir entradas?')">Aprobar</a>
          <a class="btn" style="background:#a33" href="rechazar_pedido.php?pedido_id=<?= (int)$p['id'] ?>" onclick="return confirm('Rechazar pedido?')">Rechazar</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</body></html>
