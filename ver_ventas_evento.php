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

/* Migraciones suaves necesarias */
if (!has_col($conexion,'pedidos','origen')) {
  @$conexion->query("ALTER TABLE pedidos ADD COLUMN origen ENUM('online','taquilla') NOT NULL DEFAULT 'online' AFTER total");
}
if (!has_col($conexion,'pedidos','comprobante_path')) {
  @$conexion->query("ALTER TABLE pedidos ADD COLUMN comprobante_path VARCHAR(255) NULL AFTER metodo_pago");
}

/* ===== Acciones POST: habilitar / revertir ===== */
$flash_ok = $_SESSION['flash_ok'] ?? '';
$flash_err= $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $accion    = $_POST['accion'] ?? '';
  $pedido_id = isset($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : 0;

  if ($pedido_id<=0) {
    $_SESSION['flash_err'] = 'ID de pedido inválido.';
    header('Location: ver_ventas_evento.php?evento_id='.$evento_id);
    exit;
  }

  if ($accion === 'habilitar') {
    // Marcar como pagado => habilita QR en PDF/mi_entrada
    $sql = "UPDATE pedidos SET estado='pagado' WHERE id=? AND evento_id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('ii',$pedido_id,$evento_id);
      $st->execute();
      if ($st->affected_rows>0) { $_SESSION['flash_ok']='Entradas habilitadas (pedido marcado como pagado).'; }
      else { $_SESSION['flash_err']='No se pudo actualizar el estado (¿pertenece a otro evento?).'; }
      $st->close();
    } else { $_SESSION['flash_err']='Error interno (prep habilitar).'; }
  }
  elseif ($accion === 'pendiente') {
    // Volver a pendiente => QR no operable
    $sql = "UPDATE pedidos SET estado='pendiente' WHERE id=? AND evento_id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('ii',$pedido_id,$evento_id);
      $st->execute();
      if ($st->affected_rows>0) { $_SESSION['flash_ok']='Pedido vuelto a pendiente.'; }
      else { $_SESSION['flash_err']='No se pudo actualizar el estado (¿pertenece a otro evento?).'; }
      $st->close();
    } else { $_SESSION['flash_err']='Error interno (prep pendiente).'; }
  }

  header('Location: ver_ventas_evento.php?evento_id='.$evento_id);
  exit;
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
  SELECT id, comprador_nombre, comprador_email, total, estado, origen, created_at, comprobante_path
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
      --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
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
    .btn.red{background:#7a1f1f;border-color:#8f2a2a;color:#fff}

    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd;margin-right:6px}

    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}

    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}

    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}

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

    <?php if(!empty($flash_ok)): ?><div class="ok"><?= $flash_ok ?></div><?php endif; ?>
    <?php if(!empty($flash_err)): ?><div class="bad"><?= $flash_err ?></div><?php endif; ?>

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
              <th>Comprobante</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
          <?php if(!$pedidos): ?>
            <tr><td colspan="8" class="mut">Sin ventas.</td></tr>
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
              <td data-label="Comprobante">
                <?php if(!empty($p['comprobante_path'])): ?>
                  <a class="btn small gray" href="<?= h($p['comprobante_path']) ?>" target="_blank" rel="noopener">📎 Ver</a>
                <?php else: ?>
                  <span class="mut">—</span>
                <?php endif; ?>
              </td>
              <td data-key="acciones" data-label="Acciones" style="white-space:nowrap">
                <a class="btn small" href="compra_ok.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">👁️ Ver</a>
                <a class="btn small gray" href="qr_evento.php?pedido_id=<?= (int)$p['id'] ?>" target="_blank" rel="noopener">📄 PDF</a>

                <?php if (strtolower((string)$p['estado'])!=='pagado'): ?>
                  <!-- Habilitar entradas (marcar pagado) -->
                  <form method="post" action="" style="display:inline">
                    <input type="hidden" name="accion" value="habilitar">
                    <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn small" type="submit">✅ Habilitar entradas</button>
                  </form>
                <?php else: ?>
                  <!-- Volver a pendiente -->
                  <form method="post" action="" style="display:inline" onsubmit="return confirm('¿Volver a pendiente este pedido?');">
                    <input type="hidden" name="accion" value="pendiente">
                    <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                    <button class="btn small red" type="submit">↩ Volver a pendiente</button>
                  </form>
                <?php endif; ?>
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
