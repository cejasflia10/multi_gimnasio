<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['evento_usuario_id'])) { header('Location: login_evento.php'); exit; }

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('evento_id requerido'); }

/* Migración suave: columna origen en pedidos */
if (!has_col($conexion,'pedidos','origen')) {
  @$conexion->query("ALTER TABLE pedidos ADD COLUMN origen ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total");
}

/* Totales por origen/estado */
$tot = ['online'=>['pagado'=>0,'pendiente'=>0,'cancelado'=>0,'monto'=>0.0],'taquilla'=>['pagado'=>0,'pendiente'=>0,'cancelado'=>0,'monto'=>0.0]];
$st=$conexion->prepare("SELECT origen, estado, COUNT(*) c, COALESCE(SUM(total),0) s FROM pedidos WHERE evento_id=? GROUP BY origen, estado");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result();
while($row=$r->fetch_assoc()){
  $o = $row['origen']?:'online'; $e = $row['estado']?:'pagado';
  $tot[$o][$e] = (int)$row['c']; if ($e==='pagado') $tot[$o]['monto'] += (float)$row['s'];
}
$st->close();

/* Listado de pedidos */
$st=$conexion->prepare("SELECT id, comprador_nombre, comprador_email, total, estado, origen, created_at FROM pedidos WHERE evento_id=? ORDER BY id DESC LIMIT 300");
$st->bind_param('i',$evento_id); $st->execute(); $pedidos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

/* Alias bancario si está configurado */
$cfg=['alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null];
$st=$conexion->prepare("SELECT alias_bancario,titular_banco,banco_nombre,nota FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ventas — Evento #<?= (int)$evento_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{margin:0;background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1100px;margin:20px auto;padding:16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
    .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
    table{width:100%;border-collapse:collapse}
    th,td{border-bottom:1px solid #1c2a36;padding:8px;text-align:left}
    th{color:#9ecbff}
    .pill{display:inline-block;padding:4px 8px;border-radius:999px;border:1px solid #3b4b5a;font-size:12px;margin-right:6px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <h2 style="margin:0 0 8px">📊 Ventas — Evento #<?= (int)$evento_id ?></h2>
    <div style="margin-bottom:10px">
      <a class="btn" style="background:#1b2836;border-color:#2b3c4f" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver</a>
    </div>

    <div class="grid">
      <div class="card">
        <h3 style="margin:0 0 6px">Online</h3>
        <div class="pill">Pagados: <?= (int)$tot['online']['pagado'] ?></div>
        <div class="pill">Pendientes: <?= (int)$tot['online']['pendiente'] ?></div>
        <div class="pill">Cancelados: <?= (int)$tot['online']['cancelado'] ?></div>
        <div style="margin-top:6px"><b>Total recaudado:</b> $ <?= number_format((float)$tot['online']['monto'],2,',','.') ?></div>
        <?php if(!empty($cfg['alias_bancario'])): ?>
          <div style="margin-top:10px">
            <b>Alias bancario:</b> <?= h($cfg['alias_bancario']) ?><br>
            <?php if(!empty($cfg['titular_banco'])): ?><span>Titular: <?= h($cfg['titular_banco']) ?></span><br><?php endif; ?>
            <?php if(!empty($cfg['banco_nombre'])): ?><span>Banco: <?= h($cfg['banco_nombre']) ?></span><?php endif; ?>
          </div>
          <?php if(!empty($cfg['nota'])): ?><div style="margin-top:6px"><?= nl2br(h($cfg['nota'])) ?></div><?php endif; ?>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3 style="margin:0 0 6px">Taquilla</h3>
        <div class="pill">Pagados: <?= (int)$tot['taquilla']['pagado'] ?></div>
        <div class="pill">Pendientes: <?= (int)$tot['taquilla']['pendiente'] ?></div>
        <div class="pill">Cancelados: <?= (int)$tot['taquilla']['cancelado'] ?></div>
        <div style="margin-top:6px"><b>Total recaudado:</b> $ <?= number_format((float)$tot['taquilla']['monto'],2,',','.') ?></div>
      </div>
    </div>

    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Últimos movimientos</h3>
      <div style="overflow-x:auto">
        <table>
          <thead>
            <tr>
              <th># Venta</th>
              <th>Fecha</th>
              <th>Origen</th>
              <th>Estado</th>
              <th>Comprador</th>
              <th>Total</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$pedidos): ?>
            <tr><td colspan="7" style="color:#9ecbff">Sin ventas.</td></tr>
          <?php else: foreach($pedidos as $p): ?>
            <tr>
              <td><?= sprintf('PED-%06d',(int)$p['id']) ?></td>
              <td><?= h((string)$p['created_at']) ?></td>
              <td><?= h((string)$p['origen']) ?></td>
              <td><?= h((string)$p['estado']) ?></td>
              <td><?= h((string)$p['comprador_nombre']) ?> <span style="color:#9ecbff">/ <?= h((string)$p['comprador_email']) ?></span></td>
              <td>$ <?= number_format((float)$p['total'],2,',','.') ?></td>
              <td>
                <a class="btn" href="compra_ok.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank">Ver</a>
                <a class="btn" href="qr_evento.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank">PDF</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</body>
</html>
