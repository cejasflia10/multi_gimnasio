<?php
/* ============================================================
   gym_qr_checkin.php — Check-in por QR (MultiGimnasio)
   URL: .../gym_qr_checkin.php?g=<g>&exp=<ISO-UTC>&sig=<hex>
   - Firma HMAC por gimnasio (si hay qr_secret).
   - Autologin por sesión o token persistente (cookie).
   - Geocerca opcional por gimnasio (lat/lon + radio en metros).
   - Fallback por DNI o PIN; OTP (WhatsApp/SMS) opcional.
   - Anti-doble: bloquea reingreso por ventana de minutos.
   - Webhook por gimnasio (POST JSON) tras check-in OK.
   - Descuenta 1 clase en membresias.clases_disponibles (idempotente).
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Config ===== */
$REINGRESO_BLOQUEO_MIN = 15;
$PERMITE_DNI = true;
$PERMITE_PIN = true;
$OTP_MINUTOS = 5;                 // duración del OTP
$OTP_CANAL_DEF = 'whatsapp';      // 'whatsapp'|'sms'|'email'

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function hex2bin_s($hex){ $b=@hex2bin(preg_replace('/[^0-9a-f]/i','',$hex)); return $b===false?null:$b; }
function qv($db,$s){ return "'".$db->real_escape_string((string)$s)."'"; }
function haversine_m($lat1,$lon1,$lat2,$lon2){
  if ($lat1===null||$lon1===null||$lat2===null||$lon2===null) return null;
  $R=6371000; // m
  $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  $c=2*atan2(sqrt($a),sqrt(1-$a));
  return $R*$c;
}

/* ===== Inputs ===== */
$gimnasio_id = (int)($_GET['g'] ?? 0);
$exp = $_GET['exp'] ?? null;
$sig = $_GET['sig'] ?? null;
if ($gimnasio_id<=0){ http_response_code(400); exit('❌ Falta ?g'); }

/* ===== Datos del gimnasio + validación firma ===== */
$rsG = $conexion->query("SELECT id, nombre, qr_secret, lat, lon, geofence_radius_m, webhook_checkin_url FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
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

/* ===== BD helpers ===== */
function get_cliente(mysqli $db, int $id){
  $rs=$db->query("SELECT id, nombre, apellido, dni, telefono, codigo_pin, gimnasio_id FROM clientes WHERE id={$id} LIMIT 1");
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}
function find_cliente_for_login(mysqli $db, int $gimnasio_id, ?string $dni, ?string $pin){
  $dni = $dni ? preg_replace('/\D+/', '', $dni) : null;
  $pin = $pin ? preg_replace('/\D+/', '', $pin) : null;
  $conds = [];
  if ($dni) $conds[] = "dni=".qv($db,$dni);
  if ($pin) $conds[] = "codigo_pin=".qv($db,$pin);
  if (!$conds) return null;
  $sql = "SELECT id, nombre, apellido, dni, telefono, codigo_pin, gimnasio_id
          FROM clientes
          WHERE (".implode(' OR ',$conds).") AND gimnasio_id={$gimnasio_id}
          LIMIT 1";
  $rs = $db->query($sql);
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}
function tiene_membresia_activa(mysqli $db, int $cliente_id, int $gimnasio_id): bool{
  $sql = "SELECT id, estado, fecha_vencimiento, fecha_inicio
          FROM membresias
          WHERE cliente_id={$cliente_id} AND gimnasio_id={$gimnasio_id}
          ORDER BY COALESCE(fecha_vencimiento, fecha_inicio) DESC
          LIMIT 1";
  $rs = $db->query($sql);
  if (!$rs || !$rs->num_rows) return false;
  $m = $rs->fetch_assoc();
  $estado_ok = true;
  if (isset($m['estado']) && $m['estado']!=='') {
    $estado_ok = in_array(strtolower($m['estado']), ['activa','activo','al_dia','vigente','1','si','sí'], true);
  }
  $hoy = date('Y-m-d');
  $vto_ok = true;
  if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00') {
    $vto_ok = ($m['fecha_vencimiento'] >= $hoy);
  }
  return $estado_ok && $vto_ok;
}
function pudo_registrar(mysqli $db, int $cliente_id, int $gimnasio_id, int $min_bloqueo): bool{
  $sql="SELECT id FROM accesos_gimnasio
        WHERE cliente_id={$cliente_id} AND gimnasio_id={$gimnasio_id}
          AND fecha_ingreso >= (NOW() - INTERVAL {$min_bloqueo} MINUTE)
        ORDER BY fecha_ingreso DESC LIMIT 1";
  $rs=$db->query($sql);
  return !($rs && $rs->num_rows);
}
/** Inserta acceso y retorna ID (0 si falla) */
function registrar_acceso(mysqli $db, int $cliente_id, int $gimnasio_id, string $metodo='QR-GYM', ?float $lat=null, ?float $lon=null): int{
  $latv = is_null($lat) ? "NULL" : (float)$lat;
  $lonv = is_null($lon) ? "NULL" : (float)$lon;
  $sql="INSERT INTO accesos_gimnasio (cliente_id, gimnasio_id, fecha_ingreso, metodo, lat, lon)
        VALUES ({$cliente_id}, {$gimnasio_id}, NOW(), ".qv($db,$metodo).", {$latv}, {$lonv})";
  if ($db->query($sql)) return (int)$db->insert_id;
  return 0;
}
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

