<?php
/* ============================================================
   gym_qr_checkin.php — Check-in por QR (MultiGimnasio)
   (DNI y OTP; sin PIN)
   Cambios:
   - Usa membresias.gimnasio_id (no id_gimnasio)
   - Valida activa=1 + no vencida (+ clases opcional)
   - No redirige a registro si el cliente existe; solo avisa
   - Solo redirige a registro cuando NO hay cliente
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Config ===== */
$REINGRESO_BLOQUEO_MIN = 15;
$OTP_MINUTOS = 5;                 // duración del OTP
$OTP_CANAL_DEF = 'whatsapp';      // 'whatsapp'|'sms'|'email'
$REQUIERE_CLASES_DISPONIBLES = false; // ← poné true si querés exigir clases > 0

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
$rsG = $conexion->query("SELECT id, nombre, qr_secret, lat, lon, geofence_radius_m, webhook_checkin_url, logo FROM gimnasios WHERE id={$gimnasio_id} LIMIT 1");
if (!$rsG || !$rsG->num_rows){ http_response_code(404); exit('❌ Gimnasio no encontrado'); }
$gym = $rsG->fetch_assoc();

/* Marca visual */
$nombre_gimnasio = $gym['nombre'] ?? 'Gimnasio';
$logo_gimnasio   = $gym['logo'] ?? 'logo.png';

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
  $rs=$db->query("SELECT id, nombre, apellido, dni, telefono, gimnasio_id FROM clientes WHERE id={$id} LIMIT 1");
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}
function find_cliente_for_login(mysqli $db, int $gimnasio_id, ?string $dni){
  $dni = $dni ? preg_replace('/\D+/', '', $dni) : null;
  if (!$dni) return null;
  $sql = "SELECT id, nombre, apellido, dni, telefono, gimnasio_id
          FROM clientes
          WHERE dni=".qv($db,$dni)." AND gimnasio_id={$gimnasio_id}
          LIMIT 1";
  $rs = $db->query($sql);
  return $rs && $rs->num_rows ? $rs->fetch_assoc() : null;
}

/* === Membresía ACTIVA para este gimnasio ===
   Reglas: activa=1, no vencida, y si $REQUIERE_CLASES_DISPONIBLES=true => clases_disponibles>0
*/
function tiene_membresia_activa(mysqli $db, int $cliente_id, int $gimnasio_id, bool $requiereClases): bool{
  $sql = "SELECT activa, fecha_vencimiento, clases_disponibles
          FROM membresias
          WHERE cliente_id={$cliente_id}
            AND gimnasio_id={$gimnasio_id}
          ORDER BY COALESCE(fecha_vencimiento,'9999-12-31') DESC, id DESC
          LIMIT 1";
  $rs = $db->query($sql);
  if (!$rs || !$rs->num_rows) return false;
  $m = $rs->fetch_assoc();

  // activa=1 (o null se toma como 1 si querés; acá lo exigimos explícito 1)
  $activa_ok = ((string)($m['activa'] ?? '0') === '1');

  // no vencida
  $hoy = date('Y-m-d');
  $vto = (string)($m['fecha_vencimiento'] ?? '');
  $vto_ok = ($vto==='' || $vto==='0000-00-00' || $vto >= $hoy);

  // clases
  $clases_ok = true;
  if ($requiereClases) {
    $cl = is_null($m['clases_disponibles']) ? null : (int)$m['clases_disponibles'];
    $clases_ok = is_null($cl) ? true : ($cl > 0);
  }
  return $activa_ok && $vto_ok && $clases_ok;
}

