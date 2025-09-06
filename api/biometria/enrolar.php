<?php
// /api/biometria/enrolar.php
if (session_status()===PHP_SESSION_NONE) session_start();

/* ===== Salida en JSON SIEMPRE ===== */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');
header('Access-Control-Allow-Methods: POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); ob_end_clean(); exit; }

/* ===== Helpers de respuesta ===== */
function json_out(int $code, array $payload){
  http_response_code($code);
  // Vaciar cualquier salida previa y forzar JSON
  if (ob_get_level()) { @ob_clean(); }
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  // En caso de FastCGI, forzar flush
  if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
  exit;
}

/* ===== Handlers de error/exception/fatal ===== */
ini_set('display_errors','0'); error_reporting(E_ALL);
set_exception_handler(function(Throwable $e){
  error_log('[biometria enrolar][EX] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
  json_out(500, ['ok'=>false,'error'=>'EX: '.$e->getMessage()]);
});
set_error_handler(function($sev,$msg,$file,$line){
  error_log('[biometria enrolar][ERR] '.$msg.' @ '.$file.':'.$line);
  json_out(500, ['ok'=>false,'error'=>"ERR: $msg @ $file:$line"]);
});
register_shutdown_function(function(){
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) {
    error_log('[biometria enrolar][FATAL] '.$e['message'].' @ '.$e['file'].':'.$e['line']);
    json_out(500, ['ok'=>false,'error'=>'FATAL: '.$e['message'].' @ '.$e['file'].':'.$e['line']]);
  }
});

/* ===== Cargar conexion.php de forma robusta ===== */
$root = __DIR__;
$conexion = null;
for ($i=0; $i<6; $i++) {
  $a = $root.'/conexion.php'; $b = $root.'/../conexion.php';
  if (file_exists($a)) { require_once $a; break; }
  if (file_exists($b)) { require_once $b; break; }
  $root = dirname($root);
}
if (!isset($conexion) || !($conexion instanceof mysqli)) {
  json_out(500, ['ok'=>false,'error'=>'No hay conexión a la base de datos.']);
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== API KEY (opcional) ===== */
$API_KEY = getenv('API_KEY_BIOMETRIA') ?: '';
if ($API_KEY) {
  $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
  if (!hash_equals($API_KEY, $key)) {
    json_out(401, ['ok'=>false,'error'=>'API key inválida']);
  }
}

/* ===== Validar método ===== */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_out(405, ['ok'=>false,'error'=>'Método no permitido']);
}

/* ===== Leer JSON ===== */
$raw = file_get_contents('php://input');
if ($raw === '' || $raw === false) {
  json_out(400, ['ok'=>false,'error'=>'Body vacío']);
}
$in = json_decode($raw, true);
if (!is_array($in)) {
  json_out(400, ['ok'=>false,'error'=>'Body no es JSON']);
}

/* ===== Campos ===== */
$persona_tipo = $in['persona_tipo'] ?? '';
$persona_id   = (int)($in['persona_id'] ?? 0);
$gimnasio_id  = (int)($in['gimnasio_id'] ?? 0);
$template_b64 = (string)($in['template_b64'] ?? '');
$version      = trim((string)($in['version'] ?? 'ZKFinger10'));

/* ===== Validaciones ===== */
if ($gimnasio_id<=0) { json_out(400, ['ok'=>false,'error'=>'gimnasio_id requerido']); }
if (!in_array($persona_tipo,['profesor','cliente'],true)) { json_out(400, ['ok'=>false,'error'=>'persona_tipo inválido']); }
if ($persona_id<=0) { json_out(400, ['ok'=>false,'error'=>'persona_id inválido']); }
if ($template_b64==='' || !preg_match('~^[A-Za-z0-9+/=\r\n]+$~', $template_b64)) {
  json_out(400, ['ok'=>false,'error'=>'template_b64 inválido o vacío']);
}

/* ===== Asegurar esquema (auto-migraciones) ===== */
function exec_or_throw(mysqli $db, string $sql): void {
  if (!$db->query($sql)) { throw new Exception($db->error ?: 'Error SQL'); }
}
function col_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table); $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
  return ($r && $r->num_rows>0);
}
function idx_exists(mysqli $db, string $table, string $idx): bool {
  $t = $db->real_escape_string($table); $i = $db->real_escape_string($idx);
  $r = $db->query("SHOW INDEX FROM `$t` WHERE Key_name = '$i'");
  return ($r && $r->num_rows>0);
}
function tbl_exists(mysqli $db, string $table): bool {
  $t = $db->real_escape_string($table);
  $r = $db->query("SHOW TABLES LIKE '$t'");
  return ($r && $r->num_rows>0);
}

if (!tbl_exists($conexion,'huellas')) {
  exec_or_throw($conexion, "
    CREATE TABLE huellas (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      gimnasio_id INT NOT NULL,
      persona_tipo ENUM('profesor','cliente') NOT NULL,
      persona_id INT NOT NULL,
      template_b64 LONGTEXT NOT NULL,
      version VARCHAR(32) DEFAULT NULL,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}
if (!col_exists($conexion,'huellas','template_b64')) {
  exec_or_throw($conexion, "ALTER TABLE huellas ADD COLUMN template_b64 LONGTEXT NOT NULL AFTER persona_id");
}
if (!col_exists($conexion,'huellas','version')) {
  exec_or_throw($conexion, "ALTER TABLE huellas ADD COLUMN version VARCHAR(32) NULL AFTER template_b64");
}
if (!col_exists($conexion,'huellas','created_at')) {
  exec_or_throw($conexion, "ALTER TABLE huellas ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER version");
}
if (!col_exists($conexion,'huellas','updated_at')) {
  exec_or_throw($conexion, "ALTER TABLE huellas ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
}
/* Columna heredada 'template' → volverla NULL para no bloquear INSERTs */
if (col_exists($conexion,'huellas','template')) {
  @$conexion->query("ALTER TABLE huellas MODIFY template LONGBLOB NULL DEFAULT NULL");
}
/* Índices */
if (!idx_exists($conexion,'huellas','uniq_persona')) {
  @$conexion->query("ALTER TABLE huellas DROP INDEX uniq_persona");
  exec_or_throw($conexion, "ALTER TABLE huellas ADD UNIQUE KEY uniq_persona (gimnasio_id, persona_tipo, persona_id)");
}
if (!idx_exists($conexion,'huellas','idx_tipo_id')) {
  exec_or_throw($conexion, "ALTER TABLE huellas ADD KEY idx_tipo_id (persona_tipo, persona_id)");
}

/* ===== (opcional) validar sesión vs gimnasio ===== */
$session_gym = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($session_gym && $session_gym !== $gimnasio_id) {
  json_out(403, ['ok'=>false,'error'=>'Gimnasio de sesión no coincide']);
}

/* ===== UPSERT ===== */
$sql = "
  INSERT INTO huellas (gimnasio_id, persona_tipo, persona_id, template_b64, version)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    template_b64 = VALUES(template_b64),
    version = VALUES(version),
    updated_at = CURRENT_TIMESTAMP
";
$st = $conexion->prepare($sql);
if (!$st) { throw new Exception('No se pudo preparar la consulta: '.$conexion->error); }
$st->bind_param('isiss', $gimnasio_id, $persona_tipo, $persona_id, $template_b64, $version);
if (!$st->execute()) { throw new Exception('Error SQL al guardar huella: '.$st->error); }

/* ===== OK ===== */
json_out(200, [
  'ok' => true,
  'persona_tipo' => $persona_tipo,
  'persona_id' => $persona_id,
  'gimnasio_id' => $gimnasio_id,
  'version' => $version
]);
