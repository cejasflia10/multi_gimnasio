<?php
/* ================= DEBUG (desactivar en producción si querés) ================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/* ============================================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
@include __DIR__ . '/menu_cliente.php';

/* ---------- Datos de sesión ---------- */
$cliente_id  = isset($_SESSION['cliente_id'])  ? (int)$_SESSION['cliente_id']  : 0;
$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : 0;

if (!$cliente_id || !$gimnasio_id) {
  echo "<div style='color:red; text-align:center; font-size:18px; padding:12px'>❌ Acceso denegado.</div>";
  exit;
}

/* ---------- Helpers ---------- */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function num($n, $dec=1){ return number_format((float)$n, $dec, ',', '.'); }

/* ---------- Cliente ---------- */
$st = $conexion->prepare("SELECT * FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1");
$st->bind_param("ii", $cliente_id, $gimnasio_id);
$st->execute();
$cliente = $st->get_result()->fetch_assoc();
$st->close();

if (!$cliente) {
  echo "<p style='color:red; padding:20px;'>⚠️ No se encontró el cliente.</p>";
  exit;
}
$nombre_cliente = trim(($cliente['apellido'] ?? '').' '.($cliente['nombre'] ?? ''));

/* Altura -> metros (acepta altura_cm o altura) */
$altura_raw = $cliente['altura_cm'] ?? ($cliente['altura'] ?? null);
$altura_m = ($altura_raw && (float)$altura_raw > 0)
  ? ((float)$altura_raw > 3 ? ((float)$altura_raw / 100.0) : (float)$altura_raw)
  : 1.70;