/* ===== Helpers de membresía: descuento en membresias.clases_disponibles ===== */
function mem_pick_gymcol(mysqli $db): string {
  $rs = $db->query("SHOW COLUMNS FROM membresias LIKE 'gimnasio_id'");
  return ($rs && $rs->num_rows) ? 'gimnasio_id' : 'id_gimnasio';
}
function mem_is_activa_row(array $m): bool {
  $ok_estado = true;
  if (array_key_exists('activa',$m) && $m['activa']!==null) $ok_estado = ((string)$m['activa']==='1');
  elseif (array_key_exists('estado',$m) && $m['estado']!=='') $ok_estado = in_array(strtolower((string)$m['estado']), ['activa','activo','vigente','al_dia','si','sí','1'], true);
  $ok_vto = true;
  if (!empty($m['fecha_vencimiento']) && $m['fecha_vencimiento']!=='0000-00-00') $ok_vto = ($m['fecha_vencimiento'] >= date('Y-m-d'));
  return $ok_estado && $ok_vto;
}
function mem_find_activa(mysqli $db, int $gimnasio_id, int $cliente_id): ?array {
  $gymcol = mem_pick_gymcol($db);
  $hoy = date('Y-m-d');
  $sql = "SELECT id, cliente_id, {$gymcol} AS gimnasio_id, plan_id, plan, fecha_inicio, fecha_vencimiento,
                 clases_disponibles, activa, estado
          FROM membresias
          WHERE cliente_id={$cliente_id}
            AND {$gymcol}={$gimnasio_id}
            AND (fecha_vencimiento IS NULL OR fecha_vencimiento='0000-00-00' OR fecha_vencimiento >= '{$hoy}')
          ORDER BY COALESCE(fecha_vencimiento, fecha_inicio) DESC, id DESC
          LIMIT 1";
  $rs = $db->query($sql);
  if (!$rs || !$rs->num_rows) return null;
  $m = $rs->fetch_assoc();
  return mem_is_activa_row($m) ? $m : null;
}
/** Idempotente por acceso_id; descuenta 1 si hay stock > 0 y loguea */
function mem_aplicar_consumo(mysqli $db, int $gimnasio_id, int $cliente_id, ?int $acceso_id=null): array {
  if (!is_null($acceso_id)) {
    $chk = $db->query("SELECT id FROM membresia_consumos WHERE acceso_id={$acceso_id} LIMIT 1");
    if ($chk && $chk->num_rows) return ['ok'=>true, 'msg'=>'Ya aplicado previamente (log)'];
  }
  $mem = mem_find_activa($db, $gimnasio_id, $cliente_id);
  if (!$mem) return ['ok'=>false, 'msg'=>'Sin membresía activa en membresias'];

  $mem_id = (int)$mem['id'];
  $db->query("UPDATE membresias
              SET clases_disponibles = CASE WHEN clases_disponibles>0 THEN clases_disponibles-1 ELSE 0 END
              WHERE id={$mem_id} AND clases_disponibles>0");
  if ($db->affected_rows === 0) return ['ok'=>false, 'msg'=>'Sin clases disponibles para descontar'];

  $acc = is_null($acceso_id) ? "NULL" : (int)$acceso_id;
  $db->query("INSERT INTO membresia_consumos (gimnasio_id, cliente_id, membresia_id, acceso_id, origen)
              VALUES ({$gimnasio_id}, {$cliente_id}, {$mem_id}, {$acc}, 'checkin')");
  return ['ok'=>true, 'msg'=>'Clase descontada'];
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

/* ===== Acciones OTP (en el mismo endpoint) ===== */
function generar_codigo_otp(int $len=6){ return str_pad((string)random_int(0, 10**$len - 1), $len, '0', STR_PAD_LEFT); }
function enviar_por_whatsapp_sms(string $destino, string $texto): bool {
  // TODO: Integrar proveedor (Twilio, Meta WhatsApp Cloud, etc.)
  // Por ahora stub (simula envío ok):
  return true;
}
if (isset($_POST['otp_send'])) {
  $tel = trim($_POST['telefono'] ?? '');
  if ($tel===''){ render_page_form("Ingresá tu teléfono para enviar código OTP.", false); exit; }

  $code = generar_codigo_otp(6);
  $canal = $OTP_CANAL_DEF;
  $expira = date('Y-m-d H:i:s', time() + $OTP_MINUTOS*60);
  $conexion->query("INSERT INTO otp_codes (gimnasio_id, telefono, destino, code, expira)
                    VALUES ({$gimnasio_id}, ".qv($conexion,$tel).", ".qv($conexion,$canal).", ".qv($conexion,$code).", ".qv($conexion,$expira).")");
  $ok_env = enviar_por_whatsapp_sms($tel, "Tu código de acceso es: {$code} (vence en {$OTP_MINUTOS} min)");
  render_page_form($ok_env ? "📩 Enviamos un código a {$tel}. Ingrésalo abajo." : "⚠️ No pudimos enviar el código. Intentá más tarde.", $ok_env, ['pref_tel'=>$tel, 'show_otp'=>true]);
  exit;
}
if (isset($_POST['otp_verify'])) {
  $tel = trim($_POST['telefono'] ?? '');
  $code = trim($_POST['otp_code'] ?? '');
  if ($tel==='' || $code===''){ render_page_form("Ingresá teléfono y código OTP.", false, ['pref_tel'=>$tel, 'show_otp'=>true]); exit; }

  $q = "SELECT id FROM otp_codes
        WHERE gimnasio_id={$gimnasio_id}
          AND telefono=".qv($conexion,$tel)."
          AND code=".qv($conexion,$code)."
          AND usado=0 AND expira >= NOW()
        ORDER BY id DESC LIMIT 1";
  $rs = $conexion->query($q);
  if (!$rs || !$rs->num_rows){
    render_page_form("❌ Código inválido o vencido.", false, ['pref_tel'=>$tel, 'show_otp'=>true]); exit;
  }
  $otp = $rs->fetch_assoc();
  $conexion->query("UPDATE otp_codes SET usado=1 WHERE id={$otp['id']} LIMIT 1");

  // Autenticar por teléfono → buscar cliente por teléfono en este gimnasio
  $rsC = $conexion->query("SELECT id, nombre, apellido, gimnasio_id FROM clientes WHERE gimnasio_id={$gimnasio_id} AND telefono=".qv($conexion,$tel)." LIMIT 1");
  if (!$rsC || !$rsC->num_rows){
    render_page_form("⚠️ Teléfono validado, pero no encontramos un cliente con ese número en este gimnasio.", false); exit;
  }
  $cli = $rsC->fetch_assoc();
  $_SESSION['cliente_id']=(int)$cli['id'];
  $_SESSION['gimnasio_id']=(int)$cli['gimnasio_id'];
  // sigue el flujo normal más abajo (auto-checkin)
}

/* ===== Auto-checkin si hay sesión del MISMO gimnasio ===== */
$cliente_id_sesion = (int)($_SESSION['cliente_id'] ?? 0);
if ($cliente_id_sesion>0){
  $cli=get_cliente($conexion,$cliente_id_sesion);
  if ($cli && (int)$cli['gimnasio_id']===$gimnasio_id){
    // Geocerca (si configurada)
    $latReq = isset($_POST['lat']) ? (float)$_POST['lat'] : (isset($_GET['lat'])?(float)$_GET['lat']:null);
    $lonReq = isset($_POST['lon']) ? (float)$_POST['lon'] : (isset($_GET['lon'])?(float)$_GET['lon']:null);
    if (!is_null($gym['geofence_radius_m']) && $gym['lat']!==null && $gym['lon']!==null) {
      $dist = haversine_m((float)$gym['lat'], (float)$gym['lon'], $latReq, $lonReq);
      if ($dist===null) { render_page_form("📍 Necesitamos tu ubicación para validar el ingreso.", false, ['need_geo'=>true]); exit; }
      if ($dist > (int)$gym['geofence_radius_m']) {
        render_page_form("🚫 Fuera del área del gimnasio (".round($dist)." m). Acercate para marcar ingreso.", false, ['need_geo'=>true]); exit;
      }
    }

    if (!tiene_membresia_activa($conexion,$cliente_id_sesion,$gimnasio_id)){
      render_page_form("⚠️ Membresía no activa o vencida. Acercate a recepción.", false); exit;
    }
    if (!pudo_registrar($conexion,$cliente_id_sesion,$gimnasio_id,$REINGRESO_BLOQUEO_MIN)){
      render_page_form("⏱️ Ya registraste un ingreso hace poco. Esperá {$REINGRESO_BLOQUEO_MIN} min.", false); exit;
    }
    $acceso_id = registrar_acceso($conexion,$cliente_id_sesion,$gimnasio_id,'QR-GYM/AUTO',$latReq,$lonReq);
    $ok = $acceso_id>0;
    if ($ok){
      // Descuento de clase (idempotente por acceso)
      mem_aplicar_consumo($conexion, $gimnasio_id, $cliente_id_sesion, $acceso_id);

      if (!empty($gym['webhook_checkin_url'])) {
        fire_webhook($gym['webhook_checkin_url'], [
          'tipo' => 'checkin',
          'gimnasio_id' => $gimnasio_id,
          'cliente_id' => $cliente_id_sesion,
          'nombre' => $cli['nombre'].' '.$cli['apellido'],
          'hora' => date('c'),
          'metodo' => 'QR-GYM/AUTO',
          'lat' => $latReq, 'lon' => $lonReq
        ]);
      }
    }
    $msg = $ok ? ("✅ Ingreso marcado — ".date('H:i')) : "❌ No se pudo marcar el ingreso.";
    render_page_done($gimnasio_id,$gym['nombre']??'', $msg, (bool)$ok); exit;
  }
}

/* ===== POST: DNI / PIN ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && (isset($_POST['dni']) || isset($_POST['pin'])) ){
  $dni = $PERMITE_DNI ? (trim($_POST['dni'] ?? '')) : '';
  $pin = $PERMITE_PIN ? (trim($_POST['pin'] ?? '')) : '';
  $latReq = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
  $lonReq = isset($_POST['lon']) ? (float)$_POST['lon'] : null;

  if (!$dni && !$pin){ render_page_form("❌ Ingresá DNI o PIN.", false, ['need_geo'=>true]); exit; }

  $cli = find_cliente_for_login($conexion,$gimnasio_id,$dni?:null,$pin?:null);
  if (!$cli){ render_page_form("❌ No encontramos un cliente con esos datos en este gimnasio.", false, ['need_geo'=>true]); exit; }

  // Geocerca (si configurada)
  if (!is_null($gym['geofence_radius_m']) && $gym['lat']!==null && $gym['lon']!==null) {
    $dist = haversine_m((float)$gym['lat'], (float)$gym['lon'], $latReq, $lonReq);
    if ($dist===null) { render_page_form("📍 Necesitamos tu ubicación para validar el ingreso.", false, ['need_geo'=>true]); exit; }
    if ($dist > (int)$gym['geofence_radius_m']) {
      render_page_form("🚫 Fuera del área del gimnasio (".round($dist)." m). Acercate para marcar ingreso.", false, ['need_geo'=>true]); exit;
    }
  }

  if (!tiene_membresia_activa($conexion,(int)$cli['id'],$gimnasio_id)){
    render_page_form("⚠️ Membresía no activa o vencida. Consultá en recepción.", false, ['need_geo'=>true]); exit;
  }
  if (!pudo_registrar($conexion,(int)$cli['id'],$gimnasio_id,$REINGRESO_BLOQUEO_MIN)){
    render_page_form("⏱️ Ya registraste un ingreso recientemente. Intentá en {$REINGRESO_BLOQUEO_MIN} minutos.", false, ['need_geo'=>true]); exit;
  }

  $acceso_id = registrar_acceso($conexion,(int)$cli['id'],$gimnasio_id,$dni?'QR-GYM/DNI':'QR-GYM/PIN',$latReq,$lonReq);
  if ($acceso_id>0){
    // sesión
    $_SESSION['cliente_id']=(int)$cli['id'];
    $_SESSION['gimnasio_id']=(int)$cli['gimnasio_id'];

    // token persistente (si “Recordar”)
    if (!headers_sent() && !empty($_POST['recordar'])) {
      $raw = bin2hex(random_bytes(32)); // 64 hex
      $ua  = $conexion->real_escape_string($_SERVER['HTTP_USER_AGENT'] ?? '');
      $cid = (int)$cli['id'];
      $conexion->query("INSERT INTO cliente_tokens (cliente_id, gimnasio_id, token, user_agent)
                        VALUES ({$cid}, {$gimnasio_id}, '{$raw}', '{$ua}')");
      setcookie('cli_autologin', $cid.'.'.$raw, [
        'expires'  => time() + 60*60*24*180,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax'
      ]);
    }

    // Descuento de clase (idempotente por acceso)
    mem_aplicar_consumo($conexion, $gimnasio_id, (int)$cli['id'], $acceso_id);

    if (!empty($gym['webhook_checkin_url'])) {
      fire_webhook($gym['webhook_checkin_url'], [
        'tipo' => 'checkin',
        'gimnasio_id' => $gimnasio_id,
        'cliente_id' => (int)$cli['id'],
        'nombre' => $cli['nombre'].' '.$cli['apellido'],
        'hora' => date('c'),
        'metodo' => $dni?'QR-GYM/DNI':'QR-GYM/PIN',
        'lat' => $latReq, 'lon' => $lonReq
      ]);
    }

    render_page_done($gimnasio_id,$gym['nombre']??'', "✅ Ingreso marcado — ".h($cli['nombre'].' '.$cli['apellido'])." ".date('H:i'), true);
  } else {
    render_page_form("❌ Error al registrar el ingreso.", false, ['need_geo'=>true]);
  }
  exit;
}

/* ===== Primer render ===== */
render_page_form(null, null, ['need_geo'=>true]); // pide ubicación
exit;

/* ================== VISTAS ================== */
function base_css(){ return "body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#111;color:#eee}
.wrap{max-width:520px;margin:24px auto;padding:20px}
.card{background:#1b1b1b;border:1px solid #2a2a2a;border-radius:14px;padding:18px;box-shadow:0 6px 24px rgba(0,0,0,.25)}
label{display:block;margin:8px 0 6px;color:#bbb;font-size:14px}
input{width:100%;padding:12px 14px;border-radius:10px;border:1px solid #333;background:#151515;color:#eee;font-size:16px;outline:none}
input:focus{border-color:#6ea8fe}
.muted{color:#888;font-size:13px}
.flash{padding:12px 14px;border-radius:12px;margin-bottom:12px}
.center{text-align:center}
.big{font-size:28px;font-weight:800;letter-spacing:.2px}
.gym{color:#ffd166}
button{width:100%;padding:12px 14px;border-radius:12px;border:0;background:#6ea8fe;color:#000;font-weight:700;font-size:16px;margin-top:14px;cursor:pointer}
.row{display:flex;gap:10px;align-items:center}
.chk{display:flex;gap:8px;align-items:center;margin-top:8px}
.help{font-size:12px;color:#aaa}"; }

function render_page_form(?string $flash, ?bool $ok, array $opts=[]){
  $need_geo = !empty($opts['need_geo']);
  $pref_tel = $opts['pref_tel'] ?? '';
  $show_otp = !empty($opts['show_otp']);
  $okClass = $ok === null ? '' : ($ok ? 'bg-green-600' : 'bg-amber-600');
  $okIcon  = $ok === null ? '' : ($ok ? '✅' : '⚠️'); ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ingreso por QR</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="dark">
<style><?= base_css() ?></style>
<script>
let lat=null, lon=null;
function askGeo(){
  if (!navigator.geolocation) return;
  navigator.geolocation.getCurrentPosition(
    (pos)=>{ lat=pos.coords.latitude; lon=pos.coords.longitude;
      document.querySelectorAll('input[name=lat]').forEach(e=>e.value=lat);
      document.querySelectorAll('input[name=lon]').forEach(e=>e.value=lon);
    },
    (err)=>{ console.log('geo error', err); },
    { enableHighAccuracy:true, timeout:6000, maximumAge:0 }
  );
}
document.addEventListener('DOMContentLoaded', askGeo);
</script>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="center big">Ingreso <span class="gym">Gimnasio</span></div>
      <?php if ($flash): ?>
        <div class="flash <?= $okClass ?>"><?= $okIcon." ".h($flash) ?></div>
      <?php endif; ?>

      <!-- DNI / PIN -->
      <form method="post" class="card" style="background:#181818;margin-top:10px">
        <input type="hidden" name="lat"><input type="hidden" name="lon">
        <label>DNI (rápido)</label>
        <input inputmode="numeric" pattern="[0-9]*" name="dni" placeholder="Ej: 35123456" autofocus>
        <div class="muted" style="margin:6px 0 16px">o</div>
        <label>PIN (4-6 dígitos)</label>
        <input inputmode="numeric" pattern="[0-9]*" name="pin" placeholder="Tu PIN de cliente">
        <label class="chk"><input type="checkbox" name="recordar" value="1" style="width:auto"> Recordar este dispositivo</label>
        <button type="submit">Marcar mi ingreso</button>
        <?php if ($need_geo): ?>
          <div class="help">📍 Si se te solicita, permití la ubicación para validar que estás en el gimnasio.</div>
        <?php endif; ?>
      </form>

      <!-- OTP -->
      <div class="card" style="background:#181818;margin-top:10px">
        <form method="post" class="row" style="flex-wrap:wrap">
          <input type="hidden" name="lat"><input type="hidden" name="lon">
          <input name="telefono" placeholder="Tu WhatsApp / Teléfono" value="<?= h($pref_tel) ?>" style="flex:1 1 200px">
          <button name="otp_send" value="1">Enviar código</button>
        </form>
        <?php if ($show_otp): ?>
        <form method="post" class="row" style="flex-wrap:wrap;margin-top:10px">
          <input type="hidden" name="lat"><input type="hidden" name="lon">
          <input name="telefono" placeholder="WhatsApp/Teléfono" value="<?= h($pref_tel) ?>" style="flex:1 1 180px">
          <input name="otp_code" placeholder="Código de 6 dígitos" style="flex:1 1 160px">
          <button name="otp_verify" value="1">Validar código</button>
        </form>
        <?php endif; ?>
        <div class="help">Si no recordás tu PIN, pedí un código por WhatsApp/SMS.</div>
      </div>

    </div>
  </div>
</body>
</html>
<?php }

function render_page_done(int $g, string $gymName, ?string $flash, ?bool $ok){
  $okClass = $ok ? 'bg-green-600' : 'bg-amber-600';
  $okIcon  = $ok ? '✅' : '⚠️'; ?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ingreso registrado</title>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="color-scheme" content="dark">
<style><?= base_css() ?></style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="center big">Gimnasio #<?= (int)$g ?> <?= $gymName? '· '.h($gymName):'' ?></div>
      <div class="flash <?= $okClass ?>"><?= $okIcon." ".h($flash ?? '') ?></div>
      <div class="center muted">Podés cerrar esta ventana.</div>
    </div>
  </div>
</body>
</html>
<?php }
