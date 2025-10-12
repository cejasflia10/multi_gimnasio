<?php
/* ============================================================
   guardar_competidor_evento.php — robusto (DNI canónico, upsert flexible)
   - Prioriza (evento_id + DNI válido 8 dígitos)
   - Fallback por (evento_id + apellido+nombre normalizados)
   - Preserva DNI canónico si ya existe
   - Graba evento_usuario_id si la columna existe
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500); exit('❌ No hay conexión a la base de datos.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ---------- Helpers ---------- */
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v === '' || !is_numeric($v)) ? null : (int)$v; }
function toDecOrNull($v){
  if ($v === '') return null;
  $v = str_replace(',', '.', $v);
  return is_numeric($v) ? (string)$v : null;
}
function col_exists(mysqli $db, string $table, string $col): bool {
  $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1";
  $st = $db->prepare($sql); if(!$st) return false;
  $st->bind_param('ss', $table, $col);
  $st->execute(); $r = $st->get_result();
  $ok = $r && $r->num_rows>0; $st->close();
  return $ok;
}
function normalize_dni(string $raw): string {
  $digits = preg_replace('/\D+/', '', $raw ?? '');
  return (strlen($digits) === 8) ? $digits : '';
}
function bind_params_ref(mysqli_stmt $stmt, string $types, array &$vars): bool {
  $refs = [];
  foreach ($vars as $k => &$v) { $refs[$k] = &$v; }
  array_unshift($refs, $types);
  return call_user_func_array([$stmt, 'bind_param'], $refs);
}

/* ---------- Datos de sesión ---------- */
$evento_id = (int)($_SESSION['evento_id_actual'] ?? 0);
if ($evento_id <= 0) $evento_id = (int)post('evento_id');
if ($evento_id <= 0) { http_response_code(400); exit('❌ Falta evento_id.'); }

$evento_usuario_id = (int)($_SESSION['evento_usuario_id'] ?? 0); // organizador si está
$has_evuser_col = col_exists($conexion, 'competidores_evento', 'evento_usuario_id');

/* ---------- Campos (Nombre obligatorio; DNI opcional) ---------- */
$nombre = post('nombre');
$apellido = post('apellido');
$dni_raw  = post('dni');
$dni_norm = normalize_dni($dni_raw); // '' si no es 8 dígitos

/* opcionales *_id (permitir NULL) */
$disciplina_id        = toIntOrNull(post('disciplina_id'));
$modalidad_id         = toIntOrNull(post('modalidad_id'));
$categoria_peso_id    = toIntOrNull(post('categoria_peso_id'));
$division_id          = toIntOrNull(post('division_id'));
$categoria_tecnica_id = toIntOrNull(post('categoria_tecnica_id'));

/* otros opcionales */
$fecha_nacimiento  = post('fecha_nacimiento'); // YYYY-MM-DD
$edad              = toIntOrNull(post('edad'));
$domicilio         = post('domicilio');
$localidad         = post('localidad');
$foto_competidor   = post('foto_competidor');
$escuela_nombre    = post('escuela_nombre');
$escuela_logo      = post('escuela_logo');
$pago_inscripcion  = toDecOrNull(post('pago_inscripcion'));
$modalidades       = post('modalidades');
$categoria_tecnica = post('categoria_tecnica');   // VARCHAR(2)
$modalidad_txt     = post('modalidad');          // VARCHAR(100)
$division_txt      = post('division');           // VARCHAR(50)
$escuela           = post('escuela');

/* ---------- Validación básica ---------- */
$errores = [];
if ($nombre === '')   $errores[] = 'Nombre obligatorio.';
if ($apellido === '') $errores[] = 'Apellido obligatorio.';
if ($fecha_nacimiento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
  $errores[] = 'fecha_nacimiento debe ser YYYY-MM-DD.';
}
if ($errores) { http_response_code(422); exit('❌ '.implode(' — ', $errores)); }

/* ---------- Normalizar opcionales a strings o null ---------- */
$disciplina_id_s        = isset($disciplina_id) ? (string)$disciplina_id : null;
$modalidad_id_s         = isset($modalidad_id) ? (string)$modalidad_id : null;
$categoria_peso_id_s    = isset($categoria_peso_id) ? (string)$categoria_peso_id : null;
$division_id_s          = isset($division_id) ? (string)$division_id : null;
$categoria_tecnica_id_s = isset($categoria_tecnica_id) ? (string)$categoria_tecnica_id : null;

