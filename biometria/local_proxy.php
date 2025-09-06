<?php
// /biometria/local_proxy.php
// Proxy SAME-ORIGIN para hablar con el servicio del lector vía HTTP.
// Ahora permite localhost/127.0.0.1/::1 y también IPs privadas de LAN (10.x, 172.16-31.x, 192.168.x).
// Requiere php-curl habilitado.

$DEFAULT_BASE = getenv('LOCAL_ZK_BASE') ?: 'http://127.0.0.1:5177';
$TIMEOUT = 20; // segundos

function out_json($code, $arr){
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}
function json_error($code, $msg){
  out_json($code, ['ok'=>false,'proxy'=>true,'error'=>$msg]);
}

// Valida base: http/https + host permitido (localhost/127.0.0.1/::1 o IP privada RFC1918) + puerto válido
function sanitize_base($base){
  if (!$base) return false;
  $u = parse_url($base);
  if (!$u || empty($u['scheme']) || empty($u['host'])) return false;
  $scheme = strtolower($u['scheme']);
  if (!in_array($scheme, ['http','https'])) return false;

  $host = strtolower($u['host']);
  $isLocal =
    $host === 'localhost' ||
    $host === '127.0.0.1' ||
    $host === '::1';

  // IP privada v4 (10/8, 172.16-31/12, 192.168/16)
  $isPrivateV4 = preg_match('/^(10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[0-1])\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3})$/', $host);

  if (!($isLocal || $isPrivateV4)) return false;

  $port = isset($u['port']) ? (int)$u['port'] : ($scheme==='https'?443:80);
  if ($port<=0 || $port>65535) return false;

  return $scheme.'://'.$host.':'.$port;
}

// --- Parámetros ---
$path = '';
if (isset($_GET['p'])) $path = trim($_GET['p']);
elseif (!empty($_SERVER['PATH_INFO'])) $path = ltrim($_SERVER['PATH_INFO'], '/');
$path = strtolower($path);

if (!in_array($path, ['enroll','health','rescan'], true)) {
  json_error(400, 'Parámetro "p" inválido. Use enroll, health o rescan.');
}

$base = isset($_GET['base']) ? sanitize_base($_GET['base']) : false;
if ($base === false) $base = sanitize_base($DEFAULT_BASE);
if ($base === false) json_error(400, 'Base inválida. Use http://127.0.0.1:PUERTO o http://192.168.x.x:PUERTO');

$query = $_GET;
unset($query['p'], $query['base']);
$qstr = http_build_query($query);
$target = rtrim($base, '/') . '/' . $path . ($qstr ? ('?' . $qstr) : '');

// Método y body
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$body   = file_get_contents('php://input');

if (!function_exists('curl_init')) json_error(500, 'cURL no está habilitado en PHP.');

$ch = curl_init($target);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, $TIMEOUT);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
// Si es POST/PUT/PATCH, reenviamos body
if (in_array($method, ['POST','PUT','PATCH'])) {
  curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}
// Propagar Content-Type si lo hay
$headers = [];
if (!empty($_SERVER['CONTENT_TYPE'])) $headers[] = 'Content-Type: ' . $_SERVER['CONTENT_TYPE'];
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// Forzar IPv4 si hubiera líos con resolución
if (defined('CURLOPT_IPRESOLVE')) {
  curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
}

$raw = curl_exec($ch);
if ($raw === false) {
  $err = curl_error($ch);
  $errno = curl_errno($ch);
  curl_close($ch);
  // Informe más claro
  json_error(502, "Proxy cURL error ($errno): $err | target=$target");
}

$code   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$hdrLen = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$respHeaders = substr($raw, 0, $hdrLen);
$respBody    = substr($raw, $hdrLen);
curl_close($ch);

// Content-Type que vino del destino
$ct = 'application/json; charset=utf-8';
foreach (explode("\r\n", $respHeaders) as $h) {
  if (stripos($h, 'Content-Type:') === 0) { $ct = trim(substr($h, strlen('Content-Type:'))); break; }
}

http_response_code($code);
header('Content-Type: ' . $ct);

// Si el body vino vacío, devolvemos JSON consistente
if ($respBody === '' || $respBody === false) {
  out_json($code, ['ok'=>false,'proxy'=>true,'status'=>$code,'error'=>'(cuerpo vacío)','target'=>$target]);
}
echo $respBody;
