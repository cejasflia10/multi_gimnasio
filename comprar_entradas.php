<?php
if (session_status()===PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
if (!isset($conexion)||!($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function money($n){ return number_format((float)$n,2,',','.'); }

$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_GET['id'] ?? 0);
if ($evento_id<=0){ http_response_code(400); exit('Falta evento_id'); }

/* Evento */
$st=$conexion->prepare("SELECT id,titulo,fecha,hora,lugar,flyer FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id); $st->execute(); $ev=$st->get_result()->fetch_assoc(); $st->close();
if (!$ev){ http_response_code(404); exit('Evento no encontrado'); }

/* Config pagos */
$conexion->query("CREATE TABLE IF NOT EXISTS eventos_pagos_config(
  evento_id INT PRIMARY KEY,
  alias_bancario VARCHAR(120) NULL,
  titular_banco  VARCHAR(200) NULL,
  banco_nombre   VARCHAR(200) NULL,
  nota TEXT NULL,
  habilitar_online  TINYINT(1) NOT NULL DEFAULT 1,
  habilitar_taquilla TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY(evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$cfg=['alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null,'habilitar_online'=>1];
$st=$conexion->prepare("SELECT alias_bancario,titular_banco,banco_nombre,nota,habilitar_online FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();
if ((int)$cfg['habilitar_online']!==1){ http_response_code(403); exit('Ventas online deshabilitadas para este evento.'); }

/* Armar query de tipos respetando columnas opcionales */
$has_visible = has_col($conexion,'tickets_tipos','visible');
$has_canal   = has_col($conexion,'tickets_tipos','canal');

$cond = "evento_id=?";
if ($has_visible) $cond .= " AND visible=1";
if ($has_canal)   $cond .= " AND canal IN('online','todos')";

$sql="SELECT id,nombre,precio,stock_disponible,max_por_compra".
     ($has_visible ? ",visible" : "").
     ($has_canal ? ",canal" : "").
     " FROM tickets_tipos WHERE $cond ORDER BY precio ASC, id ASC";

$st=$conexion->prepare($sql); $st->bind_param('i',$evento_id); $st->execute();
$tipos=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

/* Flash */
$flash_err = $_SESSION['flash_error'] ?? null;
$flash_ok  = $_SESSION['ok_msg'] ?? null;
unset($_SESSION['flash_error'], $_SESSION['ok_msg']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1, viewport-fit=cover">
  <title>Comprar entradas — <?= h($ev['titulo']) ?></title>
  <style>
    :root{
      --bg:#0a0a0a; --fg:#e6eef4; --mut:#9ecbff; --brand:#d4af37;
      --card:#0f1720; --bd:#1f2a33; --line:#222;
      --okbg:#0f251b; --okbd:#164b31; --oktx:#b6f3d1; --badbg:#2a1414; --badbd:#5e2626; --badt:#ffb4b4;
    }
    *{box-sizing:border-box}
    html,body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    a{color:var(--brand);text-decoration:none}
    a:focus,button:focus,input:focus,select:focus,textarea:focus{outline:2px dashed var(--brand); outline-offset:2px}

    .wrap{max-width:980px;margin:18px auto;padding:16px}
    .btn{
      display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--line);
      background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer
    }
    .btn.gray{background:#1b2836;border-color:#2b3c4f;color:#ddd}
    .btn.primary{background:#0e7ad1;border-color:#27455c;color:#fff}
    .btn.full{width:100%;justify-content:center}

    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}

    .muted{color:var(--mut)}
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}

    .tipo{display:flex;align-items:center;justify-content:space-between;border:1px solid #243140;border-radius:10px;padding:10px;margin:8px 0;gap:12px}
    .tipo .precio{color:#b6f3d1;font-weight:600}
    .tipo .controles{display:flex;align-items:center;gap:8px}

    input,select{
      width:100%;padding:.56rem .7rem;border-radius:10px;border:1px solid var(--line);background:#111a24;color:var(--fg)
    }

    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    @media(max-width:720px){.form-grid{grid-template-columns:1fr}}

    /* En móvil, “cards” para tipos (la disposición ya es card por defecto) */
  </style>
</head>
<body>
<div class="wrap">
  <div style="margin-bottom:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <a class="btn gray" href="evento.php?id=<?= (int)$evento_id ?>">← Volver al evento</a>
    <span class="btn" style="pointer-events:none">📅 <?= h($ev['fecha']) ?> · ⏰ <?= h(substr((string)$ev['hora'],0,5)) ?> · 📍 <?= h($ev['lugar']) ?></span>
  </div>

  <div class="card">
    <h2 style="margin:0 0 6px">🎫 Comprar entradas — <?= h($ev['titulo']) ?></h2>

    <?php if($flash_err): ?><div class="bad"><?= h($flash_err) ?></div><?php endif; ?>
    <?php if($flash_ok):  ?><div class="ok"><?= h($flash_ok)  ?></div><?php endif; ?>

    <?php if(!$tipos): ?>
      <p class="muted" style="margin-top:10px">No hay tipos de entrada disponibles online por ahora.</p>
    <?php else: ?>
      <form action="comprar_entradas_post.php" method="post" enctype="multipart/form-data" id="frmCompra">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

        <h3 style="margin:10px 0 6px">Datos del comprador</h3>
        <div class="form-grid">
          <div>
            <label for="nombre">Nombre y apellido</label>
            <input id="nombre" name="nombre" required autocomplete="name">
          </div>
          <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" required autocomplete="email">
          </div>
          <div>
            <label for="tel">Teléfono</label>
            <input id="tel" name="tel" autocomplete="tel">
          </div>
          <div>
            <label for="metodo">Método de pago</label>
            <select id="metodo" name="metodo_pago" required>
              <option value="transferencia">Transferencia/Depósito</option>
              <option value="efectivo">Efectivo (en punto físico)</option>
              <option value="tarjeta">Tarjeta</option>
            </select>
          </div>
        </div>

        <h3 style="margin:14px 0 6px">Seleccioná tus entradas</h3>

        <?php foreach($tipos as $t):
          $tid   = (int)$t['id'];
          $stock = (int)$t['stock_disponible'];
          $maxpc = max(0,(int)$t['max_por_compra']); // 0 = sin límite por compra
          $maxSel = $maxpc>0 ? min($maxpc,$stock) : $stock;
          if ($stock <= 0) $maxSel = 0;
        ?>
          <div class="tipo" data-tipo-id="<?= $tid ?>">
            <div style="min-width:0">
              <div><strong><?= h($t['nombre']) ?></strong></div>
              <small class="muted">Máx/compra: <?= (int)$t['max_por_compra'] ?> · Stock disp.: <?= $stock ?></small>
            </div>
            <div class="controles">
              <span class="precio" data-precio="<?= (float)$t['precio'] ?>">$<?= money($t['precio']) ?></span>
              <?php if ($maxSel>0): ?>
                <input
                  type="number"
                  name="qty[<?= $tid ?>]"
                  min="0"
                  max="<?= $maxSel ?>"
                  value="0"
                  inputmode="numeric"
                  style="width:96px"
                  aria-label="Cantidad para <?= h($t['nombre']) ?>">
              <?php else: ?>
                <span class="muted">Agotado</span>
                <input type="hidden" name="qty[<?= $tid ?>]" value="0">
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <h3 style="margin:14px 0 6px">Comprobante del pago</h3>
        <p class="muted" style="margin:0 0 6px">
          Transferí a <b><?= h($cfg['alias_bancario'] ?: '—') ?></b>
          (<?= h($cfg['banco_nombre'] ?: 'Banco') ?><?= !empty($cfg['titular_banco']) ? ', Titular: '.h($cfg['titular_banco']) : '' ?>)
          y subí el comprobante. Tu solicitud quedará <b>pendiente</b> hasta aprobación. Luego recibirás tu <b>QR</b> y el <b>PDF</b> por correo.
        </p>
        <input type="file" name="comprobante" accept=".pdf,image/*">

        <div class="card" style="margin-top:12px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; justify-content:space-between">
          <div>
            <div class="muted">Resumen</div>
            <div><b>Total ítems:</b> <span id="totalItems">0</span></div>
            <div><b>Total a pagar:</b> $ <span id="totalMonto">0,00</span></div>
          </div>
          <div style="display:flex; gap:8px; flex-wrap:wrap">
            <button class="btn primary" type="submit" id="btnEnviar" disabled>✅ Enviar solicitud y comprobante</button>
            <a class="btn gray" href="evento.php?id=<?= (int)$evento_id ?>">Ver evento</a>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  const $ = (q,root=document)=>root.querySelector(q);
  const $$ = (q,root=document)=>Array.from(root.querySelectorAll(q));

  const totalItems = $('#totalItems');
  const totalMonto = $('#totalMonto');
  const btnEnviar  = $('#btnEnviar');

  function parsePrice(el){
    const v = parseFloat(el.getAttribute('data-precio') || '0');
    return isFinite(v) ? v : 0;
  }
  function clamp(n, min, max){ return Math.max(min, Math.min(max, n)); }

  function recalc(){
    let items = 0;
    let monto = 0;
    $$('.tipo').forEach(card=>{
      const priceEl = $('.precio', card);
      const price = parsePrice(priceEl);
      const qtyEl = $('input[type="number"]', card);
      let q = 0;
      if(qtyEl){
        const min = parseInt(qtyEl.min || '0', 10);
        const max = parseInt(qtyEl.max || '0', 10);
        q = clamp(parseInt(qtyEl.value || '0', 10) || 0, min, max);
        if(q != qtyEl.value){ qtyEl.value = q; } // normalizar
      }
      items += q;
      monto += q * price;
    });
    totalItems.textContent = items.toString();
    totalMonto.textContent = new Intl.NumberFormat('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2}).format(monto);
    btnEnviar.disabled = (items <= 0);
  }

  // Listeners
  $$('.tipo input[type="number"]').forEach(inp=>{
    inp.addEventListener('input', recalc);
    inp.addEventListener('blur', recalc);
  });

  // Init
  recalc();
})();
</script>
</body>
</html>
