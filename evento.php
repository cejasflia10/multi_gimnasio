<?php
// evento.php — Vista pública de un evento + formulario de compra (responsive)
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
function money($n){ return number_format((float)$n, 2, ',', '.'); }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; } return false;
}
function is_youtube($url){ $u=(string)$url; return stripos($u,'youtube.com')!==false || stripos($u,'youtu.be')!==false || stripos($u,'shorts/')!==false; }
function yt_embed($url){
  $u=(string)$url;
  if (stripos($u,'watch?v=')!==false) return str_ireplace('watch?v=','embed/',$u);
  if (stripos($u,'shorts/')!==false)   return str_ireplace('shorts/','embed/',$u);
  if (stripos($u,'youtu.be/')!==false){ $code=trim(parse_url($u,PHP_URL_PATH),'/'); return 'https://www.youtube.com/embed/'.$code; }
  return $u;
}

$evento_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($evento_id<=0){ http_response_code(400); exit('Evento inválido'); }

/* Evento */
$st=$conexion->prepare("SELECT id, titulo, descripcion, fecha, hora, lugar, flyer, video FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id); $st->execute(); $evento=$st->get_result()->fetch_assoc(); $st->close();
if (!$evento){ http_response_code(404); exit('Evento no encontrado'); }

/* Config pagos */
$cfg=['habilitar_online'=>1,'alias_bancario'=>null,'titular_banco'=>null,'banco_nombre'=>null,'nota'=>null];
$conexion->query("CREATE TABLE IF NOT EXISTS eventos_pagos_config (
  evento_id INT PRIMARY KEY,
  alias_bancario VARCHAR(120) NULL,
  titular_banco  VARCHAR(200) NULL,
  banco_nombre   VARCHAR(200) NULL,
  habilitar_online  TINYINT(1) NOT NULL DEFAULT 1,
  habilitar_taquilla TINYINT(1) NOT NULL DEFAULT 1,
  nota TEXT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (evento_id) REFERENCES eventos_deportivos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$st=$conexion->prepare("SELECT alias_bancario,titular_banco,banco_nombre,habilitar_online,nota FROM eventos_pagos_config WHERE evento_id=?");
$st->bind_param('i',$evento_id); $st->execute(); $r=$st->get_result(); if($r && $r->num_rows){ $cfg=$r->fetch_assoc(); } $st->close();

/* Tipos */
$cols_extra = has_col($conexion,'tickets_tipos','visible') ? ', visible' : '';
$sql="SELECT id, nombre, precio, stock_disponible, max_por_compra $cols_extra
      FROM tickets_tipos WHERE evento_id=? ORDER BY precio ASC, id ASC";
$st=$conexion->prepare($sql);
$st->bind_param('i',$evento_id);
$st->execute();
$tipos = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

/* Reglas */
$hay_stock=false; $tipos_mostrables=[];
foreach($tipos as $t){
  $visible_ok = !isset($t['visible']) || (int)$t['visible']===1;
  $stock_ok   = (int)$t['stock_disponible']>0;
  if($visible_ok){ $tipos_mostrables[]=$t; }
  if($visible_ok && $stock_ok){ $hay_stock=true; }
}
$online_habilitado = (int)$cfg['habilitar_online']===1;
$bloqueado = !$online_habilitado;

/* Flash */
$flash_err = $_SESSION['flash_error'] ?? '';
$flash_ok  = $_SESSION['ok_msg'] ?? '';
unset($_SESSION['flash_error'], $_SESSION['ok_msg']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= h($evento['titulo']) ?> — Entradas</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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
    .btn{display:inline-flex;align-items:center;gap:.45rem;padding:.58rem .9rem;border-radius:10px;border:1px solid var(--line);background:#151515;color:var(--brand);text-decoration:none;font-weight:600;cursor:pointer}
    .btn.gray{background:#1b2836;border-color:#2b3c4f;color:#ddd}
    .btn.primary{background:#0e7ad1;border-color:#27455c;color:#fff}
    .btn.full{width:100%;justify-content:center}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    @media(max-width:900px){.grid{grid-template-columns:1fr}}
    h1{margin:0 0 6px}
    .muted{color:var(--mut)}
    .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:620px}
    thead th{position:sticky; top:0; background:#121212; color:var(--brand); text-align:left; padding:.7rem .65rem; border-bottom:1px solid var(--bd); z-index:1}
    td{padding:.6rem .65rem;border-bottom:1px solid var(--bd);vertical-align:middle}
    select, input[type="text"], input[type="email"], input[type="file"]{width:100%;padding:.56rem .7rem;border-radius:10px;border:1px solid var(--line);background:#111a24;color:#fffc}
    .pill{display:inline-block;padding:.25rem .6rem;border-radius:999px;border:1px solid #3b3b3b;font-size:.85rem;color:#ddd}
    @media (max-width: 760px){
      .table-wrap{border:0}
      table{border-collapse:separate;border-spacing:0 12px;min-width:0}
      thead{display:none}
      tbody tr{display:block;background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:10px 10px 6px}
      tbody td{display:flex;justify-content:space-between;gap:12px;padding:.55rem .3rem;border-bottom:0;font-size:.98rem}
      tbody td::before{content:attr(data-label); color:var(--mut); min-width:42%}
      td[data-key="tipo"]{display:block;font-weight:700}
      td[data-key="tipo"]::before{content:"Tipo"}
      td[data-key="qty"]{display:flex;gap:8px;align-items:center}
    }
    .ok{margin:10px 0;padding:10px;border-radius:10px;background:var(--okbg);border:1px solid var(--okbd);color:var(--oktx)}
    .bad{margin:10px 0;padding:10px;border-radius:10px;background:var(--badbg);border:1px solid var(--badbd);color:var(--badt)}
    .flyer{width:100%;height:auto;border-radius:10px;border:1px solid var(--line);background:#000}
    .video-embed{width:100%;aspect-ratio:16/9;border:0;border-radius:10px;background:#000}
    .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    @media(max-width:720px){.form-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div style="margin-bottom:10px">
      <a class="btn gray" href="eventos_disponibles.php">← Eventos disponibles</a>
    </div>

    <div class="grid">
      <div>
        <div class="card">
          <h1><?= h($evento['titulo']) ?></h1>
          <div class="muted">
            <b>Fecha:</b> <?= h($evento['fecha']) ?> · <b>Hora:</b> <?= h($evento['hora']) ?> · <b>Lugar:</b> <?= h($evento['lugar']) ?>
          </div>
          <?php if(!empty($evento['descripcion'])): ?>
            <p style="margin-top:8px"><?= nl2br(h($evento['descripcion'])) ?></p>
          <?php endif; ?>
          <?php if(!empty($evento['flyer'])): ?>
            <div style="margin-top:10px"><img class="flyer" src="<?= h($evento['flyer']) ?>" alt="Flyer"></div>
          <?php endif; ?>
          <?php if(!empty($evento['video'])): ?>
            <div style="margin-top:10px">
              <?php if(is_youtube($evento['video'])): ?>
                <iframe class="video-embed" src="<?= h(yt_embed($evento['video'])) ?>" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
              <?php else: ?>
                <a class="btn" href="<?= h($evento['video']) ?>" target="_blank" rel="noopener">🎥 Video</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin-top:12px">
          <h3 style="margin:0 0 6px">Entradas</h3>

          <?php if($flash_err): ?><div class="bad"><?= h($flash_err) ?></div><?php endif; ?>
          <?php if($flash_ok):  ?><div class="ok"><?= h($flash_ok)  ?></div><?php endif; ?>

          <?php if ($bloqueado): ?>
            <div class="bad">🚫 Las ventas online para este evento están deshabilitadas por el organizador.</div>
          <?php elseif (!$tipos_mostrables): ?>
            <div class="bad">🚫 Aún no hay tipos de entrada configurados.</div>
          <?php elseif (!$hay_stock): ?>
            <div class="bad">🚫 No hay stock disponible en este momento.</div>
          <?php else: ?>
            <!-- IMPORTANTE: enctype para subir el comprobante -->
            <form method="post" action="crear_pedido.php" id="frmCompra" enctype="multipart/form-data">
              <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

              <div class="table-wrap" role="region" aria-label="Tipos de entradas" tabindex="0">
                <table>
                  <thead>
                    <tr><th>Tipo</th><th>Precio</th><th>Stock</th><th style="width:160px">Cantidad</th></tr>
                  </thead>
                  <tbody>
                  <?php foreach($tipos_mostrables as $t):
                    $stock = (int)$t['stock_disponible']; $max = max(0,(int)($t['max_por_compra'] ?? 0)); ?>
                    <tr>
                      <td data-key="tipo"><?= h($t['nombre']) ?></td>
                      <td data-label="Precio">$ <?= money($t['precio']) ?></td>
                      <td data-label="Stock"><?= $stock>0 ? $stock : 'Agotado' ?></td>
                      <td data-key="qty" data-label="Cantidad">
                        <?php if ($stock>0): ?>
                          <select name="qty[<?= (int)$t['id'] ?>]" aria-label="Cantidad para <?= h($t['nombre']) ?>">
                            <?php $lim = $max>0 ? min($max,$stock) : $stock;
                            for($i=0;$i<=$lim;$i++): ?><option value="<?= $i ?>"><?= $i ?></option><?php endfor; ?>
                          </select>
                        <?php else: ?><span class="pill">0</span><?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="card" style="margin-top:10px">
                <div class="pill">Online: <?= $online_habilitado?'ON':'OFF' ?></div>
                <?php if(!empty($cfg['alias_bancario'])): ?>
                  <div style="margin-top:6px"><b>Alias bancario:</b> <?= h($cfg['alias_bancario']) ?>
                  <?php if(!empty($cfg['titular_banco'])): ?> — Titular: <?= h($cfg['titular_banco']) ?><?php endif; ?>
                  <?php if(!empty($cfg['banco_nombre'])): ?> — Banco: <?= h($cfg['banco_nombre']) ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if(!empty($cfg['nota'])): ?><div style="margin-top:6px"><?= nl2br(h($cfg['nota'])) ?></div><?php endif; ?>
              </div>

              <div class="card" style="margin-top:10px">
                <h4 style="margin:0 0 8px">Tus datos</h4>
                <div class="form-grid">
                  <div><label for="nombre">Nombre y apellido</label><input id="nombre" type="text" name="nombre" required autocomplete="name"></div>
                  <div><label for="email">Email</label><input id="email" type="email" name="email" required autocomplete="email"></div>
                </div>
                <div style="margin-top:8px"><label for="tel">Teléfono (opcional)</label><input id="tel" type="text" name="tel" autocomplete="tel"></div>
              </div>

              <!-- NUEVO: método y comprobante -->
              <div class="card" style="margin-top:10px">
                <h4 style="margin:0 0 8px">Pago</h4>
                <div class="form-grid">
                  <div>
                    <label for="metodo_pago">Método de pago</label>
                    <select id="metodo_pago" name="metodo_pago" required>
                      <option value="transferencia">Transferencia / Depósito</option>
                      <option value="efectivo">Efectivo (punto físico)</option>
                      <option value="tarjeta">Tarjeta</option>
                    </select>
                  </div>
                  <div>
                    <label for="comprobante">Comprobante (imagen o PDF)</label>
                    <!-- En móviles abre cámara por `capture` y limita a imágenes/PDF -->
                    <input id="comprobante" type="file" name="comprobante" accept="image/*,.pdf" capture="environment">
                  </div>
                </div>
                <p class="muted" style="margin:.5rem 0 0">
                  <small>Si pagaste por transferencia, subí el comprobante. El pedido queda <b>pendiente</b> hasta aprobación
                  y recién ahí se habilita el QR.</small>
                </p>
              </div>

              <div style="margin-top:10px; display:flex; gap:8px; flex-wrap:wrap">
                <button class="btn primary" type="submit">Comprar</button>
                <a class="btn gray" href="eventos_disponibles.php">Seguir viendo eventos</a>
              </div>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <div class="card">
          <h3 style="margin:0 0 6px">Información</h3>
          <div><b>Fecha:</b> <?= h($evento['fecha']) ?> — <b>Hora:</b> <?= h($evento['hora']) ?></div>
          <div style="margin-top:4px"><b>Lugar:</b> <?= h($evento['lugar']) ?></div>
          <?php if(!empty($evento['video']) && !is_youtube($evento['video'])): ?>
            <div style="margin-top:8px"><a class="btn" href="<?= h($evento['video']) ?>" target="_blank" rel="noopener">🎥 Video</a></div>
          <?php endif; ?>
        </div>

        <div class="card" style="margin:12px 0 0">
          <h3 style="margin:0 0 6px">Ayuda</h3>
          <p class="muted">Tras la compra vas a poder <b>descargar tu entrada</b> e imprimirla o mostrarla en el celular. El <b>QR</b> se habilita cuando el organizador confirma el pago.</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Validación suave: si elige "transferencia", pedimos comprobante -->
  <script>
    const metodo = document.getElementById('metodo_pago');
    const comp   = document.getElementById('comprobante');
    function toggleComprobanteReq(){
      if (!metodo || !comp) return;
      comp.required = (metodo.value === 'transferencia');
    }
    metodo?.addEventListener('change', toggleComprobanteReq);
    toggleComprobanteReq();
  </script>
</body>
</html>
