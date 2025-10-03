<?php
// guardar_rutina.php — Subida de rutinas a Cloudinary (image|raw según MIME) y registro en rutinas_clientes
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/Argentina/San_Luis');

$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) { http_response_code(403); exit('❌ Sesión inválida.'); }

/* ========= Cloudinary =========
   Opción 1 (recomendada): CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
   Opción 2: completar aquí:
*/
const CLOUD_ENABLED    = true;
const CLOUD_NAME       = 'ddfugds9b';
const CLOUD_API_KEY    = '657814174747186';
const CLOUD_API_SECRET = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';

// Si usás upload preset *unsigned*, setealo (o por env CLOUDINARY_UNSIGNED_PRESET)
const CLOUD_UNSIGNED_PRESET  = '';  // ej: 'gym_unsigned'

// Debug opcional (no imprimir secretos)
const CLOUD_DEBUG = false;

/* ===== Helpers Cloudinary ===== */
function cld_from_env_or_const(): array {
  $url = getenv('CLOUDINARY_URL') ?: '';
  if ($url) {
    $p = parse_url($url); // cloudinary://key:secret@cloud
    if ($p && !empty($p['user']) && !empty($p['pass']) && !empty($p['host'])) {
      return ['cloud_name'=>$p['host'],'api_key'=>$p['user'],'api_secret'=>$p['pass']];
    }
  }
  return ['cloud_name'=>CLOUD_NAME,'api_key'=>CLOUD_API_KEY,'api_secret'=>CLOUD_API_SECRET];
}

function cld_unsigned_preset(): string {
  $v = getenv('CLOUDINARY_UNSIGNED_PRESET') ?: CLOUD_UNSIGNED_PRESET;
  return trim((string)$v);
}

function cld_sdk_available(): bool {
  $a1 = __DIR__ . '/vendor/autoload.php';
  $a2 = dirname(__DIR__) . '/vendor/autoload.php';
  if (file_exists($a1)) require_once $a1; elseif (file_exists($a2)) require_once $a2;
  return class_exists('\Cloudinary\Configuration\Configuration') &&
         (class_exists('\Cloudinary\Api\Upload\UploadApi') || class_exists('\Cloudinary\Uploader'));
}

function cld_init_sdk(array $cfg): void {
  \Cloudinary\Configuration\Configuration::instance([
    'cloud' => [
      'cloud_name' => $cfg['cloud_name'],
      'api_key'    => $cfg['api_key'],
      'api_secret' => $cfg['api_secret'],
    ],
    'url' => ['secure' => true]
  ]);
}

/** Firma REST: solo los parámetros enviados (sin file/api_key/signature). */
function cld_sign(array $params, string $apiSecret): string {
  ksort($params);
  $pieces = [];
  foreach ($params as $k => $v) {
    if ($v === '' || $v === null) continue;
    $pieces[] = $k . '=' . $v;
  }
  $base = implode('&', $pieces);
  if (CLOUD_DEBUG) @file_put_contents('/tmp/cloud_debug.log', "[SIGN]\n$base\n", FILE_APPEND);
  return sha1($base . $apiSecret);
}

/** Log seguro (enmascara api_key/signature) */
function cld_debug_log(string $label, array $data): void {
  if (!CLOUD_DEBUG) return;
  $safe = $data;
  if (isset($safe['api_key']))       $safe['api_key'] = substr($safe['api_key'], 0, 4) . '****';
  if (isset($safe['signature']))     $safe['signature'] = substr($safe['signature'], 0, 6) . '****';
  if (isset($safe['Authorization'])) $safe['Authorization'] = '****';
  @file_put_contents('/tmp/cloud_debug.log', "[$label] " . print_r($safe, true) . "\n", FILE_APPEND);
}

/**
 * Subida a Cloudinary con endpoint según MIME:
 *  - $resourceType = 'image' (jpg/png/webp)
 *  - $resourceType = 'raw'   (pdf/doc/docx)
 * Usa: unsigned si hay preset; si no, SDK; si no, REST firmada.
 */
