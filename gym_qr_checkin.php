<?php
/* ============================================================
   gym_qr_checkin.php — Check-in por QR (MultiGimnasio)
   Solo DNI + recordar en dispositivo + registro online si no existe
   Auto-checkin: SOLO 1 VEZ por escaneo (se resetea al RE-ESCANEAR: exp cambia)
   URL: .../gym_qr_checkin.php?g=<g>&exp=<ISO-UTC>&sig=<hex>
   ============================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Config ===== */
define('QR_DEBUG', false);
$REINGRESO_BLOQUEO_MIN = 15;
$PERMITE_DNI = true;

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hex2bin_s($hex){ $b=@hex2bin(preg_replace('/[^0-9a-f]/i','',$hex)); return $b===false?null:$b; }
function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function haversine_m($lat1,$lon1,$lat2,$lon2){
  if ($lat1===null||$lon1===null||$lat2===null||$lon2===null) return null;
  $R=6371000; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  return $R*2*atan2(sqrt($a),sqrt(1-$a));
}
function redirect302(string $url){
  if (!headers_sent()){ header("Location: ".$url, true, 302); exit; }
  echo '<meta http-equiv="refresh" content="0;url='.h($url).'">'; exit;
}

/* ===== Inputs ===== */
$gimnasio_id = (int)($_GET['g'] ?? 0);
$exp = $_GET['exp'] ?? null;   // clave por escaneo
$sig = $_GET['sig'] ?? null;
if ($gimnasio_id<=0){ http_response_code(400); exit('❌ Falta ?g'); }