$apellido_v         = ($apellido !== '') ? $apellido : null;
$fecha_nacimiento_v = ($fecha_nacimiento !== '') ? $fecha_nacimiento : null;
$edad_v             = isset($edad) ? (string)$edad : null;
$domicilio_v        = ($domicilio !== '') ? $domicilio : null;
$localidad_v        = ($localidad !== '') ? $localidad : null;
$foto_competidor_v  = ($foto_competidor !== '') ? $foto_competidor : null;
$escuela_nombre_v   = ($escuela_nombre !== '') ? $escuela_nombre : null;
$escuela_logo_v     = ($escuela_logo !== '') ? $escuela_logo : null;
$pago_v             = isset($pago_inscripcion) ? (string)$pago_inscripcion : null;
$modalidades_v      = ($modalidades !== '') ? $modalidades : null;
$categoria_tecnica_v= ($categoria_tecnica !== '') ? $categoria_tecnica : null;
$modalidad_txt_v    = ($modalidad_txt !== '') ? $modalidad_txt : null;
$division_txt_v     = ($division_txt !== '') ? $division_txt : null;
$escuela_v          = ($escuela !== '') ? $escuela : null;

/* ---------- Buscar existente
   1) Si DNI válido => por (evento_id, dni)
   2) Si no => por (evento_id, LOWER(TRIM(apellido)), LOWER(TRIM(nombre))) último ID
   ---------------------------------------------- */
$existente_id = 0;
$existe_row   = null;

if ($dni_norm !== '') {
  $dup_sql = "SELECT id, dni FROM competidores_evento WHERE evento_id = ? AND dni = ? LIMIT 1";
  $st = $conexion->prepare($dup_sql);
  if (!$st) { http_response_code(500); exit('❌ SQL dup: '.$conexion->error); }
  $st->bind_param('is', $evento_id, $dni_norm);
  $st->execute();
  $res = $st->get_result();
  if ($res && $res->num_rows) { $existe_row = $res->fetch_assoc(); $existente_id = (int)$existe_row['id']; }
  $st->close();
}

if ($existente_id === 0) {
  $sql = "SELECT id, dni
          FROM competidores_evento
          WHERE evento_id = ?
            AND LOWER(TRIM(apellido)) = LOWER(TRIM(?))
            AND LOWER(TRIM(nombre))   = LOWER(TRIM(?))
          ORDER BY id DESC
          LIMIT 1";
  $st = $conexion->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ SQL search: '.$conexion->error); }
  $st->bind_param('iss', $evento_id, $apellido, $nombre);
  $st->execute();
  $res = $st->get_result();
  if ($res && $res->num_rows) { $existe_row = $res->fetch_assoc(); $existente_id = (int)$existe_row['id']; }
  $st->close();
}

/* ============================================================
   UPSERT con preservación de DNI canónico
   ============================================================ */