function cld_upload(string $tmpPath, string $publicId, string $folder, array $cfg, string $resourceType): array {
  $unsigned = cld_unsigned_preset();

  // (A) UNSIGNED si hay preset
  if ($unsigned !== '') {
    if (!function_exists('curl_init')) throw new RuntimeException('cURL no habilitado.');
    $endpoint = "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/{$resourceType}/upload";
    $post = [
      'upload_preset' => $unsigned,
      'public_id'     => $publicId,
      'folder'        => $folder,
      'file'          => new CURLFile($tmpPath, mime_content_type($tmpPath) ?: 'application/octet-stream', basename($tmpPath)),
    ];
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
      CURLOPT_POST           => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS     => $post,
      CURLOPT_TIMEOUT        => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code= (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$raw, true);
    if ($code >= 400 || !$json || isset($json['error'])) {
      cld_debug_log('UNSIGNED ERR', ['code'=>$code, 'err'=>$err, 'raw'=>$raw, 'endpoint'=>$endpoint]);
      // continúa con firmado
    } else {
      return $json;
    }
  }

  // (B) SDK si está
  if (cld_sdk_available()) {
    cld_init_sdk($cfg);
    $opts = [
      'resource_type'   => $resourceType, // <- clave
      'public_id'       => $publicId,
      'folder'          => $folder,
      'use_filename'    => false,
      'unique_filename' => false,
      'overwrite'       => true,
    ];
    if (class_exists('\Cloudinary\Api\Upload\UploadApi')) {
      $api = new \Cloudinary\Api\Upload\UploadApi();
      return $api->upload($tmpPath, $opts);
    }
    return \Cloudinary\Uploader::upload($tmpPath, $opts);
  }

  // (C) REST firmada
  if (!function_exists('curl_init')) throw new RuntimeException('cURL no habilitado.');
  $endpoint  = "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/{$resourceType}/upload";
  $timestamp = time();

  $params_to_send = [
    'folder'    => $folder,
    'public_id' => $publicId,
    'timestamp' => $timestamp,
  ];
  $signature = cld_sign($params_to_send, $cfg['api_secret']);

  $post = $params_to_send + [
    'api_key'   => $cfg['api_key'],
    'signature' => $signature,
    'file'      => new CURLFile($tmpPath, mime_content_type($tmpPath) ?: 'application/octet-stream', basename($tmpPath)),
  ];

  cld_debug_log('POST', ['endpoint'=>$endpoint] + $post);

  $ch = curl_init($endpoint);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS     => $post,
    CURLOPT_TIMEOUT        => 60,
  ]);
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $code= (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($raw === false || $code >= 400) {
    cld_debug_log('REST ERR', ['code'=>$code, 'err'=>$err, 'raw'=>$raw, 'endpoint'=>$endpoint]);
    $json = json_decode((string)$raw, true);
    $msg  = $json['error']['message'] ?? "Fallo HTTP {$code}" . ($err ? ": $err" : '');
    throw new RuntimeException($msg);
  }

  $json = json_decode((string)$raw, true);
  if (!$json || isset($json['error'])) {
    $msg = $json['error']['message'] ?? 'Respuesta inválida de Cloudinary';
    throw new RuntimeException($msg);
  }
  return $json;
}

/* ===== Utilidades ===== */
function redir_ok(int $ok = 1, string $extra = ''): void {
  $qs = "ok={$ok}";
  if ($extra !== '') $qs .= '&err=' . rawurlencode($extra);
  header('Location: subir_rutina.php?' . $qs);
  exit;
}

