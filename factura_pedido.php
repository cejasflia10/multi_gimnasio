<?php
/* factura_pedido.php — Vista imprimible (guardar como PDF) */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
$pedido_id   = (int)($_GET['id'] ?? 0);
if ($pedido_id<=0){ http_response_code(400); exit('Falta id'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function must_p(mysqli $db,string $sql){ $st=$db->prepare($sql); if(!$st) die($db->error); return $st; }

$sql = "SELECT p.*, c.nombre AS cliente_nombre, c.apellido AS cliente_apellido
        FROM ind_pedidos p
        LEFT JOIN clientes c ON c.id=p.cliente_id
        WHERE p.id=? AND p.gimnasio_id=? LIMIT 1";
$st = must_p($conexion,$sql);
$st->bind_param('ii',$pedido_id,$gimnasio_id);
$st->execute();
$pedido = $st->get_result()->fetch_assoc();
$st->close();
if (!$pedido){ http_response_code(404); exit('Pedido no encontrado'); }

$sti = must_p($conexion,"SELECT * FROM ind_pedidos_items WHERE pedido_id=? ORDER BY id");
$sti->bind_param('i',$pedido_id);
$sti->execute();
$items = $sti->get_result()->fetch_all(MYSQLI_ASSOC);
$sti->close();

$fecha = $pedido['creado_en'] ? substr($pedido['creado_en'],0,19) : date('Y-m-d H:i:s');
$cliente = trim(($pedido['cliente_nombre']??'').' '.($pedido['cliente_apellido']??''));
$pago = str_replace('_',' ', (string)$pedido['pago_tipo']);
$total = (float)$pedido['total'];
$sena  = (float)$pedido['sena_monto'];
$cobrado = str_starts_with($pedido['pago_tipo']??'','total_') ? $total : $sena;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pedido #<?= (int)$pedido_id ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @media print { .no-print{display:none} }
  body{font-family:system-ui,Segoe UI,Roboto,Arial;color:#111;margin:0;padding:20px;background:#fff}
  .head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
  .brand h1{margin:0;font-size:20px}
  .muted{color:#555;font-size:12px}
  table{width:100%;border-collapse:collapse;margin-top:12px}
  th,td{padding:8px;border-bottom:1px solid #ddd;text-align:left}
  .right{text-align:right}
  .totals{margin-top:12px;max-width:380px;margin-left:auto}
  .btn{padding:10px 12px;border:1px solid #666;border-radius:8px;background:#f6f6f6;cursor:pointer}
</style>
</head>
<body>
<div class="no-print" style="text-align:right">
  <button class="btn" onclick="window.print()">🧾 Imprimir / Guardar como PDF</button>
</div>

<div class="head">
  <div class="brand">
    <h1>Comprobante de Pedido</h1>
    <div class="muted">Pedido #<?= (int)$pedido_id ?> · Fecha: <?= h($fecha) ?></div>
  </div>
  <div class="info">
    <div><strong>Cliente:</strong> <?= h($cliente ?: ('ID '.$pedido['cliente_id'])) ?></div>
    <div><strong>Pago:</strong> <?= h($pago) ?></div>
    <div><strong>Estado:</strong> <?= h($pedido['estado']) ?></div>
  </div>
</div>

<table>
  <thead>
    <tr><th>Producto</th><th>Talle</th><th class="right">Cant.</th><th class="right">Precio</th><th class="right">Subtotal</th></tr>
  </thead>
  <tbody>
    <?php foreach($items as $it): $st = (float)$it['precio'] * (int)$it['cantidad']; ?>
      <tr>
        <td><?= h($it['titulo']) ?></td>
        <td><?= h($it['talle']) ?></td>
        <td class="right"><?= (int)$it['cantidad'] ?></td>
        <td class="right">$<?= number_format($it['precio'],2,',','.') ?></td>
        <td class="right">$<?= number_format($st,2,',','.') ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="totals">
  <table>
    <tr><td><strong>Total</strong></td><td class="right">$<?= number_format($total,2,',','.') ?></td></tr>
    <tr><td>Seña</td><td class="right">$<?= number_format($sena,2,',','.') ?></td></tr>
    <tr><td><strong>Cobrado</strong></td><td class="right"><strong>$<?= number_format($cobrado,2,',','.') ?></strong></td></tr>
  </table>
  <?php if (!empty($pedido['comprobante_url'])): ?>
    <div class="muted" style="margin-top:6px">Comprobante: <a href="<?= h($pedido['comprobante_url']) ?>" target="_blank">ver archivo</a></div>
  <?php endif; ?>
</div>
</body>
</html>