if ($existente_id > 0) {
  // Cargar DNI actual para decidir si se preserva
  $dni_actual = (string)($existe_row['dni'] ?? '');
  $dni_actual_norm = normalize_dni($dni_actual);
  $dni_update = null; // null => NO tocar DNI

  // Si no hay DNI actual y entra uno válido, asignarlo si está libre en este evento
  if ($dni_actual_norm === '' && $dni_norm !== '') {
    $st = $conexion->prepare("SELECT id FROM competidores_evento WHERE evento_id = ? AND dni = ? AND id <> ? LIMIT 1");
    $st->bind_param('isi', $evento_id, $dni_norm, $existente_id);
    $st->execute();
    $ya = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$ya) { $dni_update = $dni_norm; }
  }

  // ---------- UPDATE ----------
  $sets = [
    "nombre = ?",
    "disciplina_id = ?","modalidad_id = ?","categoria_peso_id = ?",
    "division_id = ?","categoria_tecnica_id = ?",
    "apellido = ?","fecha_nacimiento = ?","edad = ?",
    "domicilio = ?","localidad = ?","foto_competidor = ?",
    "escuela_nombre = ?","escuela_logo = ?","pago_inscripcion = ?",
    "modalidades = ?","categoria_tecnica = ?","modalidad = ?",
    "division = ?","escuela = ?"
  ];
  $params = [
    $nombre,
    $disciplina_id_s, $modalidad_id_s, $categoria_peso_id_s,
    $division_id_s, $categoria_tecnica_id_s,
    $apellido_v, $fecha_nacimiento_v, $edad_v,
    $domicilio_v, $localidad_v, $foto_competidor_v,
    $escuela_nombre_v, $escuela_logo_v, $pago_v,
    $modalidades_v, $categoria_tecnica_v, $modalidad_txt_v,
    $division_txt_v, $escuela_v
  ];
  $types = str_repeat('s', count($params)); // todo como string/null

  // incluir DNI solo si corresponde actualizarlo
  if ($dni_update !== null) {
    array_unshift($sets, "dni = ?");
    array_unshift($params, $dni_update);
    $types = 's'.$types;
  }

  if ($has_evuser_col) {
    $sets[] = "evento_usuario_id = ?";
    $params[] = ($evento_usuario_id > 0) ? (string)$evento_usuario_id : null;
    $types .= 's';
  }

  $params[] = $existente_id;
  $types   .= 'i';

  $sql = "UPDATE competidores_evento SET ".implode(', ', $sets)." WHERE id = ? LIMIT 1";
  $st = $conexion->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ SQL update: '.$conexion->error); }
  if (!bind_params_ref($st, $types, $params)) { http_response_code(500); exit('❌ bind(update)'); }
  if (!$st->execute()) { http_response_code(500); exit('❌ exec(update): '.$st->error); }
  $st->close();

} else {
  // ---------- INSERT ----------
  // si entra DNI válido y libre en el evento → se usa; sino NULL
  $dni_insert = null;
  if ($dni_norm !== '') {
    $st = $conexion->prepare("SELECT id FROM competidores_evento WHERE evento_id = ? AND dni = ? LIMIT 1");
    $st->bind_param('is', $evento_id, $dni_norm);
    $st->execute();
    $dup = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$dup) $dni_insert = $dni_norm;
  }

  $cols = [
    'evento_id','nombre','apellido','dni',
    'disciplina_id','modalidad_id','categoria_peso_id',
    'division_id','categoria_tecnica_id',
    'fecha_nacimiento','edad',
    'domicilio','localidad','foto_competidor',
    'escuela_nombre','escuela_logo','pago_inscripcion',
    'modalidades','categoria_tecnica','modalidad','division','escuela'
  ];
  $params = [
    $evento_id, $nombre, $apellido, $dni_insert,
    $disciplina_id_s, $modalidad_id_s, $categoria_peso_id_s,
    $division_id_s, $categoria_tecnica_id_s,
    $fecha_nacimiento_v, $edad_v,
    $domicilio_v, $localidad_v, $foto_competidor_v,
    $escuela_nombre_v, $escuela_logo_v, $pago_v,
    $modalidades_v, $categoria_tecnica_v, $modalidad_txt_v, $division_txt_v, $escuela_v
  ];
  $types = 'isss' . str_repeat('s', count($params)-4); // evento_id=int, 3 strings, resto string/null

  if ($has_evuser_col) {
    $cols[] = 'evento_usuario_id';
    $params[] = ($evento_usuario_id > 0) ? (string)$evento_usuario_id : null;
    $types .= 's';
  }

  $place = implode(',', array_fill(0, count($cols), '?'));
  $sql = "INSERT INTO competidores_evento (".implode(',', $cols).") VALUES ($place)";
  $st = $conexion->prepare($sql);
  if (!$st) { http_response_code(500); exit('❌ SQL insert: '.$conexion->error); }
  if (!bind_params_ref($st, $types, $params)) { http_response_code(500); exit('❌ bind(insert)'); }
  if (!$st->execute()) {
    if ($conexion->errno === 1062) exit('⚠️ Ese DNI ya está cargado para este evento.');
    http_response_code(500); exit('❌ exec(insert): '.$st->error);
  }
  $st->close();
}

/* ---------- Volver al listado ---------- */
header('Location: ver_competidores_evento.php?evento_id='.(int)$evento_id);
exit;