/* ===== Validaciones request ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Método no permitido.'); }

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
if ($cliente_id <= 0) { redir_ok(0, 'Alumno inválido.'); }

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  redir_ok(0, 'Error al recibir el archivo.');
}

$f = $_FILES['archivo'];
$maxBytes = 20 * 1024 * 1024; // 20MB
if ($f['size'] <= 0 || $f['size'] > $maxBytes) { redir_ok(0, 'Tamaño inválido (0 o supera 20 MB).'); }

// validar que el alumno pertenezca al gimnasio
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1");
$stmt->bind_param('ii', $cliente_id, $gimnasio_id);
$stmt->execute();
$cli = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cli) { redir_ok(0, 'El alumno no pertenece a este gimnasio.'); }

// validar MIME real
$mime = @mime_content_type($f['tmp_name']) ?: '';
$permitidos = [
  'application/pdf',
  'image/jpeg','image/jpg','image/png','image/webp',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
if (!in_array($mime, $permitidos, true)) {
  redir_ok(0, 'Formato no permitido (PDF/JPG/PNG/WEBP/DOC/DOCX).');
}

// Elegir endpoint (¡clave!)
// Imágenes -> image ; PDF/DOC/DOCX -> raw
$isImage = in_array($mime, ['image/jpeg','image/jpg','image/png','image/webp','image/gif'], true);
$resourceType = $isImage ? 'image' : 'raw';

/* ===== Subida a Cloudinary ===== */
if (!CLOUD_ENABLED) { redir_ok(0, 'Cloudinary deshabilitado.'); }

$baseName  = pathinfo($f['name'], PATHINFO_FILENAME);
$ext       = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
$sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName);
$timestamp = date('Ymd_His');
try { $rand8 = substr(bin2hex(random_bytes(4)), 0, 8); } catch (Throwable $e) { $rand8 = substr(md5(uniqid('', true)), 0, 8); }

$publicId = $timestamp . '_' . ($sanitized ?: 'rutina') . "_alumno-{$cliente_id}_{$rand8}";
$folder   = "multi_gimnasio/rutinas/{$cliente_id}";

$cfg = cld_from_env_or_const();

try {
  $res = cld_upload($f['tmp_name'], $publicId, $folder, $cfg, $resourceType);

  $url    = $res['secure_url'] ?? ($res['url'] ?? '');
  $bytes  = (int)($res['bytes'] ?? $f['size']);
  $format = strtolower((string)($res['format'] ?? $ext));
  $nombre = $f['name'];

  if (!$url) { throw new RuntimeException('No se obtuvo URL de Cloudinary.'); }

  // Tabla (idempotente)
  $conexion->query("
    CREATE TABLE IF NOT EXISTS rutinas_clientes (
      id INT AUTO_INCREMENT PRIMARY KEY,
      cliente_id INT NOT NULL,
      gimnasio_id INT NOT NULL,
      profesor_id INT NOT NULL,
      nombre_archivo VARCHAR(255) NOT NULL,
      url_archivo TEXT NOT NULL,
      extension VARCHAR(16) DEFAULT '',
      tamano_bytes INT DEFAULT 0,
      creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_cli (cliente_id),
      INDEX idx_gym (gimnasio_id),
      INDEX idx_fecha (creado_en)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Guardar metadatos
  $stmt2 = $conexion->prepare("INSERT INTO rutinas_clientes
    (cliente_id, gimnasio_id, profesor_id, nombre_archivo, url_archivo, extension, tamano_bytes)
    VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt2->bind_param('iiisssi', $cliente_id, $gimnasio_id, $profesor_id, $nombre, $url, $format, $bytes);
  $ok = $stmt2->execute();
  $stmt2->close();

  if (!$ok) { throw new RuntimeException('No se pudo guardar metadatos en la BD.'); }

  redir_ok(1);

} catch (Throwable $e) {
  $msg = $e->getMessage();
  if (stripos($msg, 'signature') !== false) $msg = 'Firma inválida (revisar API Secret y reloj del servidor).';
  if (stripos($msg, 'Invalid credentials') !== false) $msg = 'Credenciales inválidas (Cloud name / API key / secret).';
  redir_ok(0, 'Error subiendo: ' . $msg);
}
