<?php
/* registrar_asistencia.php — Fondo Cloudinary + estado Clody + keepalive
   — Botones “Ver/Quitar” reubicados en panel de opciones
   — Listados de ingresos ocultos por defecto con toggle
*/
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store, no-cache, must-revalidate');

require_once __DIR__ . '/conexion.php';

date_default_timezone_set('America/Argentina/San_Luis');
$hoy         = date('Y-m-d');
$hora_actual = date('H:i:s');

/* ====== PING keepalive ====== */
if (isset($_GET['ping'])) {
  $_SESSION['last_ping'] = time();
  header('Cache-Control: no-store, no-cache, must-revalidate');
  http_response_code(204);
  exit;
}

/* ===== Sesión ===== */
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf_token'];

/* ===== Datos del gimnasio ===== */
$nombre_gimnasio = 'Gimnasio';
$logo_gimnasio   = 'logo.png';
if ($gimnasio_id > 0) {
  $res = $conexion->query("SELECT nombre, logo FROM gimnasios WHERE id = {$gimnasio_id} LIMIT 1");
  if ($res && $row = $res->fetch_assoc()) {
    if (!empty($row['nombre'])) $nombre_gimnasio = $row['nombre'];
    if (!empty($row['logo']))   $logo_gimnasio   = $row['logo'];
  }
}

/* ===== Asegurar columna de fondo ===== */
$ck = $conexion->query("SELECT 1 
  FROM INFORMATION_SCHEMA.COLUMNS 
  WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'configuracion_gimnasio' 
    AND COLUMN_NAME = 'scan_bg_image_url' LIMIT 1");
if (!$ck || $ck->num_rows === 0) {
  @ $conexion->query("ALTER TABLE configuracion_gimnasio ADD COLUMN scan_bg_image_url VARCHAR(255) NULL");
}

/* ===== Leer fondo actual ===== */
$scan_bg = '';
if ($gimnasio_id > 0) {
  $rs = $conexion->query("SELECT scan_bg_image_url FROM configuracion_gimnasio WHERE gimnasio_id={$gimnasio_id} LIMIT 1");
  if ($rs && $r = $rs->fetch_assoc()) $scan_bg = (string)($r['scan_bg_image_url'] ?? '');
}

/* ===== Cloudinary (sin SDK) ===== */
const CLOUD_ENABLED      = true;
const CLOUD_NAME         = 'ddfugds9b';
const CLOUD_API_KEY      = '657814174747186';
const CLOUD_API_SECRET   = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';
const CLOUD_FOLDER_ROOT  = 'ROOT';

function cloud_is_active(): bool {
  if (!CLOUD_ENABLED) return false;
  if (!function_exists('curl_version')) return false;
  if (!CLOUD_NAME || !CLOUD_API_KEY || !CLOUD_API_SECRET) return false;
  return true;
}
$cloud_active = cloud_is_active();

function cloud_sign_params(array $params, string $api_secret): string {
  ksort($params);
  $pairs = [];
  foreach ($params as $k => $v) {
    if ($v === null || $v === '') continue;
    $pairs[] = $k . '=' . $v;
  }
  return sha1(implode('&', $pairs) . $api_secret);
}
function cloud_direct_upload(string $file_path, array $options = []): ?array {
  if (!cloud_is_active()) return null;
  $timestamp = time();
  $params = ['timestamp' => $timestamp];
  if (!empty($options['folder']))    $params['folder']    = $options['folder'];
  if (!empty($options['public_id'])) $params['public_id'] = $options['public_id'];
  $signature = cloud_sign_params($params, CLOUD_API_SECRET);
  $post = $params + ['signature'=>$signature, 'api_key'=>CLOUD_API_KEY, 'file'=>new CURLFile($file_path)];
  $url  = "https://api.cloudinary.com/v1_1/".rawurlencode(CLOUD_NAME)."/image/upload";
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL=>$url, CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_POSTFIELDS=>$post, CURLOPT_TIMEOUT=>60
  ]);
  $resp = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_errno($ch) ? curl_error($ch) : null;
  curl_close($ch);
  if ($err || $http < 200 || $http >= 300) return null;
  $j = json_decode($resp, true);
  return is_array($j) ? $j : null;
}

