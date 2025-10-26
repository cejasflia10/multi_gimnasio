<?php
// pago_online.php — Carga de comprobante con planes/adicionales (MENÚ REUSABLE)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id']  ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($cliente_id === 0 || $gimnasio_id === 0) { header('Location: cliente_acceso.php'); exit; }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ok_ext(string $name): bool {
  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  return in_array($ext, ['jpg','jpeg','png','pdf'], true);
}
function ok_mime(string $tmp): bool {
  $f = @finfo_open(FILEINFO_MIME_TYPE);
  if (!$f) return true;
  $m = (string)@finfo_file($f, $tmp);
  @finfo_close($f);
  if ($m === '') return true;
  return in_array($m, ['image/jpeg','image/png','application/pdf'], true);
}
/* Polyfill PHP < 8 */
if (!function_exists('str_starts_with')) {
  function str_starts_with($haystack, $needle) { return $needle === '' || strpos($haystack, $needle) === 0; }
}

/* CSRF */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf_token'];

$mensaje = '';

/* Catálogo de planes y adicionales */
$planes = [];
if ($st = $conexion->prepare("SELECT id, nombre, precio FROM planes WHERE gimnasio_id = ? ORDER BY nombre")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute(); $res = $st->get_result();
  while($r = $res->fetch_assoc()) $planes[] = $r;
  $st->close();
}
$adicionales = [];
if ($st = $conexion->prepare("SELECT id, nombre, precio FROM planes_adicionales WHERE gimnasio_id = ? ORDER BY nombre")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute(); $res = $st->get_result();
  while($r = $res->fetch_assoc()) $adicionales[] = $r;
  $st->close();
}

/* Alias transferencia */
$alias = '';
if ($st = $conexion->prepare("SELECT alias FROM gimnasios WHERE id=? LIMIT 1")){
  $st->bind_param('i', $gimnasio_id);
  $st->execute(); $st->bind_result($alias); $st->fetch(); $st->close();
}