/* ---------- Peso de referencia (último progreso si existe) ---------- */
$peso_ref = null;
$resTmp = $conexion->query("SHOW TABLES LIKE 'progreso_cliente'");
if ($resTmp && $resTmp->num_rows) {
  // intentamos columnas más comunes
  $st = $conexion->prepare("
    SELECT COALESCE(peso_despues, peso_fin, peso_post, peso) AS p
    FROM progreso_cliente
    WHERE (cliente_id=? OR id_cliente=?) AND (gimnasio_id=? OR id_gimnasio=?)
    ORDER BY COALESCE(fecha, created_at, fecha_registro, dia, id) DESC
    LIMIT 1
  ");
  $st->bind_param("iiii", $cliente_id, $cliente_id, $gimnasio_id, $gimnasio_id);
  $st->execute();
  $r = $st->get_result()->fetch_assoc();
  if ($r && isset($r['p']) && (float)$r['p']>0) $peso_ref = (float)$r['p'];
  $st->close();
}
if (!$peso_ref || $peso_ref <= 0) {
  $peso_ref = isset($cliente['peso']) ? (float)$cliente['peso'] : 70.0;
}

/* ---------- Objetivos ---------- */
$imc = ($altura_m > 0) ? round($peso_ref / ($altura_m*$altura_m), 1) : 0.0;
$objetivo = strtolower(trim($_GET['objetivo'] ?? ''));
if (!in_array($objetivo, ['bajar peso','mantener','subir peso'], true)) {
  if     ($imc >= 25)  $objetivo = 'bajar peso';
  elseif ($imc < 18.5) $objetivo = 'subir peso';
  else                 $objetivo = 'mantener';
}
$agua_l = max(1.5, round($peso_ref * 0.035, 1)); // 35 ml/kg
$prot_gkg = ($objetivo === 'bajar peso') ? 1.6 : (($objetivo === 'subir peso') ? 2.0 : 1.4);
$proteinas_obj = (int)round($peso_ref * $prot_gkg);
$kcal_base = (int)round($peso_ref * 30);
$kcal_obj  = $kcal_base + (($objetivo === 'subir peso') ? +300 : (($objetivo === 'bajar peso') ? -400 : 0));

/* ---------- Gemini (API Key tuya) ---------- */
$apiKey = 'AIzaSyDVMv4gliTqbrHqdgNcql7P8eP8jQL7Iwo';

$resultado_modelo = '';
$error_modelo = '';
$nombre_detectado = 'Comida detectada';
$kcal_detectadas  = 0;

/* ---------- Procesar imagen ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imagen_base64'])) {
  $base64 = (string)$_POST['imagen_base64'];
  $mime   = (stripos($base64, 'image/png') !== false) ? 'image/png' : 'image/jpeg';
  $payload_b64 = preg_replace('#^data:image/[^;]+;base64,#', '', $base64);

  if (!function_exists('curl_init')) {
    $error_modelo = "⚠️ cURL no está habilitado en el servidor. Activá la extensión php-curl.";
  } else {
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=".$apiKey;
    $json_payload = json_encode([
      "contents" => [[
        "parts" => [
          ["inline_data" => ["mime_type" => $mime, "data" => $payload_b64]],
          ["text" => "En español: indica qué comida es y su valor nutricional. Devuelve una sola oración con el nombre y las calorías aproximadas (kcal)."]
        ]
      ]],
      "generationConfig" => ["temperature" => 0.4, "maxOutputTokens" => 150]
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS     => $json_payload,
      CURLOPT_TIMEOUT        => 25
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
      $error_modelo = "⚠️ Error de conexión con Gemini: ".$err;
    } else {
      $data = json_decode($resp, true);
      if ($code === 200 && isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $resultado_modelo = trim($data['candidates'][0]['content']['parts'][0]['text']);
        if (preg_match('/(\d{2,5})\s?k?cal/i', $resultado_modelo, $m)) $kcal_detectadas = (int)$m[1];
        if (preg_match('/^(.{3,80}?)(?:\s+(?:contiene|tiene|aprox|aprox\.|≈))/iu', $resultado_modelo, $n)) {
          $nombre_detectado = trim($n[1]);
        } elseif (preg_match('/^([A-ZÁÉÍÓÚÑa-záéíóúñ ]{3,80})/u', $resultado_modelo, $n2)) {
          $nombre_detectado = trim($n2[1]);
        }
      } else {
        $error_text = $data['error']['message'] ?? 'Respuesta inesperada de Gemini';
        $error_modelo = "⚠️ No se pudo procesar la imagen (HTTP {$code}). ".$error_text;
      }
    }
  }
}

/* =============================================================================
   FORZAMOS TABLA ESTÁNDAR registro_comidas (y la creamos si no existe)
   ========================================================================== */
$conexion->query("CREATE TABLE IF NOT EXISTS registro_comidas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cliente_id INT NOT NULL,
  gimnasio_id INT NOT NULL,
  fecha DATE NOT NULL,
  comida VARCHAR(255) NOT NULL,
  porciones DECIMAL(6,2) NOT NULL,
  calorias DECIMAL(8,2) NOT NULL,
  total_calorias DECIMAL(10,2) NOT NULL,
  INDEX idx_cli_gym_fecha (cliente_id, gimnasio_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

/* ---------- Guardar comida ---------- */
$mensaje_guardado = '';
if (isset($_POST['guardar']) && !empty($_POST['nombre']) && !empty($_POST['porciones']) && !empty($_POST['calorias'])) {
  $nombre = trim((string)$_POST['nombre']);
  $porc   = (float)$_POST['porciones'];
  $kcal   = (float)$_POST['calorias'];
  $total  = $porc * $kcal;
  $hoy_sql = date('Y-m-d');

  $sql = "INSERT INTO registro_comidas
            (cliente_id, gimnasio_id, fecha, comida, porciones, calorias, total_calorias)
          VALUES (?, ?, ?, ?, ?, ?, ?)";
  $st = $conexion->prepare($sql);
  // 2 ints, 1 string(fecha), 1 string(comida), 3 doubles
  $st->bind_param("iissddd", $cliente_id, $gimnasio_id, $hoy_sql, $nombre, $porc, $kcal, $total);

  if ($st->execute()) $mensaje_guardado = "✅ Comida registrada correctamente.";
  else                $mensaje_guardado = "⚠️ No se pudo registrar: ".$st->error;
  $st->close();
}

/* ---------- Totales y listado del día ---------- */
try { $tz = new DateTimeZone('America/Argentina/San_Luis'); }
catch(Throwable $e){ $tz = new DateTimeZone('America/Argentina/Buenos_Aires'); }
$hoy = (new DateTime('today', $tz))->format('Y-m-d');

$consumidas_hoy = 0;
$st = $conexion->prepare("SELECT COALESCE(SUM(total_calorias),0) AS t
                          FROM registro_comidas
                          WHERE cliente_id=? AND gimnasio_id=? AND fecha=?");
$st->bind_param("iis", $cliente_id, $gimnasio_id, $hoy);
$st->execute();
$r = $st->get_result()->fetch_assoc();
$consumidas_hoy = (int)($r['t'] ?? 0);
$st->close();

$comidas_hoy = [];
$st = $conexion->prepare("SELECT id, comida, porciones, calorias, total_calorias
                          FROM registro_comidas
                          WHERE cliente_id=? AND gimnasio_id=? AND fecha=?
                          ORDER BY id DESC LIMIT 10");
$st->bind_param("iis", $cliente_id, $gimnasio_id, $hoy);
$st->execute();
$res = $st->get_result();
while ($row = $res->fetch_assoc()) { $comidas_hoy[] = $row; }
$st->close();

/* Quemadas hoy (opcional desde progreso_cliente) */
$quemadas_hoy = 0;
$resTmp = $conexion->query("SHOW TABLES LIKE 'progreso_cliente'");
if ($resTmp && $resTmp->num_rows) {
  $st = $conexion->prepare("
    SELECT COALESCE(SUM(COALESCE(calorias_estimadas,calorias_quemadas,kcal)),0) AS t
    FROM progreso_cliente
    WHERE (cliente_id=? OR id_cliente=?) AND (gimnasio_id=? OR id_gimnasio=?) AND (fecha=? OR created_at=?)
  ");
  $st->bind_param("iiiiss", $cliente_id, $cliente_id, $gimnasio_id, $gimnasio_id, $hoy, $hoy);
  if ($st->execute()) {
    $r = $st->get_result()->fetch_assoc();
    if ($r && isset($r['t'])) $quemadas_hoy = (int)$r['t'];
  }
  $st->close();
}

$balance_neto = $consumidas_hoy - $quemadas_hoy;
$estado_neto  = ($balance_neto > 250) ? 'Superávit' : (($balance_neto < -250) ? 'Déficit' : 'Equilibrado');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📷 Escanear comida (IA)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{ --bg:#0b0b0b; --card:#111; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
    *{box-sizing:border-box}
    body{ margin:0; background:var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial; padding:16px; text-align:center }
    .container{ max-width:1100px; margin:0 auto }
    h2{ margin:8px 0 12px }
    .row{ display:grid; grid-template-columns: 1fr; gap:12px; text-align:left }
    @media (min-width:900px){ .row{ grid-template-columns: 1fr 1fr; } }
    .card{ background:var(--card); border:1px solid var(--border); border-radius:16px; padding:14px }
    video, canvas{ width:100%; max-height:420px; object-fit:cover; border-radius:12px; border:1px solid var(--border) }
    .controls{ display:flex; gap:8px; flex-wrap:wrap; justify-content:center; margin-top:8px }
    .btn{ padding:10px 14px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:#fff; font-weight:700; cursor:pointer; text-decoration:none }
    .btn-primary{ background:var(--acc); color:#111; border:none }
    .grid4{ display:grid; gap:10px; grid-template-columns:1fr 1fr; }
    @media (min-width:760px){ .grid4{ grid-template-columns: repeat(4,1fr); } }
    .kpi{ background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:10px; text-align:center }
    .kpi b{ display:block; font-size:18px; margin-top:4px }
    .muted{ color:var(--muted) }
    table{ width:100%; border-collapse:collapse; font-size:14px; margin-top:10px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:center }
    th{ color:var(--muted); font-weight:700; background:#0f1118 }
    .progress{ height:10px; background:#1f2430; border-radius:999px; overflow:hidden; border:1px solid var(--border) }
    .progress > span{ display:block; height:100%; background:var(--acc); width:0% }
    .pill{ display:inline-block; padding:2px 8px; border-radius:999px; border:1px solid var(--border); font-size:12px; margin-left:6px }
    label{ font-weight:700 }
    input[type="number"], input[type="text"]{ width:100%; padding:10px; border-radius:12px; border:1px solid var(--border); background:#1a1d24; color:#fff; }
    .form-mini{ display:grid; gap:8px; grid-template-columns: 1fr 1fr; margin-top:8px }
    .form-mini .full{ grid-column: 1 / -1; }
  </style>
</head>
<body>
<div class="container">
  <h2>📷 Escanear comida con IA <span class="pill"><?= h($objetivo) ?></span></h2>

  <section class="grid4" style="margin-bottom:10px">
    <div class="kpi">IMC<b><?= num($imc,1) ?></b></div>
    <div class="kpi">Objetivo ingesta<b><?= (int)$kcal_obj ?> kcal</b></div>
    <div class="kpi">Proteínas<b><?= (int)$proteinas_obj ?> g/día</b></div>
    <div class="kpi">Agua<b><?= num($agua_l,1) ?> L/día</b></div>
  </section>

  <section class="card" style="margin-bottom:12px; text-align:left">
    <h3 style="margin:0 0 8px">📅 Hoy</h3>
    <div class="grid4">
      <div class="kpi">Ingeridas<b><?= (int)$consumidas_hoy ?> kcal</b></div>
      <div class="kpi">Quemadas<b><?= (int)$quemadas_hoy ?> kcal</b></div>
      <div class="kpi">Balance neto<b><?= (int)($consumidas_hoy - $quemadas_hoy) ?> kcal</b></div>
      <div class="kpi">Estado<b><?= h($estado_neto) ?></b></div>
    </div>
    <div style="margin-top:10px">
      <div class="muted" style="display:flex; justify-content:space-between">
        <span>Progreso de ingesta</span><span><?= (int)$consumidas_hoy ?> / <?= (int)$kcal_obj ?> kcal</span>
      </div>
      <?php $pct = max(0, min(100, $kcal_obj>0 ? round($consumidas_hoy*100/$kcal_obj) : 0)); ?>
      <div class="progress"><span style="width:<?= $pct ?>%"></span></div>
    </div>
  </section>

  <div class="row">
    <!-- Cámara -->
    <section class="card">
      <h3 style="margin:0 0 6px">📸 Cámara</h3>
      <div id="camStatus" class="muted" style="margin-bottom:8px">Listo. Hacé clic en “Habilitar cámara”.</div>
      <video id="video" autoplay playsinline muted style="display:none"></video>
      <div class="controls">
        <button id="btnEnable" class="btn">Habilitar cámara</button>
        <button id="btnShot" class="btn btn-primary" disabled>Tomar foto</button>
        <label class="btn">
          Subir imagen
          <input type="file" id="fileInput" accept="image/*" capture="environment" style="display:none">
        </label>
      </div>
      <canvas id="canvas" style="display:none"></canvas>
      <form method="POST" id="form_enviar" style="display:none; margin-top:8px">
        <input type="hidden" name="imagen_base64" id="imagen_base64">
        <button type="submit" class="btn btn-primary">Enviar imagen</button>
      </form>
    </section>

    <!-- Resultado + registro manual -->
    <section class="card">
      <h3 style="margin:0 0 6px">🔎 Resultado</h3>
      <p style="margin:6px 0"><strong>Cliente:</strong> <?= h($nombre_cliente) ?></p>
      <?php if ($resultado_modelo): ?>
        <p><?= nl2br(h($resultado_modelo)) ?></p>
      <?php elseif ($error_modelo): ?>
        <p style="color:#ff6b6b"><?= h($error_modelo) ?></p>
      <?php else: ?>
        <p class="muted">Tomá una foto o subí una imagen para analizar la comida.</p>
      <?php endif; ?>

      <hr style="border-color:#222">

      <h4 style="margin:6px 0">💾 Registrar manualmente</h4>
      <form method="POST" class="form-mini" autocomplete="off">
        <input type="hidden" name="guardar" value="1">
        <div class="full">
          <label>Comida</label>
          <input type="text" name="nombre" placeholder="Ej: Ensalada de pollo" value="<?= h($nombre_detectado) ?>" required>
        </div>
        <div>
          <label>Porciones</label>
          <input type="number" name="porciones" min="0.1" step="0.1" value="1" required>
        </div>
        <div>
          <label>Calorías por porción</label>
          <input type="number" name="calorias" min="1" step="1" value="<?= (int)$kcal_detectadas ?>" required>
        </div>
        <div class="full" style="text-align:right">
          <button type="submit" class="btn btn-primary">Guardar comida</button>
        </div>
      </form>

      <?php if (!empty($mensaje_guardado)): ?>
        <p style="color:#22c55e; margin-top:6px"><?= h($mensaje_guardado) ?></p>
      <?php endif; ?>
    </section>
  </div>

  <!-- Lista del día -->
  <section class="card" style="margin-top:12px; text-align:left">
    <h3 style="margin:0 0 6px">🍽️ Comidas de hoy</h3>
    <table>
      <thead>
        <tr>
          <th>Comida</th>
          <th>Porciones</th>
          <th>Cal/porción</th>
          <th>Total</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($comidas_hoy)): ?>
          <?php foreach ($comidas_hoy as $c): ?>
            <tr>
              <td><?= h($c['comida']) ?></td>
              <td><?= num($c['porciones'],2) ?></td>
              <td><?= num($c['calorias'],0) ?> kcal</td>
              <td><strong><?= num($c['total_calorias'],0) ?> kcal</strong></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr><td colspan="4" class="muted">Aún no registraste comidas hoy.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>
</div>

<script>
(function(){
  var camStatus  = document.getElementById('camStatus');
  var video      = document.getElementById('video');
  var canvas     = document.getElementById('canvas');
  var imgB64     = document.getElementById('imagen_base64');
  var formEnviar = document.getElementById('form_enviar');
  var fileInput  = document.getElementById('fileInput');
  var btnShot    = document.getElementById('btnShot');
  var btnEnable  = document.getElementById('btnEnable');

  var stream = null;
  var videoReady = false;

  function setStatus(msg, ok){ camStatus.textContent = msg; camStatus.style.color = ok ? '#22c55e' : '#a0a7b4'; }
  function setError(msg){ camStatus.textContent = msg; camStatus.style.color = '#ff6b6b'; }

  var isSecure = (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1');
  if (!isSecure) setError('⚠️ Para usar la cámara necesitás HTTPS o entrar por http://localhost');
  else setStatus('Listo. Hacé clic en “Habilitar cámara”.');

  btnEnable.addEventListener('click', function(){
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setError('Tu navegador no soporta cámara. Usá “Subir imagen”.'); return;
    }
    setStatus('Solicitando permisos…');
    navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } },
      audio: false
    }).then(function(str){
      stream = str;
      video.srcObject = stream;
      video.onloadedmetadata = function(){
        video.play().then(function(){
          video.style.display = 'block';
          videoReady = true;
          btnShot.disabled = false;
          setStatus('Cámara lista ✅', true);
        }).catch(function(err){ setError('No se pudo reproducir el video: ' + err.message); });
      };
    }).catch(function(err){
      if (err && err.name === 'NotAllowedError') setError('Permiso de cámara denegado. Activá los permisos o usá “Subir imagen”.');
      else if (err && err.name === 'NotFoundError') setError('No se encontró ninguna cámara. Probá con “Subir imagen”.');
      else setError('Error iniciando cámara: ' + (err && err.message ? err.message : err));
    });
  });

  btnShot.addEventListener('click', function(){
    if (!videoReady || !video.videoWidth) { setError('La cámara aún no está lista. Probá de nuevo.'); return; }
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    var ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0);
    var base64 = canvas.toDataURL('image/jpeg', 0.92);
    imgB64.value = base64;
    formEnviar.style.display = 'block';
    formEnviar.scrollIntoView({ behavior: 'smooth' });
    setStatus('Foto capturada. Podés “Enviar imagen”.', true);
  });

  fileInput.addEventListener('change', function(e){
    var file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
    if (!file) return;
    var reader = new FileReader();
    reader.onload = function(){
      imgB64.value = reader.result;
      formEnviar.style.display = 'block';
      formEnviar.scrollIntoView({ behavior:'smooth' });
      setStatus('Imagen cargada. Podés “Enviar imagen”.', true);
    };
    reader.readAsDataURL(file);
  });

  window.addEventListener('beforeunload', function(){
    if (stream) stream.getTracks().forEach(function(t){ t.stop(); });
  });
})();
</script>
</body>
</html>
