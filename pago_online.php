<?php
// pago_online.php — Carga de comprobante con planes/adicionales (MENÚ UNIFICADO)
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
    /* ================== MENÚ UNIFICADO (idéntico al panel) ================== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);

      /* Base panel */
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; }
    .mnu-btn--ghost{ background:transparent; color:var(--mnu-fg); border:1px solid var(--mnu-border); }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:var(--mnu-fg); text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important; }

    /* ================== BASE PANEL ================== */
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

  <!-- ===== Menú Unificado ===== -->
  <header>
    <div class="mnu-bar">
      <button class="mnu-btn mnu-open">☰ Menú</button>
      <div class="mnu-title">Panel Cliente</div>
      <div class="mnu-spacer"></div>
      <a class="mnu-btn mnu-btn--ghost" href="cliente_acceso.php?logout=1">Salir</a>
    </div>

    <!-- Tabs inline (PC) -->
    <nav class="mnu-inline">
      <a class="mnu-tab" href="panel_cliente.php">🏠 Inicio</a>
      <a class="mnu-tab" href="ver_turnos_cliente.php">📅 Ver Turnos</a>
      <a class="mnu-tab" href="ver_mis_pagos.php">💳 Mis Pagos</a>
      <a class="mnu-tab" href="pago_online.php">⚡ Pago Online</a>
      <a class="mnu-tab" href="form_progreso.php">📈 Ver Progreso</a>
      <a class="mnu-tab" href="evolucion_cliente.php">📊 Evolución</a>
      <a class="mnu-tab" href="tienda_indumentaria.php">🛍️ Indumentaria</a>
      <a class="mnu-tab" href="asistente_ia.php">🤖 Asistente IA</a>
      <a class="mnu-tab" href="cena_fin_anio.php">🍽️ Cena Fin de Año</a>
      <a class="mnu-tab" href="qr_maquinas.php">🧰 QR de Máquinas</a>
    </nav>

    <!-- Drawer (móvil) -->
    <div class="mnu-backdrop" id="mnu-backdrop"></div>
    <aside class="mnu-drawer" id="mnu-drawer">
      <div class="mnu-head">
        <button class="mnu-close" id="mnu-close">✕</button>
        <div class="mnu-title">Menú</div>
      </div>
      <ul class="mnu-list">
        <li><a class="mnu-item" href="panel_cliente.php"><span class="mnu-item__icon">🏠</span><span class="mnu-item__text">Inicio</span></a></li>
        <li><a class="mnu-item" href="ver_turnos_cliente.php"><span class="mnu-item__icon">📅</span><span class="mnu-item__text">Ver Turnos</span></a></li>
        <li><a class="mnu-item" href="ver_mis_pagos.php"><span class="mnu-item__icon">💳</span><span class="mnu-item__text">Mis Pagos</span></a></li>
        <li><a class="mnu-item" href="pago_online.php"><span class="mnu-item__icon">⚡</span><span class="mnu-item__text">Pago Online</span></a></li>
        <li><a class="mnu-item" href="form_progreso.php"><span class="mnu-item__icon">📈</span><span class="mnu-item__text">Ver Progreso</span></a></li>
        <li><a class="mnu-item" href="evolucion_cliente.php"><span class="mnu-item__icon">📊</span><span class="mnu-item__text">Evolución</span></a></li>
        <li><a class="mnu-item" href="tienda_indumentaria.php"><span class="mnu-item__icon">🛍️</span><span class="mnu-item__text">Indumentaria</span></a></li>
        <li><a class="mnu-item" href="asistente_ia.php"><span class="mnu-item__icon">🤖</span><span class="mnu-item__text">Asistente IA</span></a></li>
        <li><a class="mnu-item" href="cena_fin_anio.php"><span class="mnu-item__icon">🍽️</span><span class="mnu-item__text">Cena Fin de Año</span></a></li>
        <li><a class="mnu-item" href="qr_maquinas.php"><span class="mnu-item__icon">🧰</span><span class="mnu-item__text">QR de Máquinas</span></a></li>
        <li><a class="mnu-item" href="cliente_acceso.php?logout=1"><span class="mnu-item__icon">🚪</span><span class="mnu-item__text">Salir</span></a></li>
      </ul>
    </aside>
  </header>

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

  <script>
  // ===== Menú (abrir/cerrar + bloquear scroll) =====
  (function(){
    const drawer   = document.getElementById('mnu-drawer');
    const backdrop = document.getElementById('mnu-backdrop');
    const openBtn  = document.querySelector('.mnu-open');
    const closeBtn = document.getElementById('mnu-close');
    const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
    function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); lock(true); }
    function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); lock(false); }
    openBtn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);
    window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
  })();
  </script>
</body>
</html>
