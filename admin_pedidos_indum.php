<?php
/* admin_pedidos_indum.php — Panel admin pedidos indumentaria */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
// Podés requerir tu menu_admin si lo tenés:
// require_once __DIR__.'/menu_admin.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
@$conexion->set_charset('utf8mb4');

$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($gimnasio_id<=0){ http_response_code(403); exit('Gimnasio no identificado'); }

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES,'UTF-8'); }
function must_p(mysqli $db,string $sql){ $st=$db->prepare($sql); if(!$st) die($db->error); return $st; }

$desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde']??'') ? $_GET['desde'] : date('Y-m-01');
$hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta']??'') ? $_GET['hasta'] : date('Y-m-d');
$estado= trim($_GET['estado'] ?? '');
$pago  = trim($_GET['pago'] ?? '');

$where = "p.gimnasio_id=? AND DATE(p.creado_en) BETWEEN ? AND ?";
$params = [$gimnasio_id,$desde,$hasta]; $types='iss';

if ($estado!==''){ $where.=" AND p.estado=?"; $params[]=$estado; $types.='s'; }
if ($pago!==''){   $where.=" AND p.pago_tipo=?"; $params[]=$pago;   $types.='s'; }

$sql = "
 SELECT p.*, c.nombre AS cliente_nombre
 FROM ind_pedidos p
 LEFT JOIN clientes c ON c.id = p.cliente_id
 WHERE $where
 ORDER BY p.creado_en DESC, p.id DESC
";
$st = must_p($conexion,$sql);
$st->bind_param($types, ...$params);
$st->execute();
$pedidos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* Totales */
$total_facturado = 0.0;     // suma de 'total'
$total_senas     = 0.0;     // suma de 'sena_monto'
$total_cobrado   = 0.0;     // si pago es total_* => total, si es sena_* => sena_monto
foreach($pedidos as $p){
  $total_facturado += (float)$p['total'];
  $total_senas     += (float)$p['sena_monto'];
  $total_cobrado   += str_starts_with($p['pago_tipo']??'', 'total_') ? (float)$p['total'] : (float)$p['sena_monto'];
}

/* Export CSV */
if (isset($_GET['export']) && $_GET['export']==='csv') {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename=pedidos_'.$desde.'_a_'.$hasta.'.csv');
  $out=fopen('php://output','w');
  fputcsv($out, ['ID','Fecha','Cliente','Estado','Pago','Total','Seña','Cobrado','Comprobante']);
  foreach($pedidos as $p){
    $cobrado = str_starts_with($p['pago_tipo'],'total_') ? (float)$p['total'] : (float)$p['sena_monto'];
    fputcsv($out, [
      $p['id'], substr($p['creado_en'],0,19), $p['cliente_nombre']??$p['cliente_id'],
      $p['estado'], $p['pago_tipo'], number_format($p['total'],2,'.',''),
      number_format($p['sena_monto'],2,'.',''), number_format($cobrado,2,'.',''), $p['comprobante_url']
    ]);
  }
  fclose($out); exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>🧾 Pedidos Indumentaria</title>
<style>
  body{font-family:system-ui,Segoe UI,Roboto,Arial;background:#0e1117;color:#fff;margin:0}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  .card{background:#141a2a;border:1px solid #2a3550;border-radius:12px;padding:16px;margin:12px 0}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #2a3550;text-align:left}
  th{background:#0f1628}
  input,select{padding:10px;border-radius:10px;border:1px solid #2a3550;background:#0d1322;color:#fff}
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
  .btn{padding:10px 14px;border:0;border-radius:10px;background:#3b82f6;color:#fff;cursor:pointer;text-decoration:none;display:inline-block}
  .muted{color:#9fb0d3;font-size:12px}
  .right{text-align:right}
  .nowrap{white-space:nowrap}
</style>
</head>
<body>
<div class="wrap">
  <h1>🧾 Pedidos de Indumentaria</h1>

  <div class="card">
    <form class="row">
      <div><label>Desde</label><br><input type="date" name="desde" value="<?=h($desde)?>"></div>
      <div><label>Hasta</label><br><input type="date" name="hasta" value="<?=h($hasta)?>"></div>
      <div><label>Estado</label><br>
        <select name="estado">
          <option value="">Todos</option>
          <option value="pendiente"   <?= $estado==='pendiente'?'selected':''; ?>>Pendiente</option>
          <option value="entregado"   <?= $estado==='entregado'?'selected':''; ?>>Entregado</option>
          <option value="cancelado"   <?= $estado==='cancelado'?'selected':''; ?>>Cancelado</option>
        </select>
      </div>
      <div><label>Pago</label><br>
        <select name="pago">
          <option value="">Todos</option>
          <option value="sena_efectivo"       <?= $pago==='sena_efectivo'?'selected':''; ?>>Seña (Efectivo)</option>
          <option value="total_efectivo"      <?= $pago==='total_efectivo'?'selected':''; ?>>Total (Efectivo)</option>
          <option value="sena_transferencia"  <?= $pago==='sena_transferencia'?'selected':''; ?>>Seña (Transferencia)</option>
          <option value="total_transferencia" <?= $pago==='total_transferencia'?'selected':''; ?>>Total (Transferencia)</option>
        </select>
      </div>
      <div><br><button class="btn">Filtrar</button></div>
      <div><br><a class="btn" href="?desde=<?=h($desde)?>&hasta=<?=h($hasta)?>&estado=<?=h($estado)?>&pago=<?=h($pago)?>&export=csv">⬇️ CSV</a></div>
    </form>
  </div>

  <div class="card">
    <div style="display:flex;gap:18px;flex-wrap:wrap">
      <div><strong>Total facturado:</strong> $<?=number_format($total_facturado,2,',','.')?></div>
      <div><strong>Señas:</strong> $<?=number_format($total_senas,2,',','.')?></div>
      <div><strong>Cobrado (según pago):</strong> $<?=number_format($total_cobrado,2,',','.')?></div>
      <div class="muted">Tip: “Cobrado” suma Total para pagos “total_*” y Seña para “sena_*”.</div>
    </div>
  </div>

  <div class="card">
    <?php if (empty($pedidos)): ?>
      <p class="muted">Sin pedidos en el rango.</p>
    <?php else: ?>
      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Fecha</th><th>Cliente</th><th>Estado</th><th>Pago</th>
              <th class="right">Total</th><th class="right">Seña</th><th>Comp.</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($pedidos as $p): ?>
              <tr>
                <td class="nowrap"><?= (int)$p['id'] ?></td>
                <td class="nowrap"><?= h(substr((string)$p['creado_en'],0,19)) ?></td>
                <td><?= h($p['cliente_nombre'] ?: ('#'.$p['cliente_id'])) ?></td>
                <td><?= h($p['estado']) ?></td>
                <td><?= h(str_replace('_',' ',$p['pago_tipo'])) ?></td>
                <td class="right">$<?= number_format($p['total'],2,',','.') ?></td>
                <td class="right">$<?= number_format($p['sena_monto'],2,',','.') ?></td>
                <td>
                  <?php if (!empty($p['comprobante_url'])): ?>
                    <a class="btn" style="background:#475569" target="_blank" href="<?=h($p['comprobante_url'])?>">Ver</a>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td class="nowrap">
                  <a class="btn" href="factura_pedido.php?id=<?= (int)$p['id'] ?>">🧾 Imprimir</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
