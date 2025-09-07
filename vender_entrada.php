<?php
// vender_entrada.php — Venta rápida en taquilla con envío por WhatsApp
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

if (!function_exists('h')) { function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); } }
if (!function_exists('app_url')) {
  function app_url(string $p=''): string {
    $base = defined('APP_BASE_URL') ? rtrim(APP_BASE_URL,'/').'/' : '/';
    return $base.ltrim($p,'/');
  }
}

// ==== Helpers mínimos ====
function code_gen(int $len=12): string {
  $a='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $n=strlen($a); $s='';
  for($i=0;$i<$len;$i++){ $s.=$a[random_int(0,$n-1)]; }
  return $s;
}

// ==== Evento ====
$evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : (int)($_POST['evento_id'] ?? 0);
if ($evento_id<=0) { http_response_code(400); exit('Falta evento_id'); }

$st = $conexion->prepare("SELECT id, titulo, fecha, hora, lugar FROM eventos_deportivos WHERE id=? LIMIT 1");
$st->bind_param('i',$evento_id);
$st->execute(); $evento = $st->get_result()->fetch_assoc(); $st->close();
if (!$evento){ http_response_code(404); exit('Evento no encontrado'); }

// ==== Tipos de entrada (para la vista) ====
$tipos = [];
$st = $conexion->prepare("SELECT id, nombre, precio, stock_disponible, COALESCE(max_por_compra,0) AS max_por_compra
                          FROM tickets_tipos
                          WHERE evento_id=? AND (visible IS NULL OR visible=1)
                          ORDER BY precio ASC, id ASC");
$st->bind_param('i',$evento_id);
$st->execute(); $tipos = $st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$flash_err = $_SESSION['flash_err'] ?? '';
$flash_ok  = $_SESSION['flash_ok']  ?? '';
unset($_SESSION['flash_err'], $_SESSION['flash_ok']);

// ======================================================
// POST: crear pedido "pagado" + tickets + link de WhatsApp
// ======================================================
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $nombre   = trim((string)($_POST['nombre'] ?? ''));
  $tel      = trim((string)($_POST['tel'] ?? ''));   // WhatsApp del comprador (código país sin +)
  $metodo   = trim((string)($_POST['metodo_pago'] ?? 'efectivo'));
  $tipo_id  = (int)($_POST['tipo_id'] ?? 0);
  $cantidad = (int)($_POST['cantidad'] ?? 1);
  $ajuste   = (float)str_replace(',','.', (string)($_POST['ajuste'] ?? 0));

  if ($nombre==='' || $tipo_id<=0 || $cantidad<=0) {
    $_SESSION['flash_err'] = 'Completá nombre, tipo y cantidad.';
    header('Location: vender_entrada.php?evento_id='.$evento_id);
    exit;
  }

  // Traer tipo seleccionado con lock
  $conexion->begin_transaction();
  try{
    $st = $conexion->prepare("SELECT id,nombre,precio,stock_disponible,COALESCE(max_por_compra,0) AS max_por_compra
                              FROM tickets_tipos
                              WHERE evento_id=? AND id=? FOR UPDATE");
    $st->bind_param('ii',$evento_id,$tipo_id);
    $st->execute(); $tipo = $st->get_result()->fetch_assoc(); $st->close();
    if (!$tipo) throw new Exception('El tipo de entrada no existe.');
    if ($cantidad > (int)$tipo['stock_disponible']) throw new Exception('Stock insuficiente en “'.$tipo['nombre'].'”.');
    if ((int)$tipo['max_por_compra']>0 && $cantidad>(int)$tipo['max_por_compra']) throw new Exception('Máximo permitido: '.$tipo['max_por_compra']);

    $unit   = (float)$tipo['precio'];
    $total  = max(0.0, ($unit * $cantidad) + $ajuste);

    // Insert pedido ya PAGADO (origen taquilla)
    $sqlP = "INSERT INTO pedidos (evento_id, comprador_nombre, comprador_email, comprador_tel, metodo_pago, total, estado, origen)
             VALUES (?, ?, '', ?, ?, ?, 'pagado', 'taquilla')";
    $st = $conexion->prepare($sqlP);
    $st->bind_param('isssd', $evento_id, $nombre, $tel, $metodo, $total);
    $st->execute(); $pedido_id = $st->insert_id; $st->close();

    // Token para consulta / impresión
    $token = bin2hex(random_bytes(20));
    $st=$conexion->prepare("UPDATE pedidos SET qr_token=?, qr_status='activo' WHERE id=?");
    $st->bind_param('si',$token,$pedido_id);
    $st->execute(); $st->close();

    // Descontar stock
    $st=$conexion->prepare("UPDATE tickets_tipos SET stock_disponible=stock_disponible-? WHERE id=? AND evento_id=? AND stock_disponible>=?");
    $st->bind_param('iiii',$cantidad,$tipo_id,$evento_id,$cantidad);
    $st->execute();
    if ($st->affected_rows<=0){ $st->close(); throw new Exception('El stock cambió, reintentá.'); }
    $st->close();

    // Emitir tickets
    $codes = [];
    for($i=0;$i<$cantidad;$i++){
      do{
        $code = code_gen(12);
        $chk=$conexion->prepare("SELECT 1 FROM tickets WHERE code=?");
        $chk->bind_param('s',$code); $chk->execute();
        $exists = $chk->get_result()->num_rows>0;
        $chk->close();
      }while($exists);

      $ins=$conexion->prepare("INSERT INTO tickets (pedido_id,evento_id,tipo_id,code) VALUES (?,?,?,?)");
      $ins->bind_param('iiis',$pedido_id,$evento_id,$tipo_id,$code);
      $ins->execute(); $ins->close();
      $codes[] = $code;
    }

    $conexion->commit();

    // ===== Construir mensaje de WhatsApp al comprador =====
    // Normalizar teléfono: quitar +, espacios, guiones
    $wa = preg_replace('/\D+/', '', $tel); // mandar como “549…” etc.
    // Enlaces a tickets (mostramos primero y, si hay más, indicamos cuántos quedan)
    $links = [];
    foreach($codes as $c){
      $links[] = app_url('ticket_pdf.php?code='.rawurlencode($c));
    }
    $primerLink = $links[0] ?? app_url();
    $restantes  = max(0, count($links)-1);

    $txt  = "🎫 *Entrada confirmada*%0A";
    $txt .= "*Evento:* ".rawurlencode($evento['titulo'])."%0A";
    $txt .= "*Cliente:* ".rawurlencode($nombre)."%0A";
    $txt .= "*Tipo:* ".rawurlencode($tipo['nombre'])." x ".$cantidad."%0A";
    $txt .= "*Total:* $ ".number_format($total,2,',','.')."%0A";
    $txt .= "*Tu ticket:* ".$primerLink."%0A";
    if ($restantes>0) { $txt .= "(+".$restantes." adicionales en la taquilla)%0A"; }
    $txt .= "%0A📄 Presentá el *PDF/QR* en el acceso.";

    // Si hay teléfono, armamos wa.me; si no, dejamos vacío
    $waLink = $wa ? ("https://wa.me/".$wa."?text=".$txt) : '';

    // Página de confirmación
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Venta OK — <?= h($evento['titulo']) ?></title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <style>
        body{background:#0b1115;color:#e6eef4;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif;margin:0}
        .wrap{max-width:760px;margin:18px auto;padding:16px}
        .card{background:#0f1720;border:1px solid #1f2a33;border-radius:12px;padding:14px}
        .btn{display:inline-block;padding:.65rem 1rem;border-radius:10px;background:#00c853;color:#000;font-weight:700;text-decoration:none;border:1px solid #0b8f3f}
        .btn.gray{background:#1b2836;color:#e6eef4;border-color:#2b3c4f}
        .list{line-height:1.65}
        code{background:#09131a;border:1px solid #1e2b36;border-radius:6px;padding:.15rem .35rem}
      </style>
    </head>
    <body>
      <div class="wrap">
        <div class="card">
          <h2 style="margin:0 0 6px">✅ Venta registrada</h2>
          <p>Se generó el pedido <b>#<?= (int)$pedido_id ?></b> (estado <b>pagado</b>) y los tickets correspondientes.</p>
          <p class="list">
            <b>Cliente:</b> <?= h($nombre) ?><br>
            <b>Tipo:</b> <?= h($tipo['nombre']) ?> x <?= (int)$cantidad ?><br>
            <b>Total:</b> $ <?= number_format($total,2,',','.') ?><br>
            <b>Primer ticket (PDF):</b> <a class="btn gray" target="_blank" rel="noopener" href="<?= h($primerLink) ?>">Abrir PDF</a>
          </p>

          <?php if ($waLink): ?>
            <p style="margin-top:10px">
              <a class="btn" target="_blank" rel="noopener" href="<?= h($waLink) ?>">📲 Enviar por WhatsApp</a>
            </p>
          <?php else: ?>
            <p style="color:#bcd8ff">Ingresá un teléfono en la próxima venta para habilitar el envío por WhatsApp.</p>
          <?php endif; ?>

          <?php if ($restantes>0): ?>
            <details style="margin-top:10px">
              <summary>Ver todos los códigos</summary>
              <ul>
                <?php foreach($codes as $c): ?>
                  <li><code><?= h($c) ?></code> — <a target="_blank" rel="noopener" href="<?= h(app_url('ticket_pdf.php?code='.rawurlencode($c))) ?>">PDF</a></li>
                <?php endforeach; ?>
              </ul>
            </details>
          <?php endif; ?>

          <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn gray" href="vender_entrada.php?evento_id=<?= (int)$evento_id ?>">➕ Otra venta</a>
            <a class="btn gray" href="ver_entradas_vendidas.php?evento_id=<?= (int)$evento_id ?>">🎟️ Ver entradas vendidas</a>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;

  } catch(Exception $e){
    $conexion->rollback();
    $_SESSION['flash_err'] = $e->getMessage();
    header('Location: vender_entrada.php?evento_id='.$evento_id);
    exit;
  }
}

// ======================
// GET: formulario simple
// ======================
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Taquilla — <?= h($evento['titulo']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{--bg:#0b1115;--card:#0f1720;--bd:#1f2a33;--txt:#e6eef4;--mut:#bcd8ff;--brand:#d4af37}
    *{box-sizing:border-box}
    body{margin:0;background:var(--bg);color:var(--txt);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:680px;margin:16px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--bd);border-radius:12px;padding:14px}
    .row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    @media(max-width:680px){.row{grid-template-columns:1fr}}
    label{display:block;font-weight:700;margin:.15rem 0}
    input,select{width:100%;padding:.6rem .7rem;border-radius:10px;border:1px solid #203040;background:#111a24;color:#e6eef4}
    .mut{color:var(--mut)}
    .btn{display:inline-block;padding:.65rem 1rem;border-radius:10px;background:#d4af37;color:#000;font-weight:700;border:1px solid #7e6a22;cursor:pointer}
    .btn.gray{background:#1b2836;color:#e6eef4;border-color:#2b3c4f}
    .price{font-size:1.05rem;color:#9effa2;margin-top:6px}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 6px">🎟️ Venta en taquilla — <?= h($evento['titulo']) ?></h2>
      <div class="mut"><?= h(date('d/m/Y', strtotime((string)$evento['fecha']))) ?> · <?= h(substr((string)$evento['hora'],0,5)) ?> · <?= h($evento['lugar']) ?></div>

      <?php if($flash_err): ?><div style="margin-top:8px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;border-radius:10px;padding:10px"><?= h($flash_err) ?></div><?php endif; ?>
      <?php if($flash_ok):  ?><div style="margin-top:8px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1;border-radius:10px;padding:10px"><?= h($flash_ok)  ?></div><?php endif; ?>

      <form method="post" action="" oninput="calc()">
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id ?>">

        <label for="nombre">Nombre y apellido</label>
        <input id="nombre" name="nombre" required autocomplete="name" placeholder="Cliente...">

        <div class="row">
          <div>
            <label for="tipo_id">Tipo de entrada</label>
            <select id="tipo_id" name="tipo_id" required onchange="syncPrecio()">
              <option value="">-- Elegí --</option>
              <?php foreach($tipos as $t): ?>
                <option value="<?= (int)$t['id'] ?>"
                        data-precio="<?= (float)$t['precio'] ?>"
                        data-stock="<?= (int)$t['stock_disponible'] ?>">
                  <?= h($t['nombre']) ?> — $ <?= number_format((float)$t['precio'],2,',','.') ?> (<?= (int)$t['stock_disponible'] ?> disp.)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="cantidad">Cantidad</label>
            <input id="cantidad" name="cantidad" type="number" min="1" value="1" required>
          </div>
        </div>

        <div class="row">
          <div>
            <label for="ajuste">Ajuste (opcional)</label>
            <input id="ajuste" name="ajuste" type="number" step="0.01" value="0" placeholder="+/- para redondeo/desc.">
          </div>
          <div>
            <label for="metodo_pago">Método de pago</label>
            <select id="metodo_pago" name="metodo_pago" required>
              <option value="efectivo">Efectivo</option>
              <option value="transferencia">Transferencia</option>
              <option value="tarjeta">Tarjeta</option>
            </select>
          </div>
        </div>

        <label for="tel">WhatsApp del comprador (con prefijo país, sin +)</label>
        <input id="tel" name="tel" inputmode="numeric" placeholder="54911....">

        <div class="price" id="totalBox">Total: $ 0,00</div>

        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn" type="submit">✅ Confirmar y marcar PAGADO</button>
          <a class="btn gray" href="ver_evento.php?id=<?= (int)$evento_id ?>">↩ Volver</a>
        </div>
      </form>
    </div>
  </div>

<script>
function toNumber(x){ const n = parseFloat(String(x).replace(',','.')); return isNaN(n)?0:n; }
function calc(){
  const sel = document.getElementById('tipo_id');
  const qty = toNumber(document.getElementById('cantidad').value);
  const adj = toNumber(document.getElementById('ajuste').value);
  const opt = sel.options[sel.selectedIndex];
  const unit = opt ? toNumber(opt.getAttribute('data-precio')) : 0;
  const total = Math.max(0, (unit*qty) + adj);
  document.getElementById('totalBox').textContent = 'Total: $ ' + total.toFixed(2).replace('.',',');
}
function syncPrecio(){ calc(); }
calc();
</script>
</body>
</html>