/* ===== Datos del gimnasio + validación firma ===== */
$rsG = $conexion->query("SELECT id, nombre, logo, qr_secret, lat, lon, geofence_radius_m, webhook_checkin_url
                         FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
if (!$rsG || !$rsG->num_rows){ http_response_code(404); exit('❌ Gimnasio no encontrado'); }
$gym = $rsG->fetch_assoc();

if (!empty($gym['qr_secret'])) {
  if (!$exp || !$sig){ http_response_code(403); exit('❌ QR inválido (falta exp/sig)'); }
  $params = "g={$gimnasio_id}&exp=".rawurlencode($exp);
  $calc = hash_hmac('sha256', $params, hex2bin_s($gym['qr_secret']));
  if (!hash_equals($calc, strtolower($sig))) { http_response_code(403); exit('❌ Firma no válida'); }
  $exp_ts = strtotime($exp);
  if ($exp_ts === false || time() > $exp_ts) { http_response_code(403); exit('❌ QR vencido'); }
}

/* ===== Marca (nombre/logo) ===== */
function resolver_logo_gimnasio(?string $logoRaw): ?string {
  $logoRaw = trim((string)$logoRaw);
  if ($logoRaw === '') return null;
  if (preg_match('#^(https?:)?//#i', $logoRaw) || str_starts_with($logoRaw, 'data:')) return $logoRaw;
  $candidatos = [
    __DIR__ . '/uploads/gimnasios/' . $logoRaw => '/uploads/gimnasios/' . rawurlencode($logoRaw),
    __DIR__ . '/img/' . $logoRaw              => '/img/' . rawurlencode($logoRaw),
    __DIR__ . '/' . ltrim($logoRaw, '/\\')    => '/' . ltrim($logoRaw, '/\\'),
  ];
  foreach ($candidatos as $fs => $url) if (is_file($fs)) return $url.'?v='.(int)@filemtime($fs);
  return $logoRaw ?: null;
}
$nombre_gimnasio = !empty($gym['nombre']) ? $gym['nombre'] : 'Gimnasio';
$logo_gimnasio   = !empty($gym['logo'])   ? resolver_logo_gimnasio($gym['logo']) : null;
if ($logo_gimnasio === null) {
  $fallback = __DIR__ . '/img/logo-default.png';
  if (is_file($fallback)) $logo_gimnasio = '/img/logo-default.png?v='.(int)@filemtime($fallback);
}

/* ===== BD: clientes ===== */
function get_cliente(mysqli $db, int $id){
  $rs=$db->query("SELECT id, nombre, apellido, dni, telefono, gimnasio_id FROM clientes WHERE id={$id} LIMIT 1");
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}
function find_cliente_por_dni(mysqli $db, int $gimnasio_id, ?string $dni){
  $dni = $dni ? preg_replace('/\D+/', '', $dni) : null;
  if (!$dni) return null;
  $sql = "SELECT id, nombre, apellido, dni, telefono, gimnasio_id
          FROM clientes
          WHERE dni=".qv($db,$dni)." AND gimnasio_id={$gimnasio_id}
          LIMIT 1";
  $rs = $db->query($sql);
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}

/* ===== BD: membresías ===== */
function mem_row_es_activa(array $m): bool {
  $ok_estado = true;
  if (array_key_exists('activa',$m) && $m['activa'] !== null && $m['activa']!=='') $ok_estado = ((string)$m['activa'] === '1');
  $ok_vto = true;
  if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00') $ok_vto = ($m['fecha_vencimiento'] >= date('Y-m-d'));
  return $ok_estado && $ok_vto;
}
function tiene_membresia_activa(mysqli $db, int $cliente_id, int $gimnasio_id): bool{
  $hoy = date('Y-m-d');
  $sql = "SELECT id, cliente_id, gimnasio_id, activa, fecha_inicio, fecha_vencimiento
          FROM membresias
          WHERE cliente_id={$cliente_id}
            AND gimnasio_id={$gimnasio_id}
            AND (fecha_vencimiento IS NULL OR fecha_vencimiento='0000-00-00' OR fecha_vencimiento >= '{$hoy}')
          ORDER BY COALESCE(fecha_vencimiento, fecha_inicio) DESC, id DESC
          LIMIT 1";
  $rs = $db->query($sql);
  if (!$rs || !$rs->num_rows) return false;
  return mem_row_es_activa($rs->fetch_assoc());
}
function mem_find_activa(mysqli $db, int $gimnasio_id, int $cliente_id): ?array {
  $hoy = date('Y-m-d');
  $sql = "SELECT id, cliente_id, gimnasio_id, plan_id, plan, fecha_inicio, fecha_vencimiento,
                 clases_disponibles, activa
          FROM membresias
          WHERE cliente_id={$cliente_id}
            AND gimnasio_id={$gimnasio_id}
            AND (fecha_vencimiento IS NULL OR fecha_vencimiento='0000-00-00' OR fecha_vencimiento >= '{$hoy}')
          ORDER BY COALESCE(fecha_vencimiento, fecha_inicio) DESC, id DESC
          LIMIT 1";
  $rs = $db->query($sql);
  if (!$rs || !$rs->num_rows) return null;
  $m = $rs->fetch_assoc();
  return mem_row_es_activa($m) ? $m : null;
}
function mem_aplicar_consumo(mysqli $db, int $gimnasio_id, int $cliente_id, ?int $acceso_id=null): array {
  if (!is_null($acceso_id)) {
    $chk = $db->query("SELECT id FROM membresia_consumos WHERE acceso_id={$acceso_id} LIMIT 1");
    if ($chk && $chk->num_rows) return ['ok'=>true, 'msg'=>'Ya aplicado (log)'];
  }
  $mem = mem_find_activa($db, $gimnasio_id, $cliente_id);
  if (!$mem) return ['ok'=>false, 'msg'=>'Sin membresía activa'];
  $mem_id = (int)$mem['id'];
  $db->query("UPDATE membresias
              SET clases_disponibles = CASE WHEN clases_disponibles>0 THEN clases_disponibles-1 ELSE 0 END
              WHERE id={$mem_id} AND gimnasio_id={$gimnasio_id} AND clases_disponibles>0");
  if ($db->affected_rows === 0) return ['ok'=>false, 'msg'=>'Sin clases disponibles'];
  $acc = is_null($acceso_id) ? "NULL" : (int)$acceso_id;
  $db->query("INSERT INTO membresia_consumos (gimnasio_id, cliente_id, membresia_id, acceso_id, origen)
              VALUES ({$gimnasio_id}, {$cliente_id}, {$mem_id}, {$acc}, 'checkin')");
  return ['ok'=>true, 'msg'=>'Clase descontada'];
}

/* ===== Accesos ===== */
function pudo_registrar(mysqli $db, int $cliente_id, int $gimnasio_id, int $min_bloqueo): bool{
  $sql="SELECT id FROM accesos_gimnasio
        WHERE cliente_id={$cliente_id} AND gimnasio_id={$gimnasio_id}
          AND fecha_ingreso >= (NOW() - INTERVAL {$min_bloqueo} MINUTE)
        ORDER BY fecha_ingreso DESC LIMIT 1";
  $rs=$db->query($sql);
  if (!$rs) { error_log("pudo_registrar SQL error: ".$db->error); return true; }
  return !($rs->num_rows);
}
function registrar_acceso(mysqli $db, int $cliente_id, int $gimnasio_id, string $metodo='QR-GYM/DNI', ?float $lat=null, ?float $lon=null): int{
  static $hasLatLon = null;
  if ($hasLatLon === null) {
    $hasLatLon = ['lat'=>false,'lon'=>false];
    if ($rs = $db->query("SHOW COLUMNS FROM accesos_gimnasio")) {
      while ($c = $rs->fetch_assoc()) {
        $n = strtolower($c['Field'] ?? '');
        if ($n==='lat') $hasLatLon['lat']=true;
        if ($n==='lon') $hasLatLon['lon']=true;
      }
    }
  }
  $fields = "cliente_id, gimnasio_id, fecha_ingreso, metodo";
  $values = "{$cliente_id}, {$gimnasio_id}, NOW(), ".qv($db,$metodo);
  if ($hasLatLon['lat']) { $fields .= ", lat"; $values .= ", ".(is_null($lat) ? "NULL" : (float)$lat); }
  if ($hasLatLon['lon']) { $fields .= ", lon"; $values .= ", ".(is_null($lon) ? "NULL" : (float)$lon); }
  $sql="INSERT INTO accesos_gimnasio ({$fields}) VALUES ({$values})";
  if (!$db->query($sql)) {
    $msg = "INSERT acceso FAILED: ".$db->error." | SQL=".$sql;
    error_log($msg);
    if (QR_DEBUG) { echo "<pre style='color:#ffb3b3;background:#2b2b2b;padding:10px;border-radius:8px'>".$msg."</pre>"; }
    return 0;
  }
  return (int)$db->insert_id;
}

/* ===== Webhook ===== */
function fire_webhook(?string $url, array $payload){
  if (!$url) return;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 5
  ]);
  @curl_exec($ch);
  @curl_close($ch);
}

/* ===== Autologin por cookie si no hay sesión ===== */
if (empty($_SESSION['cliente_id']) && isset($_COOKIE['cli_autologin'])) {
  [$cid,$tok] = explode('.', $_COOKIE['cli_autologin'] ?? '.', 2) + [null,null];
  $cid = (int)$cid;
  if ($cid>0 && preg_match('/^[a-f0-9]{64}$/',$tok)) {
    $q = "SELECT cliente_id FROM cliente_tokens
          WHERE cliente_id={$cid} AND gimnasio_id={$gimnasio_id} AND token='{$tok}' LIMIT 1";
    if ($rs = $conexion->query($q)) {
      if ($rs->num_rows) {
        $_SESSION['cliente_id'] = $cid;
        $_SESSION['gimnasio_id'] = $gimnasio_id;
        $ua = $conexion->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
        $conexion->query("UPDATE cliente_tokens SET ultimo_uso=NOW(), user_agent='{$ua}'
                          WHERE cliente_id={$cid} AND gimnasio_id={$gimnasio_id} AND token='{$tok}' LIMIT 1");
      }
    }
  }
}

/* ===== Auto-checkin si hay sesión del MISMO gimnasio ===== */
$cliente_id_sesion = (int)($_SESSION['cliente_id'] ?? 0);
if ($cliente_id_sesion>0){
  $cli=get_cliente($conexion,$cliente_id_sesion);
  if ($cli && (int)$cli['gimnasio_id']===$gimnasio_id){
    $latReq = isset($_POST['lat']) ? (float)$_POST['lat'] : (isset($_GET['lat'])?(float)$_GET['lat']:null);
    $lonReq = isset($_POST['lon']) ? (float)$_POST['lon'] : (isset($_GET['lon'])?(float)$_GET['lon']:null);
    if (!is_null($gym['geofence_radius_m']) && $gym['lat']!==null && $gym['lon']!==null) {
      $dist = haversine_m((float)$gym['lat'], (float)$gym['lon'], $latReq, $lonReq);
      if ($dist===null) { render_page_form($nombre_gimnasio,$logo_gimnasio,"📍 Necesitamos tu ubicación para validar el ingreso.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit; }
      if ($dist > (int)$gym['geofence_radius_m']) {
        render_page_form($nombre_gimnasio,$logo_gimnasio,"🚫 Fuera del área del gimnasio (".round($dist)." m). Acercate para marcar ingreso.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit;
      }
    }

    if (!tiene_membresia_activa($conexion,$cliente_id_sesion,$gimnasio_id)){
      render_page_form($nombre_gimnasio,$logo_gimnasio,"⚠️ Membresía no activa o vencida. Acercate a recepción.", false, ['g'=>$gimnasio_id, 'exp'=>$exp]); exit;
    }
    if (!pudo_registrar($conexion,$cliente_id_sesion,$gimnasio_id,$REINGRESO_BLOQUEO_MIN)){
      render_page_form($nombre_gimnasio,$logo_gimnasio,"⏱️ Ya registraste un ingreso hace poco. Esperá {$REINGRESO_BLOQUEO_MIN} min.", false, ['g'=>$gimnasio_id, 'exp'=>$exp]); exit;
    }
    $acceso_id = registrar_acceso($conexion,$cliente_id_sesion,$gimnasio_id,'QR-GYM/DNI',$latReq,$lonReq);
    $ok = $acceso_id>0;
    if ($ok){
      mem_aplicar_consumo($conexion, $gimnasio_id, $cliente_id_sesion, $acceso_id);
      if (!empty($gym['webhook_checkin_url'])) {
        fire_webhook($gym['webhook_checkin_url'], [
          'tipo' => 'checkin','gimnasio_id' => $gimnasio_id,'cliente_id' => $cliente_id_sesion,
          'nombre' => $cli['nombre'].' '.$cli['apellido'],'hora' => date('c'),
          'metodo' => 'QR-GYM/DNI','lat' => $latReq, 'lon' => $lonReq
        ]);
      }
    }
    $msg = $ok ? ("✅ Ingreso marcado — ".date('H:i')) : "❌ No se pudo marcar el ingreso.";
    render_page_done($nombre_gimnasio,$logo_gimnasio,$gimnasio_id, $msg, (bool)$ok); exit;
  }
}

/* ===== POST: SOLO DNI ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dni'])){
  $dniRaw = (string)($_POST['dni'] ?? '');
  $dni = $PERMITE_DNI ? trim(preg_replace('/\D+/','',$dniRaw)) : '';
  $latReq = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
  $lonReq = isset($_POST['lon']) ? (float)$_POST['lon'] : null;

  if (!$dni){ render_page_form($nombre_gimnasio,$logo_gimnasio,"❌ Ingresá tu DNI.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit; }

  $cli = find_cliente_por_dni($conexion,$gimnasio_id,$dni);

  // Si NO está registrado → registro_online.php con g, gimnasio_id, dni y return (NO marca ingreso)
  if (!$cli){
    $return = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] ? 'https://' : 'http://')
            . ($_SERVER['HTTP_HOST'] ?? '')
            . ($_SERVER['REQUEST_URI'] ?? ('/gym_qr_checkin.php?g='.$gimnasio_id));
    $url = 'registro_online.php'
         . '?g='.$gimnasio_id
         . '&gimnasio_id='.$gimnasio_id
         . '&dni='.rawurlencode($dni)
         . '&return='.rawurlencode($return);
    redirect302($url);
  }

  // Geocerca
  if (!is_null($gym['geofence_radius_m']) && $gym['lat']!==null && $gym['lon']!==null) {
    $dist = haversine_m((float)$gym['lat'], (float)$gym['lon'], $latReq, $lonReq);
    if ($dist===null) { render_page_form($nombre_gimnasio,$logo_gimnasio,"📍 Necesitamos tu ubicación para validar el ingreso.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit; }
    if ($dist > (int)$gym['geofence_radius_m']) {
      render_page_form($nombre_gimnasio,$logo_gimnasio,"🚫 Fuera del área del gimnasio (".round($dist)." m). Acercate para marcar ingreso.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit;
    }
  }

  if (!tiene_membresia_activa($conexion,(int)$cli['id'],$gimnasio_id)){
    render_page_form($nombre_gimnasio,$logo_gimnasio,"⚠️ Membresía no activa o vencida. Consultá en recepción.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit;
  }
  if (!pudo_registrar($conexion,(int)$cli['id'],$gimnasio_id,$REINGRESO_BLOQUEO_MIN)){
    render_page_form($nombre_gimnasio,$logo_gimnasio,"⏱️ Ya registraste un ingreso recientemente. Intentá en {$REINGRESO_BLOQUEO_MIN} minutos.", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]); exit;
  }

  $acceso_id = registrar_acceso($conexion,(int)$cli['id'],$gimnasio_id,'QR-GYM/DNI',$latReq,$lonReq);
  if ($acceso_id>0){
    $_SESSION['cliente_id']=(int)$cli['id'];
    $_SESSION['gimnasio_id']=(int)$cli['gimnasio_id'];

    mem_aplicar_consumo($conexion, $gimnasio_id, (int)$cli['id'], $acceso_id);

    if (!empty($gym['webhook_checkin_url'])) {
      fire_webhook($gym['webhook_checkin_url'], [
        'tipo' => 'checkin','gimnasio_id' => $gimnasio_id,'cliente_id' => (int)$cli['id'],
        'nombre' => $cli['nombre'].' '.$cli['apellido'],'hora' => date('c'),
        'metodo' => 'QR-GYM/DNI','lat' => $latReq, 'lon' => $lonReq
      ]);
    }

    render_page_done($nombre_gimnasio,$logo_gimnasio,$gimnasio_id, "✅ Ingreso marcado — ".h($cli['nombre'].' '.$cli['apellido'])." ".date('H:i'), true);
  } else {
    render_page_form($nombre_gimnasio,$logo_gimnasio,"❌ Error al registrar el ingreso. (Ver log)", false, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]);
  }
  exit;
}

/* ===== Primer render ===== */
render_page_form($nombre_gimnasio,$logo_gimnasio, null, null, ['need_geo'=>true, 'g'=>$gimnasio_id, 'exp'=>$exp]);
exit;

/* ================== VISTAS ================== */
function base_css(){ return "
  :root{ --bg:#0c0c0d; --card:#141416; --ink:#eaeaea; --muted:#9aa0a6; --stroke:#222; --accent:#6ea8fe; }
  *{box-sizing:border-box} html,body{height:100%}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:var(--bg);color:var(--ink)}
  .wrap{max-width:520px;margin:0 auto;padding:18px}
  @media (min-width:480px){ .wrap{padding:24px} }
  .card{background:var(--card);border:1px solid var(--stroke);border-radius:16px;padding:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
  .brand{display:flex;gap:12px;align-items:center;justify-content:center;margin-bottom:12px}
  .brand img{width:56px;height:56px;object-fit:cover;border-radius:12px;background:#fff;border:1px solid #333}
  .brand .name{font-size:22px;font-weight:800;letter-spacing:.2px;color:#fff}
  .title{font-size:16px;color:var(--muted);text-align:center;margin-bottom:8px}
  label{display:block;margin:8px 0 6px;color:#cbd5e1;font-size:14px}
  input{width:100%;padding:14px 16px;border-radius:12px;border:1px solid #303038;background:#101012;color:#fff;font-size:18px;outline:none}
  input:focus{border-color:var(--accent)}
  .flash{padding:12px 14px;border-radius:12px;margin-bottom:12px;font-size:14px}
  .ok{background:#064e3b;color:#b7f7d0;border:1px solid #065f46}
  .warn{background:#4d2f05;color:#ffecb3;border:1px solid #7c4a03}
  .err{background:#5b1111;color:#ffd3d3;border:1px solid #7f1d1d}
  .btn{width:100%;padding:14px 16px;border-radius:12px;border:0;background:var(--accent);color:#000;font-weight:800;font-size:18px;margin-top:14px;cursor:pointer}
  .btn-ghost{display:inline-block;padding:10px 14px;border-radius:12px;border:1px solid #2c2c2c;background:#171717;color:#e8e8e8;text-decoration:none;font-weight:700}
  .help{font-size:12px;color:var(--muted);margin-top:10px;text-align:center}
  .grid{display:grid;gap:10px}
  .row{display:flex;gap:10px;align-items:center;justify-content:space-between}
  .small{font-size:12px;color:#9aa0a6}
  a.inline{color:#bcd7ff;text-decoration:none}
"; }

function render_page_form(string $nombreGym, ?string $logoGym, ?string $flash, ?bool $ok, array $opts=[]){
  $need_geo = !empty($opts['need_geo']);
  $g        = (int)($opts['g'] ?? 0);
  $exp      = isset($opts['exp']) ? (string)$opts['exp'] : '';
  $okClass  = $ok === null ? '' : ($ok ? 'ok' : 'warn');
  $okIcon   = $ok === null ? '' : ($ok ? '✅' : '⚠️'); ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= h($nombreGym) ?> · Ingreso</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="color-scheme" content="dark">
<style><?= base_css() ?></style>
<script>
let lat=null, lon=null, readyGeo=false, autoTried=false;
const G   = <?= (int)$g ?>;
const EXP = <?= json_encode((string)$exp) ?>; // clave de escaneo
const KEY_DNI  = 'qr_dni_g' + G;
const KEY_SCAN = 'qr_scan_' + G + '_' + (EXP || 'noexp'); // SOLO una vez por exp

function askGeo(){
  if (!navigator.geolocation) { readyGeo = true; maybeAutoSubmit(); return; }
  navigator.geolocation.getCurrentPosition(
    (pos)=>{ lat=pos.coords.latitude; lon=pos.coords.longitude; readyGeo=true;
      document.querySelectorAll('input[name=lat]').forEach(e=>e.value=lat);
      document.querySelectorAll('input[name=lon]').forEach(e=>e.value=lon);
      maybeAutoSubmit();
    },
    (err)=>{ console.log('geo error', err); readyGeo=true; maybeAutoSubmit(); },
    { enableHighAccuracy:true, timeout:6000, maximumAge:0 }
  );
}

function maybeAutoSubmit(){
  if (autoTried) return;
  if (sessionStorage.getItem(KEY_SCAN)) return; // ⛔ ya se auto-envió para este escaneo
  const dniSaved = localStorage.getItem(KEY_DNI);
  if (!dniSaved) return;
  const dniInput = document.querySelector('input[name=dni]');
  const form     = document.getElementById('checkinForm');
  if (!dniInput || !form) return;
  dniInput.value = dniSaved;
  autoTried = true;
  sessionStorage.setItem(KEY_SCAN, '1'); // marcar que ya hicimos auto-checkin para este QR
  disableUI(true, 'Marcando ingreso...');
  form.submit();
}

function disableUI(disabled, msg){
  const btn = document.getElementById('btnIngresar');
  if (btn){ btn.disabled = !!disabled; btn.textContent = disabled ? (msg||'Procesando...') : 'Marcar mi ingreso'; }
}

function goRegistro(){
  const dniVal = (document.querySelector('input[name=dni]')?.value || '').replace(/\D+/g,'');
  let url = 'registro_online.php?g='+G+'&gimnasio_id='+G; // mandamos ambos
  if (dniVal) url += '&dni='+encodeURIComponent(dniVal);
  url += '&return='+encodeURIComponent(location.href);
  location.href = url;
}

document.addEventListener('DOMContentLoaded', function(){
  askGeo();

  const dniSaved = localStorage.getItem(KEY_DNI);
  const otherLink = document.getElementById('useOther');
  if (dniSaved && otherLink) {
    otherLink.style.display = 'inline';
    otherLink.addEventListener('click', function(e){
      e.preventDefault();
      localStorage.removeItem(KEY_DNI);
      const dniInput = document.querySelector('input[name=dni]');
      if (dniInput){ dniInput.value=''; dniInput.focus(); }
      this.style.display='none';
    });
  }

  const form = document.getElementById('checkinForm');
  form.addEventListener('submit', function(){
    const dniVal = (document.querySelector('input[name=dni]')?.value || '').replace(/\D+/g,'');
    const remember = document.getElementById('rememberDNI');
    if (remember && remember.checked && dniVal.length>=6) {
      localStorage.setItem(KEY_DNI, dniVal);
    }
    if (!sessionStorage.getItem(KEY_SCAN)) sessionStorage.setItem(KEY_SCAN, '1'); // evitar bucles si recarga
    disableUI(true);
  });
});
</script>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <?php if ($logoGym): ?><img src="<?= h($logoGym) ?>" alt="Logo <?= h($nombreGym) ?>" loading="lazy" decoding="async"><?php endif; ?>
        <div class="name"><?= h($nombreGym) ?></div>
      </div>
      <div class="title">Marcá tu ingreso con tu DNI</div>

      <?php if ($flash): ?>
        <div class="flash <?= $okClass ?>"><?= $okIcon." ".h($flash) ?></div>
      <?php endif; ?>

      <form id="checkinForm" method="post" class="grid" novalidate>
        <input type="hidden" name="lat"><input type="hidden" name="lon">
        <label>DNI</label>
        <input inputmode="numeric" pattern="[0-9]*" name="dni" placeholder="Ej: 35123456" autocomplete="one-time-code" autofocus>
        <div class="row">
          <label class="small"><input type="checkbox" id="rememberDNI" style="vertical-align:-2px;margin-right:6px"> Recordar mi DNI en este dispositivo</label>
          <a href="#" id="useOther" class="inline small" style="display:none">Usar otro DNI</a>
        </div>
        <button class="btn" id="btnIngresar" type="submit">Marcar mi ingreso</button>
        <?php if ($need_geo): ?>
          <div class="help">📍 Permití la ubicación si se solicita para validar que estás en el gimnasio.</div>
        <?php endif; ?>
      </form>

      <div class="help" style="margin-top:14px">
        ¿Primera vez? <a class="btn-ghost" href="javascript:goRegistro()">Registrarme</a>
      </div>
    </div>
  </div>
</body>
</html>
<?php }

function render_page_done(string $nombreGym, ?string $logoGym, int $g, ?string $flash, ?bool $ok){
  $cls = $ok ? 'ok' : 'warn';
  $ico = $ok ? '✅' : '⚠️'; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= h($nombreGym) ?> · Ingreso</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,user-scalable=no">
<meta name="color-scheme" content="dark">
<style><?= base_css() ?></style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="brand">
        <?php if ($logoGym): ?><img src="<?= h($logoGym) ?>" alt="Logo <?= h($nombreGym) ?>" loading="lazy" decoding="async"><?php endif; ?>
        <div class="name"><?= h($nombreGym) ?></div>
      </div>
      <div class="flash <?= $cls ?>"><?= $ico." ".h($flash ?? '') ?></div>
      <div class="help">Podés cerrar esta ventana.</div>
    </div>
  </div>
</body>
</html>
<?php }