function pudo_registrar(mysqli $db, int $cliente_id, int $gimnasio_id, int $min_bloqueo): bool{
  $sql="SELECT id FROM accesos_gimnasio
        WHERE cliente_id={$cliente_id} AND gimnasio_id={$gimnasio_id}
          AND fecha_ingreso >= (NOW() - INTERVAL {$min_bloqueo} MINUTE)
        ORDER BY fecha_ingreso DESC LIMIT 1";
  $rs=$db->query($sql);
  return !($rs && $rs->num_rows);
}
function registrar_acceso(mysqli $db, int $cliente_id, int $gimnasio_id, string $metodo='QR-GYM', ?float $lat=null, ?float $lon=null): bool{
  $latv = is_null($lat) ? "NULL" : (float)$lat;
  $lonv = is_null($lon) ? "NULL" : (float)$lon;
  $sql="INSERT INTO accesos_gimnasio (cliente_id, gimnasio_id, fecha_ingreso, metodo, lat, lon)
        VALUES ({$cliente_id}, {$gimnasio_id}, NOW(), ".qv($db,$metodo).", {$latv}, {$lonv})";
  return (bool)$db->query($sql);
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

/* ===== OTP (WhatsApp/SMS) ===== */
function generar_codigo_otp(int $len=6){ return str_pad((string)random_int(0, 10**$len - 1), $len, '0', STR_PAD_LEFT); }
function enviar_por_whatsapp_sms(string $destino, string $texto): bool {
  // TODO integrar proveedor real (Twilio, Meta Cloud, etc.)
  return true;
}

/* ===== Vistas (form y done) ===== */
function base_css(){ return "body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#111;color:#eee}
.wrap{max-width:520px;margin:24px auto;padding:20px}
.card{background:#1b1b1b;border:1px solid #2a2a2a;border-radius:14px;padding:18px;box-shadow:0 6px 24px rgba(0,0,0,.25)}
.logo{display:flex;gap:10px;align-items:center;margin-bottom:10px}
.logo img{height:42px;border-radius:10px;background:#fff;border:1px solid #2a2a2a;padding:6px}
.logo .name{font-weight:900;font-size:18px}
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
.help{font-size:12px;color:#aaa}"; }

function render_page_form(?string $flash, ?bool $ok, array $opts=[]){
  global $gimnasio_id, $nombre_gimnasio, $logo_gimnasio;
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
      <div class="logo">
        <img src="<?= h($logo_gimnasio) ?>" alt="logo">
        <div class="name"><?= h($nombre_gimnasio) ?></div>
      </div>

      <?php if ($flash): ?>
        <div class="flash <?= $okClass ?>"><?= $okIcon." ".h($flash) ?></div>
      <?php endif; ?>

      <!-- DNI -->
      <form method="post" class="card" style="background:#181818;margin-top:10px">
        <input type="hidden" name="lat"><input type="hidden" name="lon">
        <label>DNI</label>
        <input inputmode="numeric" pattern="[0-9]*" name="dni" placeholder="Ej: 35123456" autofocus>
        <button type="submit">Marcar mi ingreso</button>
        <?php if ($need_geo): ?>
          <div class="help">📍 Puede pedirse tu ubicación para validar que estás en el gimnasio.</div>
        <?php endif; ?>
        <div class="muted" style="margin-top:10px">
          ¿No estás registrado? <a style="color:#9cf" href="registro_online.php?g=<?= (int)$gimnasio_id ?>">Registrarme</a>
        </div>
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
        <div class="help">Si no recordás tu DNI o no coincide el teléfono, pedí un código por WhatsApp/SMS.</div>
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
      <div class="center big"><?= h($gymName) ?></div>
      <div class="flash <?= $okClass ?>"><?= $okIcon." ".h($flash ?? '') ?></div>
      <div class="center muted">Podés cerrar esta ventana.</div>
    </div>
  </div>
</body>
</html>
<?php }

/* ===== Acciones OTP ===== */
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
  $tel = trim($_POST['telefono'] ?? ''); $code = trim($_POST['otp_code'] ?? '');
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

  // Autenticar por teléfono en ESTE gimnasio
  $rsC = $conexion->query("SELECT id, nombre, apellido, gimnasio_id FROM clientes WHERE gimnasio_id={$gimnasio_id} AND telefono=".qv($conexion,$tel)." LIMIT 1");
  if (!$rsC || !$rsC->num_rows){
    // Teléfono ok pero no hay cliente: redirigir a registro
    header("Location: registro_online.php?g=".$gimnasio_id."&tel=".rawurlencode($tel));
    exit;
  }
  $cli = $rsC->fetch_assoc();
  $_SESSION['cliente_id']=(int)$cli['id'];
  $_SESSION['gimnasio_id']=(int)$cli['gimnasio_id'];
  // NO marcar ingreso automático; solo dejar logueado
  render_page_form("Teléfono validado. Ya podés ingresar tu DNI para marcar.", true, ['pref_tel'=>$tel, 'show_otp'=>true]);
  exit;
}

/* ===== Auto-checkin si hay sesión del MISMO gimnasio =====
   (Solo marca si el usuario envía DNI; acá NO forzamos nada)
*/

/* ===== POST: DNI ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['dni']) ){
  $dni  = preg_replace('/\D+/', '', (string)($_POST['dni'] ?? ''));
  $latReq = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
  $lonReq = isset($_POST['lon']) ? (float)$_POST['lon'] : null;

  if (!$dni){ render_page_form("❌ Ingresá tu DNI.", false, ['need_geo'=>true]); exit; }

  $cli = find_cliente_for_login($conexion,$gimnasio_id,$dni);
  if (!$cli){
    // No existe cliente: llevar a registro (esto sí)
    header("Location: registro_online.php?g=".$gimnasio_id."&dni=".rawurlencode($dni));
    exit;
  }

  // Geocerca (si configurada)
  if (!is_null($gym['geofence_radius_m']) && $gym['lat']!==null && $gym['lon']!==null) {
    $dist = haversine_m((float)$gym['lat'], (float)$gym['lon'], $latReq, $lonReq);
    if ($dist===null) { render_page_form("📍 Necesitamos tu ubicación para validar el ingreso.", false, ['need_geo'=>true]); exit; }
    if ($dist > (int)$gym['geofence_radius_m']) {
      render_page_form("🚫 Fuera del área del gimnasio (".(int)$dist." m). Acercate para marcar ingreso.", false, ['need_geo'=>true]); exit;
    }
  }

  // Membresía activa (gimnasio_id + activa + no vencida + (clases>0 opcional))
  $activaOK = tiene_membresia_activa($conexion,(int)$cli['id'],$gimnasio_id,$REQUIERE_CLASES_DISPONIBLES);
  if (!$activaOK){
    render_page_form("⚠️ Tenés usuario en el sistema, pero tu membresía no está activa o está vencida. Consultá en recepción.", false, ['need_geo'=>true]); exit;
  }

  // Anti doble ingreso
  if (!pudo_registrar($conexion,(int)$cli['id'],$gimnasio_id,$REINGRESO_BLOQUEO_MIN)){
    render_page_form("⏱️ Ya registraste un ingreso recientemente. Probá en {$REINGRESO_BLOQUEO_MIN} min.", false, ['need_geo'=>true]); exit;
  }

  // Registrar acceso
  $ok=registrar_acceso($conexion,(int)$cli['id'],$gimnasio_id,'QR-GYM/DNI',$latReq,$lonReq);
  if ($ok){
    // Sesión básica
    $_SESSION['cliente_id']=(int)$cli['id'];
    $_SESSION['gimnasio_id']=(int)$cli['gimnasio_id'];

    if (!empty($gym['webhook_checkin_url'])) {
      fire_webhook($gym['webhook_checkin_url'], [
        'tipo' => 'checkin',
        'gimnasio_id' => $gimnasio_id,
        'cliente_id' => (int)$cli['id'],
        'nombre' => ($cli['nombre']??'').' '.($cli['apellido']??''),
        'hora' => date('c'),
        'metodo' => 'QR-GYM/DNI',
        'lat' => $latReq, 'lon' => $lonReq
      ]);
    }
    render_page_done($gimnasio_id,$nombre_gimnasio, "✅ Ingreso marcado — ".h(($cli['nombre']??'').' '.($cli['apellido']??''))." ".date('H:i'), true);
  } else {
    render_page_form("❌ Error al registrar el ingreso.", false, ['need_geo'=>true]);
  }
  exit;
}

/* ===== Primer render ===== */
render_page_form(null, null, ['need_geo'=>true]);
exit;
