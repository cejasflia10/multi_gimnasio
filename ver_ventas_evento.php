<?php
if (session_status() === PHP_SESSION_NONE) session_start();

/* Guardia de sesión con return_to */
if (empty($_SESSION['evento_usuario_id'])) {
  $return_to = $_SERVER['REQUEST_URI'] ?? 'ver_ventas_evento.php';
  header('Location: login_evento.php?return_to=' . urlencode($return_to));
  exit;
}

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
function money($n){ return number_format((float)$n, 2, ',', '.'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* Entrada */
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('evento_id requerido'); }

/* Traer info del evento (título, fecha) */
$evento = null;
if ($st=$conexion->prepare("SELECT id, titulo, fecha FROM eventos_deportivos WHERE id=? LIMIT 1")){
  $st->bind_param('i',$evento_id); $st->execute();
  $evento = $st->get_result()->fetch_assoc();
  $st->close();
}
if (!$evento){ http_response_code(404); exit('Evento no encontrado.'); }

/* Migración suave: columna origen en pedidos */
if (!has_col($conexion,'pedidos','origen')) {
  @$conexion->query("ALTER TABLE pedidos ADD COLUMN origen ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total");
}

/* Totales por origen/estado */
$tot = [
  'online'  => ['pagado'=>0,'pendiente'=>0,'cancelado'=>0,'monto'=>0.0],
  'taquilla'=> ['pagado'=>0,'pendiente'=>0,'cancelado'=>0,'monto'=>0.0]
];

if ($st=$conexion->prepare("
  SELECT origen, estado, COUNT(*) c, COALESCE(SUM(total),0) s
  FROM pedidos
  WHERE evento_id=?
  GROUP BY origen, estado
")){
  $st->bind_param('i',$evento_id); $st->execute();
  $r=$st->get_result();
  while($row=$r->fetch_assoc()){
    $o = ($row['origen']==='taquilla') ? 'taquilla' : 'online';
    $e = (string)($row['estado'] ?? 'pendiente');
    $tot[$o][$e] = (int)$row['c'];
    if ($e==='pagado') $tot[$o]['monto'] += (float)$row['s'];
  }
  $st->close();
}

/* Listado de pedidos (últimos 300) */
$pedidos = [];
if ($st=$conexion->prepare("
  SELECT id, comprador_nombre, comprador_email, total, estado, origen, created_at
  FROM pedidos
  WHERE evento_id=?
  ORDER BY id DESC
  LIMIT 300
")){
  $st->bind_param('i',$evento_id); $st->execute();
  $pedidos=$st->get_result()->fetch_all(MYSQLI_ASSOC);
  $st->close();
}

/* Alias bancario si está configurado */
$cfg=['alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null];
if ($st=$conexion->prepare("
  SELECT alias_bancario, titular_banco, banco_nombre, nota
  FROM eventos_pagos_config
  WHERE evento_id=?
  LIMIT 1
")){
  $st->bind_param('i',$evento_id); $st->execute();
  $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); }
  $st->close();
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Ventas — <?= h($evento['titulo']) ?> (Evento #<?= (int)$evento_id ?>)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <style>
    :root{
      --bg:#0a0a0a; --fg:#f6f6f6; --mut:#c9c9c9; --brand:#d4af37;
      --card:#111; --bd:#222; --line:#222;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1;
    }
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    a:focus{outline:2px dashed var(--brand); outline-offset:2px}

    .wrap{max-width:1100px;margin:20px auto;padding:16px}
    h2{margin:0 0 8px}
    .mut{color:var(--mut)}

    .btn{
      display:inline-flex;align-items:center;gap:.45rem;
      padding:.58rem .9rem;border-radius:10px;border:1px solid var(--bd);
      background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer
    }
    .btn.gray{background:#1b1b1b;color:#ddd}
    .btn.small{padding:.45rem .7rem; font-size:.92rem}

    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd;margin-right:6px}

    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}

    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

    /* ===== Tabla (desktop) ===== */
    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:860px}
    thead th{
      position:sticky; top:0; background:#121212; color:var(--brand);
      text-align:left; padding:.7rem .65rem; border-bottom:1px solid var(--bd); z-index:1;
    }
    td{padding:.6rem .65rem;border-bottom:1px solid var(--bd);vertical-align:middle}
    code{background:#000; padding:.15rem .35rem; border-radius:6px; border:1px solid #333}

    @media(hover:hover){ tbody tr:hover{background:#101010} }

    /* ===== Cards (mobile) ===== */
    @media (max-width: 820px){
      .table-wrap{border:0}
      table{border-collapse:separate;border-spacing:0 12px;min-width:0}
      thead{display:none}
      tbody tr{
        display:block;background:var(--card);border:1px solid var(--bd);
        border-radius:14px;padding:10px 10px 6px;
      }
      tbody td{
        display:flex;justify-content:space-between;gap:12px;
        padding:.55rem .3rem;border-bottom:0;font-size:.98rem;
      }
      tbody td::before{
        content:attr(data-label); color:var(--mut); min-width:42%;
      }
      td[data-key="venta"]{display:block;font-weight:700}
      td[data-key="venta"]::before{content:"# Venta"}
      td[data-key="acciones"]{display:flex;gap:8px;flex-wrap:wrap}
      .btn.small{flex:1 1 48%}
      .table-wrap{overflow:visible}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <?php @include __DIR__.'/menu_eventos.php'; ?>

    <div class="row" style="margin-bottom:10px">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <span class="pill">Evento #<?= (int)$evento_id ?></span>
      <?php if(!empty($evento['titulo'])): ?><span class="pill"><?= h($evento['titulo']) ?></span><?php endif; ?>
      <?php if(!empty($evento['fecha'])): ?><span class="pill"><?= h($evento['fecha']) ?></span><?php endif; ?>
    </div>

    <h2>📊 Ventas — <?= h($evento['titulo']) ?></h2>
    <div class="mut" style="margin:-4px 0 12px">Resumen por origen (solo suma $ de <b>pagados</b>).</div>

    <div class="grid">
      <div class="card">
        <h3 style="margin:0 0 6px">Online</h3>
        <div class="pill">Pagados: <?= (int)$tot['online']['pagado'] ?></div>
        <div class="pill">Pendientes: <?= (int)$tot['online']['pendiente'] ?></div>
        <div class="pill">Cancelados: <?= (int)$tot['online']['cancelado'] ?></div>
        <div style="margin-top:6px"><b>Total recaudado:</b> $ <?= money($tot['online']['monto']) ?></div>

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
        <div style="margin-top:6px"><b>Total recaudado:</b> $ <?= money($tot['taquilla']['monto']) ?></div>
      </div>
    </div>

    <div class="card" style="margin-top:12px">
      <h3 style="margin:0 0 8px">Últimos movimientos</h3>
      <div class="table-wrap" role="region" aria-label="Últimos movimientos" tabindex="0">
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
            <tr><td colspan="7" class="mut">Sin ventas.</td></tr>
          <?php else: foreach($pedidos as $p): ?>
            <tr>
              <td data-key="venta" data-label="# Venta"><?= sprintf('PED-%06d',(int)$p['id']) ?></td>
              <td data-label="Fecha"><?= h((string)$p['created_at']) ?></td>
              <td data-label="Origen"><?= h((string)$p['origen']) ?></td>
              <td data-label="Estado"><span class="pill"><?= h((string)$p['estado']) ?></span></td>
              <td data-label="Comprador">
                <?= h((string)$p['comprador_nombre']) ?><br>
                <small class="mut"><?= h((string)$p['comprador_email']) ?></small>
              </td>
              <td data-label="Total">$ <?= money($p['total']) ?></td>
              <td data-key="acciones" data-label="Acciones" style="white-space:nowrap">
                <a class="btn small" href="compra_ok.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">👁️ Ver</a>
                <a class="btn small gray" href="qr_evento.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">📄 PDF</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div style="margin-top:10px" class="row">
      <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
      <a class="btn" href="ver_entradas_vendidas.php?evento_id=<?= (int)$evento_id ?>">🎟️ Ver entradas vendidas</a>
    </div>
  </div>
</body>
</html>
