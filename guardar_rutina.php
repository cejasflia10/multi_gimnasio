<?php
// guardar_rutina.php — guarda el archivo en /uploads/rutinas (o /public/uploads/rutinas si existe)
// y genera una URL pública ABSOLUTA correcta tanto en XAMPP (carpeta proyecto bajo htdocs) como en hosting (Render, etc.)

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ====== Guards de sesión ====== */
$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) {
  http_response_code(403);
  exit('❌ Sesión inválida. Volvé a iniciar sesión.');
}

/* ====== Input ====== */
$cliente_id = isset($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : 0;
if ($cliente_id <= 0) redirect_err('Falta seleccionar el alumno.');

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
$origName = (string)($_FILES['archivo']['name'] ?? '');
$tmpPath  = (string)($_FILES['archivo']['tmp_name'] ?? '');
$size     = (int)($_FILES['archivo']['size'] ?? 0);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
if (!in_array($ext, $ALLOWED, true)) redirect_err('Tipo de archivo no permitido.');
if ($size <= 0 || $size > $maxBytes)  redirect_err('Archivo demasiado grande (máx 20 MB).');

/* ====== Rutas: proyecto, docroot y baseUri ====== */
$projectDir = str_replace('\\','/', realpath(__DIR__));                          // p.ej. C:/xampp/htdocs/multi_gimnasio
$docroot    = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
if (!$projectDir) redirect_err('No pude resolver el directorio del proyecto.');
if (!$docroot)     $docroot = $projectDir; // fallback seguro

// Base URI del proyecto respecto al docroot (ej: "/multi_gimnasio" en XAMPP; "" si el docroot ES la raíz del proyecto)
$baseUri = '';
if (strpos($projectDir, $docroot) === 0) {
  $baseUri = substr($projectDir, strlen($docroot)); // puede ser "" o "/multi_gimnasio"
  if ($baseUri === false) $baseUri = '';
}
if ($baseUri !== '' && $baseUri[0] !== '/') $baseUri = '/'.$baseUri;

/* ====== Carpeta destino: respetar tu estructura actual ======
   - Si YA existe /public/uploads/rutinas dentro del proyecto, usamos ESA (no movés nada).
   - Si no existe, usamos /uploads/rutinas en la raíz del proyecto.
*/
$dirPublicUploads = $projectDir . '/public/uploads/rutinas';
$dirRootUploads   = $projectDir . '/uploads/rutinas';

if (is_dir($dirPublicUploads)) {
  $baseDir = $dirPublicUploads;
  $relUriPrefix = '/public/uploads/rutinas'; // para armar la URL
} else {
  $baseDir = $dirRootUploads;
  $relUriPrefix = '/uploads/rutinas';
  if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);
}

if (!is_dir($baseDir) || !is_writable($baseDir)) {
  redirect_err('Carpeta no escribible: ' . $baseDir);
}

/* ====== Nombre único y mover ====== */
try { $rand = bin2hex(random_bytes(4)); } catch (Throwable $e) { $rand = uniqid(); }
$fname    = date('Ymd_His') . "_alumno-{$cliente_id}_{$rand}." . $ext;
$destPath = $baseDir . '/' . $fname;

if (!move_uploaded_file($tmpPath, $destPath)) {
  redirect_err('No se pudo guardar el archivo en el servidor (move_uploaded_file).');
}

/* ====== URL pública ABSOLUTA ====== */
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$scheme  = $isHttps ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Si definiste APP_BASE_URL (config propia), la respetamos. Si no, calculamos con host + baseUri.
if (!defined('APP_BASE_URL') || !APP_BASE_URL) {
  define('APP_BASE_URL', $scheme . '://' . $host . $baseUri);
}
$publicUrl = rtrim(APP_BASE_URL, '/') . $relUriPrefix . '/' . $fname;

// Ejemplos:
//  - XAMPP sin vhost:  http://localhost/multi_gimnasio/uploads/rutinas/archivo.pdf
//  - XAMPP si usás /public: http://localhost/multi_gimnasio/public/uploads/rutinas/archivo.pdf
//  - Render (todo en raíz): https://tu-app.onrender.com/uploads/rutinas/archivo.pdf
//  - Render (si usás subcarpeta public y la servís): https://tu-app.onrender.com/public/uploads/rutinas/archivo.pdf

/* ====== Tabla e inserción ====== */
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

/* ====== OK ====== */
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
