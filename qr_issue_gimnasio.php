<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function hex2bin_s($hex){
  $hex = preg_replace('/[^0-9a-f]/i','',$hex ?? '');
  if ($hex === '' || (strlen($hex) % 2) !== 0) return null;
  $b = @hex2bin($hex);
  return $b === false ? null : $b;
}
function server_scheme(){
  // Respeta reverse proxies
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) return (stripos($_SERVER['HTTP_X_FORWARDED_PROTO'],'https')!==false) ? 'https' : 'http';
  if (!empty($_SERVER['REQUEST_SCHEME'])) return $_SERVER['REQUEST_SCHEME'];
  if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return 'https';
  return 'http';
}
function server_host(){
  if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) return $_SERVER['HTTP_X_FORWARDED_HOST'];
  if (!empty($_SERVER['HTTP_HOST'])) return $_SERVER['HTTP_HOST'];
  return $_SERVER['SERVER_NAME'] ?? 'localhost';
}
function base_path(){
  // Directorio del script actual sin la última barra
  $dir = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
  return $dir === '' ? '' : $dir;
}

/* ===== Parámetros ===== */
$g = (int)($_GET['g'] ?? ($_SESSION['gimnasio_id'] ?? 0));
if ($g<=0){ http_response_code(400); exit('Falta ?g'); }

$horas = max(1, min(168, (int)($_GET['horas'] ?? 8)));        // validez default 8h
$size  = max(120, min(800, (int)($_GET['size']  ?? 420)));    // tamaño del PNG (px)
$exp   = gmdate('Y-m-d\TH:i:s\Z', time()+$horas*3600);

/* ===== Gym + firma ===== */
$rs = $conexion->query("SELECT id,nombre,qr_secret FROM gimnasios WHERE id={$g} LIMIT 1");
if (!$rs || !$rs->num_rows){ http_response_code(404); exit('Gimnasio no encontrado'); }
$gym = $rs->fetch_assoc();

$secret_bin = null;
if (!empty($gym['qr_secret'])) {
  $secret_bin = hex2bin_s($gym['qr_secret']); // null si no es hex válido
}

/* ===== URL destino (gym_qr_checkin.php) ===== */
$scheme = server_scheme();
$host   = server_host();
$dir    = base_path();
$base_q = "g={$g}&exp=".rawurlencode($exp);
$sig    = '';

if ($secret_bin !== null){
  $sig = hash_hmac('sha256', $base_q, $secret_bin);
}
$dest  = "{$scheme}://{$host}{$dir}/gym_qr_checkin.php?{$base_q}".($sig ? "&sig={$sig}" : "");

/* ===== QR público (servicio externo) ===== */
$qrPng = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&margin=10&data=".rawurlencode($dest);

/* ===== Vista ===== */
?><!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><title>Emitir QR · Gimnasio #<?= (int)$g ?></title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
  :root{ color-scheme:dark; }
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial,sans-serif;background:#0f0f10;color:#e6e6e6}
  .wrap{max-width:720px;margin:24px auto;padding:0 16px}
  .card{background:#151515;border:1px solid #222;border-radius:14px;padding:18px}
  input,button{padding:10px 12px;border-radius:10px;border:1px solid #333;background:#111;color:#e6e6e6}
  button{background:#7fb3ff;color:#000;border:0;font-weight:700;cursor:pointer}
  .row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
  img{display:block;border-radius:12px;border:1px solid #333;background:#fff}
  .muted{color:#aaa}
  .qrbox{display:grid;place-items:center;margin-top:14px}
  .url{word-break:break-all;background:#0d0d0d;border:1px dashed #333;padding:10px;border-radius:8px;margin-top:10px}
  @media print{ .noprint{display:none} body{background:#fff;color:#000} .card{border:0} }
</style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h2>QR de ingreso — <?= h($gym['nombre'] ?: ("Gimnasio #".$g)) ?></h2>
      <form method="get" class="row noprint">
        <input type="hidden" name="g" value="<?= (int)$g ?>">
        <label>Validez (horas)</label>
        <input type="number" min="1" max="168" name="horas" value="<?= (int)$horas ?>">
        <label>Tamaño (px)</label>
        <input type="number" min="120" max="800" name="size" value="<?= (int)$size ?>">
        <button type="submit">Actualizar</button>
        <button type="button" onclick="window.print()">Imprimir</button>
      </form>

      <div class="qrbox">
        <img src="<?= h($qrPng) ?>" width="<?= (int)$size ?>" height="<?= (int)$size ?>" alt="QR de ingreso">
      </div>
      <div class="url muted">URL: <?= h($dest) ?></div>

      <?php if (!$gym['qr_secret']): ?>
        <p class="muted noprint">⚠️ Este QR no está firmado (no hay <code>qr_secret</code>). Podría ser reutilizado sin control de firma.</p>
      <?php elseif ($secret_bin === null): ?>
        <p class="muted noprint">⚠️ <code>qr_secret</code> no es HEX válido: se omitió la firma.</p>
      <?php else: ?>
        <p class="muted">Firma HMAC aplicada. Expira: <b><?= h($exp) ?> (UTC)</b></p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
