<?php
// guardar_rutina.php — guarda SIEMPRE bajo el docroot público (/public/uploads/rutinas) y registra en BD
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ Sin conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ====== Guards de sesión ====== */
$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) {
  http_response_code(403); exit('❌ Sesión inválida. Volvé a iniciar sesión.');
}

/* ====== Input ====== */
$cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
if ($cliente_id <= 0) { redirect_err('Falta seleccionar el alumno.'); }

/* Validar alumno pertenece al gimnasio */
if ($st = $conexion->prepare("SELECT 1 FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
  $st->bind_param('ii', $cliente_id, $gimnasio_id);
  $st->execute(); $st->store_result();
  if ($st->num_rows === 0) { $st->close(); redirect_err('Alumno inválido para este gimnasio.'); }
  $st->close();
} else {
  redirect_err('Error interno al validar alumno.');
}

/* ====== Archivo ====== */
if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
  redirect_err('No se recibió el archivo o hubo un error al subirlo.');
}
$ALLOWED  = ['pdf','jpg','jpeg','png','doc','docx'];
$maxBytes = 20 * 1024 * 1024; // 20 MB
$origName = (string)$_FILES['archivo']['name'];
$tmpPath  = (string)$_FILES['archivo']['tmp_name'];
$size     = (int)$_FILES['archivo']['size'];
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED, true)) redirect_err('Tipo de archivo no permitido.');
if ($size <= 0 || $size > $maxBytes) redirect_err('Archivo demasiado grande (máx 20 MB).');

/* ====== Resolver DOCROOT confiable (preferimos /public) ====== */
$docroot = '';
$try = [];

/* 1) Si el proyecto tiene /public al lado de este archivo */
$try[] = realpath(__DIR__ . '/public');
$try[] = realpath(__DIR__ . '/../public');   // si este archivo está en /src, /app, etc.
$try[] = realpath(__DIR__ . '/../../public'); // un nivel más por las dudas

/* 2) DOCUMENT_ROOT del servidor */
$dr = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') : '';
$try[] = ($dr !== '') ? $dr : null;

/* 3) Último recurso: la carpeta de este archivo (no ideal, pero funciona) */
$try[] = realpath(__DIR__);

foreach ($try as $cand) {
  if ($cand && is_dir($cand)) { $docroot = $cand; break; }
}
if ($docroot === '') {
  redirect_err('No pude resolver el directorio público (docroot).');
}

/* Si el docroot no es “public” pero existe una carpeta public dentro, usala */
if (basename($docroot) !== 'public') {
  $cand = realpath($docroot . '/public');
  if ($cand && is_dir($cand)) $docroot = $cand;
}

/* ====== Carpeta destino dentro del docroot ====== */
$publicRel = '/uploads/rutinas';                 // ruta accesible por URL
$baseDir   = rtrim($docroot, '/') . $publicRel;  // ruta física absoluta

if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }
if (!is_dir($baseDir)) {
  redirect_err('No existe la carpeta destino: ' . $baseDir);
}
if (!is_writable($baseDir)) {
  redirect_err('La carpeta no es escribible: ' . $baseDir);
}

/* ====== Nombre único y mover ====== */
try { $rand = bin2hex(random_bytes(4)); } catch (Throwable $e) { $rand = uniqid(); }
$fname    = date('Ymd_His') . "_alumno-{$cliente_id}_{$rand}." . $ext;
$destPath = $baseDir . '/' . $fname;

if (!move_uploaded_file($tmpPath, $destPath)) {
  redirect_err('No se pudo guardar el archivo en el servidor (move_uploaded_file).');
}

/* ====== URL pública ====== */
$publicPath = $publicRel . '/' . $fname; // ej: /uploads/rutinas/20250917_...
if (defined('APP_BASE_URL') && APP_BASE_URL) {
  $publicUrl = rtrim(APP_BASE_URL, '/') . $publicPath;
} else {
  // relativa por si lo mostrás adentro de tu app
  $publicUrl = ltrim($publicPath, '/'); // 'uploads/rutinas/...'
}

/* ====== Registrar en BD ====== */
$conexion->query("
  CREATE TABLE IF NOT EXISTS rutinas_clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    profesor_id INT NOT NULL,
    cliente_id INT NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    url_archivo TEXT NOT NULL,
    tamano_bytes BIGINT UNSIGNED NOT NULL,
    extension VARCHAR(10) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gym_cliente (gimnasio_id, cliente_id),
    INDEX idx_profesor (profesor_id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$sql = "INSERT INTO rutinas_clientes
        (gimnasio_id, profesor_id, cliente_id, nombre_archivo, url_archivo, tamano_bytes, extension)
        VALUES (?,?,?,?,?,?,?)";
$stmt = $conexion->prepare($sql);
if (!$stmt) {
  @unlink($destPath);
  redirect_err('Error al preparar inserción: ' . $conexion->error);
}
$basename = basename($destPath);
$stmt->bind_param('iiissis', $gimnasio_id, $profesor_id, $cliente_id, $basename, $publicUrl, $size, $ext);
$ok = $stmt->execute();
$stmt->close();

if (!$ok) {
  @unlink($destPath);
  redirect_err('No se pudo guardar en la base de datos.');
}

/* ====== Éxito ====== */
if (!headers_sent()) {
  header('Location: subir_rutina.php?ok=1');
  exit;
}
echo "✅ Rutina subida correctamente. <a href=\"subir_rutina.php\">Volver</a>";

/* ====== Helper ====== */
function redirect_err(string $msg){
  $msg = trim($msg);
  if (!headers_sent()) {
    header('Location: subir_rutina.php?err=' . urlencode($msg));
  } else {
    echo '❌ ' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
  }
  exit;
}
