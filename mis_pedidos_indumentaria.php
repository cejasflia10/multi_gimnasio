<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id<=0){ header('Location: login.php'); exit; }

function h($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}

$ped=[]; $st=$conexion->prepare("SELECT * FROM ind_pedidos WHERE gimnasio_id=? AND cliente_id=? ORDER BY id DESC");
$st->bind_param('ii',$gimnasio_id,$cliente_id); $st->execute();
$ped=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$items=[]; if(!empty($ped)){
  $ids=array_column($ped,'id'); $in=implode(',',array_fill(0,count($ids),'?')); $types=str_repeat('i',count($ids));
  $sql="SELECT * FROM ind_pedido_items WHERE pedido_id IN ($in) ORDER BY pedido_id";
  $stm=$conexion->prepare($sql); $stm->bind_param($types,...$ids); $stm->execute();
  $rs=$stm->get_result(); while($r=$rs->fetch_assoc()){ $items[$r['pedido_id']][]=$r; } $stm->close();
}
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mis pedidos</title>
<style>
 body{font-family:system-ui,Segoe UI,Roboto,Arial;background:#0f1320;color:#fff;margin:0}
 .wrap{max-width:900px;margin:24px auto;padding:16px}
 .card{background:#141a2a;border:1px solid #24314d;border-radius:14px;padding:16px;margin-bottom:14px}
 table{width:100%;border-collapse:collapse} th,td{padding:8px;border-bottom:1px solid #24314d}
 a{color:#93c5fd}
 .mini{font-size:12px;color:#9fb0d3}
</style>
</head><body><div class="wrap">
  <h1>🧾 Mis pedidos</h1>
  <?php foreach($ped as $p): ?>
    <div class="card">
      <div><strong>#<?=$p['id']?></strong> — Total: $<?=number_format($p['total'],2,',','.')?> — Pago: <?=h($p['estado_pago'])?> <?=h($p['metodo_pago']??'')?></div>
      <div class="mini">Fecha: <?=date('d/m/Y H:i', strtotime($p['creado_en']))?> <?php if($p['comprobante_url']): ?> — <a href="<?=h($p['comprobante_url'])?>" target="_blank">Comprobante</a><?php endif; ?></div>
      <table style="margin-top:8px">
        <thead><tr><th>Producto</th><th>Talle</th><th>Sugerido</th><th>Medidas</th><th>Cant.</th><th>PU</th><th>Subt.</th></tr></thead>
        <tbody>
          <?php foreach(($items[$p['id']]??[]) as $it): ?>
          <tr>
            <td>#<?=$it['producto_id']?></td>
            <td><?=h($it['talle'])?></td>
            <td class="mini"><?=h($it['talle_sugerido']??'')?></td>
            <td class="mini"><?=h($it['medidas_json']??'')?></td>
            <td><?=$it['cantidad']?></td>
            <td>$<?=number_format($it['precio_unit'],2,',','.')?></td>
            <td>$<?=number_format($it['subtotal'],2,',','.')?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; if(empty($ped)): ?>
    <p class="mini">Sin pedidos todavía.</p>
  <?php endif; ?>
</div></body></html>
