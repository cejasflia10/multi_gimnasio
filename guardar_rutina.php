<?php
// guardar_rutina.php — Sube rutinas del profesor a Cloudinary (persistente) y guarda en rutinas_clientes
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

date_default_timezone_set('America/Argentina/San_Luis');

$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) { http_response_code(403); exit('❌ Sesión inválida.'); }

/* =========================
   Cloudinary (persistente)
   ========================= */
// Opción 1 (recomendada): usar CLOUDINARY_URL en variables de entorno:
//   CLOUDINARY_URL=cloudinary://API_KEY:API_SECRET@CLOUD_NAME
//
// Opción 2: credenciales en constantes (ya cargadas):
const CLOUD_ENABLED    = true;                            // ← activado
const CLOUD_NAME       = 'ddfugds9b';                     // ← tu cloud name
const CLOUD_API_KEY    = '657814174747186';               // ← tu API key
const CLOUD_API_SECRET = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c';   // ← tu API secret

function cloud_parse_env(): array {
  $url = getenv('CLOUDINARY_URL') ?: '';
  if ($url) {
    $p = parse_url($url); // cloudinary://key:secret@cloud
    if ($p && !empty($p['user']) && !empty($p['pass']) && !empty($p['host'])) {
      return ['cloud_name' => $p['host'], 'api_key' => $p['user'], 'api_secret' => $p['pass']];
    }
  }
  return ['cloud_name' => CLOUD_NAME, 'api_key' => CLOUD_API_KEY, 'api_secret' => CLOUD_API_SECRET];
}

function cloud_init(): void {
  static $init = false; if ($init) return; $init = true;
  if (!CLOUD_ENABLED && !getenv('CLOUDINARY_URL')) return;

  // autoload (proyecto o raíz)
  $autoload1 = __DIR__ . '/vendor/autoload.php';
  $autoload2 = dirname(__DIR__) . '/vendor/autoload.php';
  if (file_exists($autoload1)) require_once $autoload1;
  elseif (file_exists($autoload2)) require_once $autoload2;

  if (!class_exists('\Cloudinary\Configuration\Configuration')) {
    throw new RuntimeException('Cloudinary SDK no encontrado. Ejecutá: composer require cloudinary/cloudinary_php');
  }

  $cfg = cloud_parse_env();
  \Cloudinary\Configuration\Configuration::instance([
    'cloud' => [
      'cloud_name' => $cfg['cloud_name'],
      'api_key'    => $cfg['api_key'],
      'api_secret' => $cfg['api_secret'],
    ],
    'url' => ['secure' => true]
  ]);
}

/**
 * Sube usando SDK v2 (\Cloudinary\Api\Upload\UploadApi) o v1 (\Cloudinary\Uploader) según disponibilidad.
 */
function cloud_upload_auto(string $tmpPath, array $opts): array {
  cloud_init();

  if (class_exists('\Cloudinary\Api\Upload\UploadApi')) {
    // SDK v2
    $api = new \Cloudinary\Api\Upload\UploadApi();
    return $api->upload($tmpPath, $opts);
  }
  if (class_exists('\Cloudinary\Uploader')) {
    // SDK v1
    return \Cloudinary\Uploader::upload($tmpPath, $opts);
  }

  throw new RuntimeException('Cloudinary SDK no disponible tras cargar autoload.');
}

function redir_ok(int $ok = 1, string $extra = ''): void {
  $qs = "ok={$ok}";
  if ($extra !== '') $qs .= '&err=' . rawurlencode($extra);
  header('Location: subir_rutina.php?' . $qs);
  exit;
}

/* =========================
   Validaciones
   ========================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Método no permitido.');
}

$cliente_id = (int)($_POST['cliente_id'] ?? 0);
if ($cliente_id <= 0) { redir_ok(0, 'Alumno inválido.'); }

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  redir_ok(0, 'Error al recibir el archivo.');
}

$f = $_FILES['archivo'];
$maxBytes = 20 * 1024 * 1024; // 20MB
if ($f['size'] <= 0 || $f['size'] > $maxBytes) {
  redir_ok(0, 'Tamaño inválido (0 o supera 20 MB).');
}

// validar que el alumno pertenezca al gimnasio
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1");
$stmt->bind_param('ii', $cliente_id, $gimnasio_id);
$stmt->execute();
$cli = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cli) { redir_ok(0, 'El alumno no pertenece a este gimnasio.'); }

// detectar mime permitido
$mime = @mime_content_type($f['tmp_name']) ?: '';
$permitidos = [
  'application/pdf',
  'image/jpeg', 'image/png',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
if (!in_array($mime, $permitidos, true)) {
  redir_ok(0, 'Formato no permitido (PDF/JPG/PNG/DOC/DOCX).');
}

/* =========================
   Subida a Cloudinary
   ========================= */
$baseName  = pathinfo($f['name'], PATHINFO_FILENAME);
$sanitized = preg_replace('/[^A-Za-z0-9._-]+/', '_', $baseName);
$timestamp = date('Ymd_His');
$rand8     = substr(bin2hex(random_bytes(4)), 0, 8);

// public_id final (sin extensión). Ej: 20250917_201942_rutina-alumno-90_ab12cd34
$publicId = $timestamp . '_' . ($sanitized ?: 'rutina') . "_alumno-{$cliente_id}_{$rand8}";

// carpeta por cliente para orden: multi_gimnasio/rutinas/90
$folder = "multi_gimnasio/rutinas/{$cliente_id}";

try {
  $res = cloud_upload_auto($f['tmp_name'], [
    'resource_type'   => 'auto',      // acepta imágenes, pdf/docx, etc.
    'public_id'       => $publicId,
    'folder'          => $folder,
    'use_filename'    => false,
    'unique_filename' => false,
    'overwrite'       => true,
  ]);

  // Datos devueltos
  $url    = $res['secure_url'] ?? ($res['url'] ?? '');
  $pid    = $res['public_id']  ?? ($folder . '/' . $publicId);
  $bytes  = (int)($res['bytes'] ?? $f['size']);
  $format = strtolower((string)($res['format'] ?? ''));
  if (!$format) { $format = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION) ?: ''); }
  $nombre_original = $f['name'];

  if (!$url) {
    redir_ok(0, 'No se obtuvo URL de Cloudinary.');
  }

  // Asegurar tabla destino (la que usa el panel del cliente)
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

  // Guardar metadatos para que el cliente lo vea en su panel
  $stmt2 = $conexion->prepare("INSERT INTO rutinas_clientes
    (cliente_id, gimnasio_id, profesor_id, nombre_archivo, url_archivo, extension, tamano_bytes)
    VALUES (?, ?, ?, ?, ?, ?, ?)");
  $stmt2->bind_param(
    'iiisssi',
    $cliente_id, $gimnasio_id, $profesor_id, $nombre_original, $url, $format, $bytes
  );
  $ok = $stmt2->execute();
  $stmt2->close();

  if (!$ok) {
    redir_ok(0, 'No se pudo guardar metadatos en la BD.');
  }

  redir_ok(1);

} catch (Throwable $e) {
  $msg = $e->getMessage();
  if (stripos($msg, 'cloudinary') !== false && stripos($msg, 'sdk') !== false) {
    $msg .= ' (instalar: composer require cloudinary/cloudinary_php)';
  }
  redir_ok(0, 'Error subiendo: ' . $msg);
}