/* ===== Acciones fondo ===== */
$advertencia = '';
$tipo_resultado = '';
if ($gimnasio_id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
  if ($_POST['act'] === 'bg_upload' && !empty($_FILES['scan_bg_file']['tmp_name'])) {
    if (!$cloud_active) {
      $advertencia = "❌ Cloudinary no está activo. Revisá configuración.";
      $tipo_resultado = "alerta";
    } else {
      $f = $_FILES['scan_bg_file'];
      if ($f['error'] === UPLOAD_ERR_OK) {
        $mime = @mime_content_type($f['tmp_name']);
        $okMime = ['image/jpeg','image/png','image/webp','image/gif','image/heic','image/heif'];
        if (in_array($mime, $okMime, true)) {
          $folder   = CLOUD_FOLDER_ROOT.'/registros/'.$gimnasio_id;
          $basename = 'scanbg_'.date('Ymd_His').'_'.bin2hex(random_bytes(4));
          $up = cloud_direct_upload($f['tmp_name'], ['folder'=>$folder, 'public_id'=>$basename]);
          if ($up && !empty($up['secure_url'])) {
            $url = $conexion->real_escape_string($up['secure_url']);
            $conexion->query("INSERT INTO configuracion_gimnasio (gimnasio_id, scan_bg_image_url)
                              VALUES ({$gimnasio_id}, '{$url}')
                              ON DUPLICATE KEY UPDATE scan_bg_image_url=VALUES(scan_bg_image_url)");
            $scan_bg = $up['secure_url'];
            $advertencia = "✅ Fondo actualizado.";
            $tipo_resultado = "ok";
          } else {
            $advertencia = "❌ No se pudo subir a Cloudinary.";
            $tipo_resultado = "alerta";
          }
        } else {
          $advertencia = "❌ Formato de imagen no permitido.";
          $tipo_resultado = "alerta";
        }
      } else {
        $advertencia = "❌ Error al leer el archivo.";
        $tipo_resultado = "alerta";
      }
    }
  }
  if ($_POST['act'] === 'bg_delete') {
    $conexion->query("UPDATE configuracion_gimnasio SET scan_bg_image_url=NULL WHERE gimnasio_id={$gimnasio_id} LIMIT 1");
    $scan_bg = '';
    $advertencia = "✅ Fondo eliminado.";
    $tipo_resultado = "ok";
  }
}

/* ===== Rutas AJAX ===== */
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$basePath   = str_replace('\\','/', dirname($scriptName));
if ($basePath === '.' || $basePath === '/' || $basePath === '\\') $basePath = '';
$URL_AJAX_SELF   = $basePath . '/' . basename(__FILE__) . '?ajax=1';
$URL_AJAX_PROF   = $basePath . '/ajax_ingresos_profesores.php';
$URL_AJAX_CLIENT = $basePath . '/ajax_ingresos_clientes.php';

/* ===== Lógica principal de registros ===== */
function procesar_codigo(mysqli $db, int $gymId, string $codigo, string $hoy, string $hora_actual): array {
  $mensaje = ""; $tipo = "ok";

  // Profesor por DNI
  $prof_stmt = $db->prepare("SELECT id, apellido, nombre FROM profesores WHERE dni = ? AND gimnasio_id = ?");
  $prof_stmt->bind_param("si", $codigo, $gymId);
  $prof_stmt->execute();
  $prof = $prof_stmt->get_result()->fetch_assoc();
  $prof_stmt->close();

  if ($prof) {
    $prof_id     = (int)$prof['id'];
    $nombre_prof = trim(($prof['apellido'] ?? '') . ' ' . ($prof['nombre'] ?? ''));

    $q = $db->query("
      SELECT id, hora_entrada, hora_salida
      FROM asistencias_profesores
      WHERE profesor_id = {$prof_id}
        AND fecha = '{$db->real_escape_string($hoy)}'
        AND gimnasio_id = {$gymId}
      ORDER BY id DESC
      LIMIT 1
    ");
    if ($q && $r = $q->fetch_assoc()) {
      if (empty($r['hora_salida'])) {
        $db->query("UPDATE asistencias_profesores
                    SET hora_salida = '{$db->real_escape_string($hora_actual)}'
                    WHERE id = {$r['id']} LIMIT 1");
        $mensaje = "✅ Salida registrada para {$nombre_prof} a las {$hora_actual}.";
      } else {
        $db->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora)
                    VALUES ({$prof_id}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId}, '{$db->real_escape_string($hora_actual)}')");
        $mensaje = "✅ Nuevo ingreso registrado para {$nombre_prof} a las {$hora_actual}.";
      }
    } else {
      $db->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_entrada, gimnasio_id, hora)
                  VALUES ({$prof_id}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId}, '{$db->real_escape_string($hora_actual)}')");
      $mensaje = "✅ Ingreso registrado para {$nombre_prof} a las {$hora_actual}.";
    }
    return [$mensaje, $tipo];
  }

  // Cliente por DNI
  $stmt = $db->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
  $stmt->bind_param("si", $codigo, $gymId);
  $stmt->execute();
  $cliente = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($cliente) {
    $id_cliente = (int)$cliente['id'];

    $stmt2 = $db->prepare("SELECT clases_disponibles, fecha_vencimiento
                           FROM membresias
                           WHERE cliente_id = ? AND gimnasio_id = ?
                           ORDER BY fecha_vencimiento DESC
                           LIMIT 1");
    $stmt2->bind_param("ii", $id_cliente, $gymId);
    $stmt2->execute();
    $membresia = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    if ($membresia) {
      $clases      = (int)$membresia['clases_disponibles'];
      $vencimiento = $membresia['fecha_vencimiento'];

      if ($clases > 0 && $vencimiento >= $hoy) {
        $db->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id)
                    VALUES ({$id_cliente}, '{$db->real_escape_string($hoy)}', '{$db->real_escape_string($hora_actual)}', {$gymId})");
        $db->query("UPDATE membresias
                    SET clases_disponibles = clases_disponibles - 1
                    WHERE cliente_id = {$id_cliente}
                      AND fecha_vencimiento = '{$db->real_escape_string($vencimiento)}'
                      AND gimnasio_id = {$gymId}
                    LIMIT 1");
        $mensaje = "✅ Asistencia registrada para cliente a las {$hora_actual}.";
        $tipo    = "ok";
      } else {
        $mensaje = "❌ ¡Membresía vencida o sin clases disponibles!";
        $tipo    = "alerta";
      }
    } else {
      $mensaje = "❌ ¡El cliente no tiene membresía registrada!";
      $tipo    = "alerta";
    }
  } else {
    $mensaje = "❌ ¡Cliente/Profesor no encontrado!";
    $tipo    = "alerta";
  }
  return [$mensaje, $tipo];
}