/* POST: subir comprobante */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
    $mensaje = '❌ Sesión expirada. Recargá la página.';
  } else {
    $plan_id = (int)($_POST['plan_id'] ?? 0);
    $monto   = (float)($_POST['monto']   ?? 0);
    $fecha   = date('Y-m-d H:i:s');

    if (!isset($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
      $cod = (int)($_FILES['comprobante']['error'] ?? -1);
      $mensaje = "❌ Error al recibir el archivo (código {$cod}).";
    } else {
      $ruta_tmp = $_FILES['comprobante']['tmp_name'];
      $archivo  = (string)$_FILES['comprobante']['name'];
      $tam      = (int)$_FILES['comprobante']['size'];

      if ($tam <= 0 || $tam > 10 * 1024 * 1024) {
        $mensaje = "❌ El archivo supera el tamaño permitido (máx. 10 MB).";
      } elseif (!ok_ext($archivo)) {
        $mensaje = "❌ Formato no permitido. Solo JPG, PNG o PDF.";
      } elseif (!ok_mime($ruta_tmp)) {
        $mensaje = "❌ El tipo de archivo no es válido.";
      }

      $relDir = 'comprobantes';
      $absDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;
      if (empty($mensaje) && !is_dir($absDir)) {
        if (!@mkdir($absDir, 0775, true)) $mensaje = "❌ No se pudo crear la carpeta de destino.";
      }
      if (empty($mensaje) && !is_writable($absDir)) {
        $mensaje = "❌ La carpeta no es escribible: {$absDir}";
      }

      if (empty($mensaje)) {
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($archivo));
        $nombre_final = 'g'.$gimnasio_id.'_c'.$cliente_id.'_'.uniqid('', true).'_'.$base;

        $ruta_publica = $relDir . '/' . $nombre_final;
        $ruta_fisica  = $absDir . DIRECTORY_SEPARATOR . $nombre_final;

        $adicionales_sel = (isset($_POST['adicionales']) && is_array($_POST['adicionales']))
          ? array_map('intval', $_POST['adicionales'])
          : [];
        $adicionales_json = json_encode($adicionales_sel, JSON_UNESCAPED_UNICODE);

        if (!is_uploaded_file($ruta_tmp) || !@move_uploaded_file($ruta_tmp, $ruta_fisica)) {
          error_log('Fallo move_uploaded_file hacia: ' . $ruta_fisica);
          $mensaje = "❌ No se pudo guardar el comprobante en el servidor.";
        } else {
          $sql = "INSERT INTO pagos_pendientes 
                    (cliente_id, gimnasio_id, plan_id, monto, archivo_comprobante, fecha_envio, estado, adicionales)
                  VALUES (?, ?, ?, ?, ?, ?, 'pendiente', ?)";
          if ($st = $conexion->prepare($sql)) {
            $st->bind_param('iiidsss', $cliente_id, $gimnasio_id, $plan_id, $monto, $ruta_publica, $fecha, $adicionales_json);
            if ($st->execute()) { $mensaje = "✅ Comprobante enviado correctamente. Será validado en breve."; }
            else { @unlink($ruta_fisica); $mensaje = "❌ Error al guardar el registro en la base de datos."; }
            $st->close();
          } else {
            @unlink($ruta_fisica);
            $mensaje = "❌ Error interno (prepare).";
          }
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>💳 Pago Online</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ================== BASE PANEL ================== */
    :root{
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:900px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .card{ padding:18px }
    .muted{ color:var(--muted) }

    /* ================== FORM ================== */
    .form-grid{ display:grid; gap:12px; }
    label{ font-weight:700; font-size:.95rem; }
    select, input[type="file"]{ width:100%; padding:12px; background:#0f1115; border:1px solid var(--border); color:var(--fg); border-radius:12px; }
    .chk{ display:flex; align-items:center; gap:8px; }
    .btn{ display:inline-block; width:100%; padding:12px; background:var(--acc); color:#111; border:0; border-radius:14px; font-weight:800; cursor:pointer; margin-top:8px; }
    .tot{ font-weight:800; margin-top:6px; }
    .alias{ text-align:center; color:#ffd600; font-size:18px; margin: 6px 0 14px; }
    .alias strong{ color:#fff; }
    .alert-ok{ padding:10px 12px; border:1px solid rgba(34,197,94,.35); background:rgba(34,197,94,.12); color:#bbf7d0; border-radius:12px; margin-top:12px; }
    .alert-err{ padding:10px 12px; border:1px solid rgba(239,68,68,.35); background:rgba(239,68,68,.12); color:#fecaca; border-radius:12px; margin-top:12px; }
  </style>
  <script>
    function calcularTotal() {
      let total = 0;
      const planSel = document.getElementById('plan_id');
      if (planSel && planSel.value !== "0") {
        total += parseFloat(planSel.selectedOptions[0].dataset.precio || 0);
      }
      document.querySelectorAll('input[name="adicionales[]"]:checked').forEach(el=>{
        total += parseFloat(el.dataset.precio || 0);
      });
      document.getElementById('monto').value = total.toFixed(2);
      document.getElementById('total_mostrar').innerText = "$" + total.toFixed(2);
    }
    document.addEventListener('DOMContentLoaded', calcularTotal);
  </script>
</head>
<body>

  <?php
    // ===== Menú REUSABLE =====
    require_once __DIR__.'/menu_cliente.php';
    render_menu_cliente('pago_online'); // pestaña activa
  ?>

  <div class="container">
    <section class="glass card">
      <h2>💳 Pago por Transferencia</h2>
      <p class="alias">💰 Alias para transferencia:<br><strong><?= h($alias ?: 'No disponible') ?></strong></p>

      <form class="form-grid" method="POST" enctype="multipart/form-data" oninput="calcularTotal()">
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

        <div>
          <label>Seleccioná un plan</label>
          <select name="plan_id" id="plan_id">
            <option value="0" data-precio="0">-- Solo adicionales --</option>
            <?php foreach($planes as $p): ?>
              <option value="<?= (int)$p['id'] ?>" data-precio="<?= h($p['precio']) ?>">
                <?= h($p['nombre']) ?> — $<?= number_format((float)$p['precio'], 2, ',', '.') ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <?php if (!empty($adicionales)): ?>
          <div>
            <label>Adicionales (opcionales)</label>
            <?php foreach($adicionales as $a): ?>
              <label class="chk">
                <input type="checkbox" name="adicionales[]" value="<?= (int)$a['id'] ?>" data-precio="<?= h($a['precio']) ?>">
                <span><?= h($a['nombre']) ?> ($<?= number_format((float)$a['precio'], 2, ',', '.') ?>)</span>
              </label>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="tot">Total a pagar: <span id="total_mostrar">$0.00</span></div>
        <input type="hidden" name="monto" id="monto" value="0">

        <div>
          <label>Comprobante (imagen o PDF)</label>
          <input type="file" name="comprobante" accept=".jpg,.jpeg,.png,.pdf" required>
        </div>

        <button class="btn" type="submit">Enviar Comprobante</button>
      </form>

      <?php if ($mensaje): ?>
        <p class="<?= str_starts_with($mensaje,'✅') ? 'alert-ok' : 'alert-err' ?>"><?= h($mensaje) ?></p>
      <?php endif; ?>
    </section>
  </div>

</body>
</html>
