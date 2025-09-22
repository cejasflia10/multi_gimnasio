<?php
// guardar_rutina.php — Sube rutinas del profesor a Cloudinary (persistente) y guarda metadatos en MySQL
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión BD'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) { http_response_code(403); exit('❌ Sesión inválida.'); }

/* =========================
   Cloudinary (persistente)
   ========================= */
// Opción 1: usa CLOUDINARY_URL en variables de entorno
// Opción 2: completa acá tus credenciale
const CLOUD_ENABLED    = true;                 // ← activado
const CLOUD_NAME       = 'ddfugds9b';          // ← tu cloud name
const CLOUD_API_KEY    = '657814174747186';    // ← tu API key
const CLOUD_API_SECRET = 'TKo5BRiKCEjxSLFzn2DLbz_ji4c'; // ← tu API secret

function cloud_init(): void {
  static $ok=false; if ($ok) return; $ok=true;
  if (!CLOUD_ENABLED && !getenv('CLOUDINARY_URL')) return;

  $autoload1 = __DIR__ . '/vendor/autoload.php';
  $autoload2 = dirname(__DIR__) . '/vendor/autoload.php';
  if (file_exists($autoload1)) require_once $autoload1;
  elseif (file_exists($autoload2)) require_once $autoload2;

  if (!class_exists('\Cloudinary\Configuration\Configuration')) {
    throw new RuntimeException('Cloudinary SDK no encontrado. Ejecutá "composer require cloudinary/cloudinary_php".');
  }

  // Si tenés CLOUDINARY_URL, podés omitir esto; igual lo dejamos explícito.
  \Cloudinary\Configuration\Configuration::instance([
    'cloud' => [
      'cloud_name' => CLOUD_NAME,
      'api_key'    => CLOUD_API_KEY,
      'api_secret' => CLOUD_API_SECRET,
    ],
    'url' => ['secure' => true]
  ]);
}

function subir_cloudinary_auto(string $tmpPath, string $publicId, string $folder): array {
  cloud_init();
  $uploader = new \Cloudinary\Uploader();

  // resource_type:auto -> decide solo (imagenes como image, pdf/doc como raw)
  return $uploader->upload($tmpPath, [
    'resource_type'    => 'auto',
    'public_id'        => $publicId,
    'use_filename'     => false,
    'unique_filename'  => false,
    'overwrite'        => true,
    'folder'           => $folder,   // ej: multi_gimnasio/rutinas/90
  ]);
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

// detectar mime
$mime = @mime_content_type($f['tmp_name']) ?: '';
$permitidos = [
  'application/pdf',
  'image/jpeg','image/png',
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
  $res = subir_cloudinary_auto($f['tmp_name'], $publicId, $folder);

  // Datos devueltos
  $url    = $res['secure_url'] ?? ($res['url'] ?? '');
  $pid    = $res['public_id']  ?? ($folder . '/' . $publicId);
  $bytes  = (int)($res['bytes'] ?? $f['size']);
  $format = (string)($res['format'] ?? '');
  $nombre_original = $f['name'];

  if (!$url) {
    redir_ok(0, 'No se obtuvo URL de Cloudinary.');
  }

  // Guardar metadatos
  $stmt2 = $conexion->prepare("INSERT INTO cliente_archivos
    (cliente_id, gimnasio_id, profesor_id, nombre_original, public_id, url, formato, mime, bytes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt2->bind_param(
    'iiisssssi',
    $cliente_id, $gimnasio_id, $profesor_id, $nombre_original, $pid, $url, $format, $mime, $bytes
  );
  $ok = $stmt2->execute();
  $stmt2->close();

  if (!$ok) {
    redir_ok(0, 'No se pudo guardar metadatos en la BD.');
  }

  redir_ok(1);

} catch (Throwable $e) {
  redir_ok(0, 'Error subiendo: ' . $e->getMessage());
}