/* ===== Respuesta AJAX ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax'])) {
  ini_set('display_errors', '0');
  while (ob_get_level()) ob_end_clean();
  header('Content-Type: application/json; charset=utf-8');

  $codigo  = trim((string)($_POST['codigo'] ?? ''));
  $csrf_in = (string)($_POST['csrf'] ?? '');

  if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_in)) {
    echo json_encode(['ok'=>false,'mensaje'=>'❌ CSRF inválido. Refrescá la página.','tipo'=>'alerta','sonido'=>true], JSON_UNESCAPED_UNICODE);
    exit;
  }
  if ($gimnasio_id <= 0 || $codigo === '') {
    echo json_encode(['ok'=>false,'mensaje'=>'❌ Acceso denegado o código vacío.','tipo'=>'alerta','sonido'=>true], JSON_UNESCAPED_UNICODE);
    exit;
  }

  [$advertencia, $tipo_resultado] = procesar_codigo($conexion, $gimnasio_id, $codigo, $hoy, $hora_actual);
  echo json_encode([
    'ok'      => true,
    'mensaje' => $advertencia,
    'tipo'    => $tipo_resultado,
    'sonido'  => ($tipo_resultado === 'alerta'),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Flujo no-AJAX ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo']) && !isset($_GET['ajax'])) {
  $codigo  = trim((string)$_POST['codigo']);
  $csrf_in = (string)$_POST['csrf'] ?? '';
  if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf_in)) {
    $advertencia = '❌ CSRF inválido. Refrescá la página.'; $tipo_resultado = 'alerta';
  } elseif ($gimnasio_id > 0 && $codigo !== '') {
    [$advertencia, $tipo_resultado] = procesar_codigo($conexion, $gimnasio_id, $codigo, $hoy, $hora_actual);
  } else {
    $advertencia = '❌ Acceso denegado o código vacío.'; $tipo_resultado = 'alerta';
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Registro de Asistencia</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{ --mut:#9fb0d3; --veil: .45; }

    /* Fondo Cloudinary + velo */
    body::before{
      content:"";
      position:fixed; inset:0;
      background:
        linear-gradient(180deg, rgba(0,0,0,var(--veil)), rgba(0,0,0, calc(var(--veil) + .20))),
        url('<?= htmlspecialchars($scan_bg ?: '') ?>') center/cover no-repeat;
      z-index:-1;
      opacity: <?= $scan_bg ? '1' : '0' ?>;
      transition: opacity .25s, background .2s;
      will-change: opacity, background;
      background-attachment: fixed;
    }

    .wrap{ max-width:1200px; margin:24px auto; padding:0 16px 40px; }

    .page-card{
      background:<?= $scan_bg ? 'rgba(17,18,20,.0)' : 'var(--card)' ?>;
      backdrop-filter: <?= $scan_bg ? 'none' : 'blur(0px)' ?>;
      border:1px solid var(--stroke);
      border-radius:18px; box-shadow:var(--shadow); padding:16px;
      transition: background .2s ease;
    }

    .encabezado{
      display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:12px;
    }
    .encabezado h1{
      margin:0; font-weight:900; letter-spacing:.4px;
      background:linear-gradient(90deg,var(--brand),var(--brand-2),var(--brand-3));
      -webkit-background-clip:text; background-clip:text; color:transparent;
    }
    #logoGym{ height:70px; object-fit:contain; background:#fff; border:1px solid var(--stroke); border-radius:12px; padding:6px; box-shadow:var(--shadow); }

    .clock{ color:var(--mut); }

    .cloud-status{
      display:inline-flex; align-items:center; gap:8px;
      padding:8px 10px; border-radius:999px; font-weight:800; font-size:.9rem;
      border:1px solid var(--stroke);
    }
    .cloud-ok{ background:#ecfeff; color:#155e75; }
    .cloud-bad{ background:#fff7ed; color:#9a3412; }

    .toolbar{
      display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:10px;
    }
    .bg-compact{
      display:flex; gap:8px; align-items:center; flex-wrap:wrap;
    }
    details.tools{
      display:inline-block; border:1px solid var(--stroke); border-radius:10px; padding:6px 10px; background:#0d0f14;
    }
    details.tools summary{ list-style:none; cursor:pointer; font-weight:800; }
    details.tools[open]{ background:#0f1117; }

    .bg-admin-row{ display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin-top:8px; }
    .bg-admin-row input[type="file"]{ background:#0d0f14; color:var(--ink); border:1px solid var(--stroke); border-radius:10px; padding:8px; }

    .btn{padding:.6rem .9rem;border-radius:12px;border:1px solid var(--stroke);background:var(--primary);color:#000;font-weight:800;cursor:pointer}
    .btn.gray{background:#475569;color:#fff}
    .btn.danger{background:#dc2626;color:#fff}
    .btn.outline{background:transparent;color:var(--ink)}
    .hint{color:#cbd5e1;font-size:.85rem}

    .scan{ margin: 14px 0; }
    .scan input[type="text"]{
      font-size: clamp(18px, 4.5vw, 22px);
      line-height: 1.2;
      padding: 14px 16px; width: 100%;
      border: 1px solid var(--stroke); border-radius: 12px;
      background:linear-gradient(180deg,#fff,#f7fafc); color:#0f172a; min-height: 52px;
      outline: none;
    }
    .scan input[type="text"]:focus{ box-shadow:0 0 0 3px rgba(245,158,11,.18); }

    .advertencia{ font-size: clamp(16px, 3.8vw, 18px); margin: 8px 0 14px; font-weight:800; text-align:center; }
    .advertencia.ok{ color:#16a34a; }
    .advertencia.err{ color:#b91c1c; }

    .row{ display:grid; grid-template-columns: 1fr 1fr; gap:16px; }
    @media (max-width: 900px){ .row{ grid-template-columns: 1fr; } }

    section h2{ margin:6px 0 10px; color:var(--brand); }

    .table-wrap{ background:#fff; border:1px solid var(--stroke); border-radius: 16px; overflow:auto; -webkit-overflow-scrolling:touch; box-shadow:var(--shadow); }
    table{ width:100%; border-collapse: collapse; min-width: 520px; background:#fff; }
    thead th{ background:#f7fafc; position: sticky; top: 0; z-index: 1; color:#0f172a; border-bottom:1px solid var(--stroke); }
    table th, table td{ border-bottom: 1px solid var(--stroke); padding: 10px 12px; text-align:center; white-space:nowrap; }
    tbody tr:hover{ background:#f9fafb; }

    @media (max-width: 599px){ #logoGym{ height:56px; } }

    /* Oculto por defecto */
    #listados[hidden]{ display:none; }
  </style>
  <script>
    const URL_AJAX_SELF   = <?= json_encode($URL_AJAX_SELF) ?>;
    const URL_AJAX_PROF   = <?= json_encode($URL_AJAX_PROF) ?>;
    const URL_AJAX_CLIENT = <?= json_encode($URL_AJAX_CLIENT) ?>;
    const CSRF            = <?= json_encode($csrf) ?>;
    const BG_URL          = <?= json_encode($scan_bg) ?>;
    const PING_URL        = <?= json_encode(basename(__FILE__).'?ping=1') ?>;

    function ajustarVeloAuto(url){
      if (!url) return;
      const img = new Image();
      img.crossOrigin = "anonymous";
      img.referrerPolicy = "no-referrer";
      img.onload = () => {
        try{
          const w=32,h=32;
          const c=document.createElement('canvas');
          c.width=w; c.height=h;
          const ctx=c.getContext('2d',{willReadFrequently:true});
          ctx.drawImage(img,0,0,w,h);
          const d=ctx.getImageData(0,0,w,h).data;
          let sum=0, n=w*h;
          for(let i=0;i<d.length;i+=4){
            const r=d[i]/255, g=d[i+1]/255, b=d[i+2]/255;
            const Y=0.2126*r + 0.7152*g + 0.0722*b;
            sum+=Y;
          }
          const avg=sum/n;
          let veil = 0.45 + (avg-0.5)*0.6;
          veil = Math.max(0.35, Math.min(0.75, veil));
          document.documentElement.style.setProperty('--veil', veil.toFixed(2));
        }catch(e){}
      };
      img.src = url;
    }

    function pingKeepAlive(){ fetch(PING_URL, { method:'GET', cache:'no-store', keepalive:true }).catch(()=>{}); }

    let polling = null, keepAlive = null;

    function actualizarListados() {
      const listados = document.getElementById('listados');
      if (listados && listados.hasAttribute('hidden')) return; // si está oculto, no gastamos
      fetch(URL_AJAX_PROF, {cache:'no-store'})
        .then(res => res.text())
        .then(html => { const t = document.getElementById('tabla_profesores'); if (t) t.innerHTML = html; })
        .catch(()=>{});
      fetch(URL_AJAX_CLIENT, {cache:'no-store'})
        .then(res => res.text())
        .then(html => { const t = document.getElementById('tabla_clientes'); if (t) t.innerHTML = html; })
        .catch(()=>{});
    }

    function tickClock(){
      const el = document.getElementById('clock');
      if (!el) return;
      const now = new Date();
      const pad = n => String(n).padStart(2,'0');
      el.textContent = `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    }

    function focusInput(){ const i = document.getElementById('codigo'); if (i) i.focus({preventScroll:true}); }

    function enviarCodigo(e){
      e.preventDefault();
      const inp = document.getElementById('codigo');
      const val = (inp.value || '').trim();
      if (!val) { focusInput(); return; }

      const fd = new FormData();
      fd.append('codigo', val);
      fd.append('csrf', CSRF);

      fetch(URL_AJAX_SELF, { method:'POST', body:fd, cache:'no-store' })
        .then(r => r.json())
        .then(j => {
          const adv = document.getElementById('adv');
          if (j && adv) {
            adv.textContent = j.mensaje || '';
            const clase = (j.tipo === 'alerta' || j.sonido) ? 'err' : 'ok';
            adv.className = 'advertencia ' + clase;
          }
          const okAudio = document.getElementById('snd-ok');
          const alAudio = document.getElementById('snd-alerta');
          if (j && j.tipo === 'ok' && okAudio){ okAudio.currentTime=0; okAudio.play().catch(()=>{}); }
          if (j && (j.tipo === 'alerta' || j.sonido) && alAudio){ alAudio.currentTime=0; alAudio.play().catch(()=>{}); }

          inp.value = '';
          focusInput();
          actualizarListados();
        })
        .catch(()=>{
          const adv = document.getElementById('adv');
          if (adv) { adv.textContent = '⚠️ Error enviando el código. Revisá la conexión.'; adv.className = 'advertencia err'; }
        });
    }

    function toggleListados(){
      const cont = document.getElementById('listados');
      const btn  = document.getElementById('btnToggleListados');
      const newState = cont.hasAttribute('hidden');
      if (newState){ cont.removeAttribute('hidden'); btn.textContent = '🙈 Ocultar listados'; actualizarListados(); }
      else { cont.setAttribute('hidden',''); btn.textContent = '👀 Mostrar listados'; }
      try{ localStorage.setItem('mostrar_listados', newState ? '1' : '0'); }catch(e){}
    }

    document.addEventListener('visibilitychange', () => {
      if (!document.hidden) { focusInput(); actualizarListados(); }
      pingKeepAlive();
    });

    window.addEventListener('beforeunload', () => {
      try { navigator.sendBeacon(PING_URL); } catch(e){}
    });

    window.addEventListener('load', () => {
      tickClock(); setInterval(tickClock, 1000);
      focusInput();

      // Estado recordado de los listados
      try{
        const saved = localStorage.getItem('mostrar_listados');
        if (saved === '1') {
          document.getElementById('listados').removeAttribute('hidden');
          document.getElementById('btnToggleListados').textContent = '🙈 Ocultar listados';
          actualizarListados();
        }
      }catch(e){}

      polling   = setInterval(actualizarListados, 10000);
      keepAlive = setInterval(pingKeepAlive, 25000);

      const form = document.getElementById('form-scan');
      if (form) form.addEventListener('submit', enviarCodigo);

      document.addEventListener('click', (e) => {
        const t = e.target;
        if (!(t instanceof HTMLInputElement) && !(t instanceof HTMLTextAreaElement)) focusInput();
      });

      if (BG_URL) ajustarVeloAuto(BG_URL);
      pingKeepAlive();
    });
  </script>
</head>
<body>
  <div class="wrap">
    <div class="page-card">
      <div class="encabezado">
        <img id="logoGym" src="<?= htmlspecialchars($logo_gimnasio) ?>" alt="logo">
        <div>
          <h1><?= strtoupper(htmlspecialchars($nombre_gimnasio)) ?></h1>
          <div class="clock">Hora: <span id="clock"></span></div>

          <!-- Estado Cloudinary + Toolbar -->
          <div class="toolbar">
            <?php if ($cloud_active): ?>
              <span class="cloud-status cloud-ok">☁️ Cloudinary: Activo</span>
            <?php else: ?>
              <span class="cloud-status cloud-bad">☁️ Cloudinary: Inactivo</span>
              <span class="hint">Tip: habilitá cURL y completá credenciales.</span>
            <?php endif; ?>

            <!-- Subir fondo (visible) -->
            <?php if ($gimnasio_id > 0): ?>
              <form class="bg-compact" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <input type="file" name="scan_bg_file" accept="image/*">
                <button class="btn" type="submit" name="act" value="bg_upload" <?= $cloud_active ? '' : 'disabled' ?>>🖼️ Subir fondo</button>

                <!-- Opciones avanzadas reubicadas: Ver / Quitar -->
                <details class="tools">
                  <summary>⋯ Opciones fondo</summary>
                  <div class="bg-admin-row">
                    <?php if (!empty($scan_bg)): ?>
                      <a class="btn gray" href="<?= htmlspecialchars($scan_bg) ?>" target="_blank">Ver</a>
                      <button class="btn danger" type="submit" name="act" value="bg_delete">Quitar</button>
                    <?php else: ?>
                      <span class="hint">No hay fondo cargado.</span>
                    <?php endif; ?>
                  </div>
                </details>
              </form>
            <?php endif; ?>

            <!-- Toggle de listados -->
            <button id="btnToggleListados" class="btn outline" type="button" onclick="toggleListados()">👀 Mostrar listados</button>
          </div>
        </div>
      </div>

      <form id="form-scan" class="scan" method="POST" action="">
        <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="off" placeholder="Ingresar DNI..." autofocus>
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
      </form>

      <?php if (!empty($advertencia)): ?>
        <div id="adv" class="advertencia <?= ($tipo_resultado === 'alerta') ? 'err' : 'ok' ?>"><?= htmlspecialchars($advertencia) ?></div>
      <?php else: ?>
        <div id="adv" class="advertencia" style="min-height: 24px;"></div>
      <?php endif; ?>

      <!-- Sonidos -->
      <audio id="snd-ok" preload="auto"><source src="ok.mp3" type="audio/mpeg"></audio>
      <audio id="snd-alerta" preload="auto"><source src="alerta.mp3" type="audio/mpeg"></audio>

      <!-- LISTADOS (OCULTOS POR DEFECTO) -->
      <div id="listados" hidden>
        <div class="row">
          <section>
            <h2>👨‍🏫 Profesores Hoy</h2>
            <div class="table-wrap">
              <table aria-label="Profesores de hoy">
                <thead><tr><th>Apellido</th><th>Ingreso</th><th>Salida</th></tr></thead>
                <tbody id="tabla_profesores"></tbody>
              </table>
            </div>
          </section>

          <section>
            <h2>🏋️ Clientes Hoy</h2>
            <div class="table-wrap">
              <table aria-label="Clientes de hoy">
                <thead><tr><th>Apellido</th><th>Hora</th><th>Clases</th><th>Vencimiento</th></tr></thead>
                <tbody id="tabla_clientes"></tbody>
              </table>
            </div>
          </section>
        </div>
      </div>
      <!-- fin listados -->
    </div>
  </div>
</body>
</html>
