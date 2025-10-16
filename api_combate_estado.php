<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('{"ok":false,"msg":"sin_bd"}'); }
@$conexion->set_charset('utf8mb4');

function json_out($arr){
  header_remove('Set-Cookie');
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit;
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql); $ok=$r&&$r->num_rows>0; if($r)$r->close(); return $ok;
}

// Asegurar tabla y columnas del timer
$conexion->query("
CREATE TABLE IF NOT EXISTS combate_estado (
  id INT AUTO_INCREMENT PRIMARY KEY,
  evento_id INT NOT NULL UNIQUE,
  pelea_actual_id INT DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 0,
  -- ===== Timer =====
  ronda_actual INT DEFAULT NULL,
  en_descanso TINYINT(1) DEFAULT NULL,
  epoch_inicio INT DEFAULT NULL,      -- timestamp UNIX del inicio de la fase actual (round o descanso)
  dur_round INT DEFAULT NULL,         -- segundos totales del round
  dur_descanso INT DEFAULT NULL,      -- segundos totales del descanso
  actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// GET = leer estado
if ($_SERVER['REQUEST_METHOD']==='GET') {
  $evento_id = isset($_GET['evento_id']) ? (int)$_GET['evento_id'] : 0;
  if ($evento_id<=0) json_out(['ok'=>false,'msg'=>'evento_invalido']);

  $st = $conexion->prepare("SELECT pelea_actual_id, activo, ronda_actual, en_descanso, epoch_inicio, dur_round, dur_descanso, UNIX_TIMESTAMP(actualizado_en) AS ts FROM combate_estado WHERE evento_id=? LIMIT 1");
  $st->bind_param('i', $evento_id);
  $st->execute();
  $r = $st->get_result();
  $row = $r ? $r->fetch_assoc() : null;
  $st->close();

  json_out(['ok'=>true, 'evento_id'=>$evento_id] + ($row ?: []));
}

// POST = publicar (timer / pelea actual)
$evento_id = isset($_POST['evento_id']) ? (int)$_POST['evento_id'] : 0;
if ($evento_id<=0) json_out(['ok'=>false,'msg'=>'evento_invalido']);

$pelea_actual_id = isset($_POST['pelea_actual_id']) ? (int)$_POST['pelea_actual_id'] : null;
$activo         = isset($_POST['activo']) ? (int)$_POST['activo'] : null;
$ronda_actual   = isset($_POST['ronda_actual']) ? (int)$_POST['ronda_actual'] : null;
$en_descanso    = isset($_POST['en_descanso']) ? (int)$_POST['en_descanso'] : null;
$epoch_inicio   = isset($_POST['epoch_inicio']) ? (int)$_POST['epoch_inicio'] : null;
$dur_round      = isset($_POST['dur_round']) ? (int)$_POST['dur_round'] : null;
$dur_descanso   = isset($_POST['dur_descanso']) ? (int)$_POST['dur_descanso'] : null;

$cols = []; $vals = []; $types=''; $bind=[];
if (!is_null($pelea_actual_id)) { $cols[]='pelea_actual_id=?'; $types.='i'; $bind[]=$pelea_actual_id; }
if (!is_null($activo))          { $cols[]='activo=?';          $types.='i'; $bind[]=$activo; }
if (!is_null($ronda_actual))    { $cols[]='ronda_actual=?';    $types.='i'; $bind[]=$ronda_actual; }
if (!is_null($en_descanso))     { $cols[]='en_descanso=?';     $types.='i'; $bind[]=$en_descanso; }
if (!is_null($epoch_inicio))    { $cols[]='epoch_inicio=?';    $types.='i'; $bind[]=$epoch_inicio; }
if (!is_null($dur_round))       { $cols[]='dur_round=?';       $types.='i'; $bind[]=$dur_round; }
if (!is_null($dur_descanso))    { $cols[]='dur_descanso=?';    $types.='i'; $bind[]=$dur_descanso; }

if (!$cols) json_out(['ok'=>false,'msg'=>'sin_cambios']);

$sql = "INSERT INTO combate_estado (evento_id) VALUES (?) ON DUPLICATE KEY UPDATE actualizado_en=CURRENT_TIMESTAMP";
$st = $conexion->prepare($sql);
$st->bind_param('i',$evento_id);
$st->execute(); $st->close();

$sql = "UPDATE combate_estado SET ".implode(', ',$cols)." , actualizado_en=CURRENT_TIMESTAMP WHERE evento_id=? LIMIT 1";
$st = $conexion->prepare($sql);
$types .= 'i'; $bind[] = $evento_id;
$st->bind_param($types, ...$bind);
$ok = $st->execute(); $st->close();

json_out(['ok'=>$ok?true:false]);
