<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'Sin conexión a BD.']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0; if($q) $q->close(); return $ok;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql); $ok = $r && $r->num_rows>0; if($r) $r->close(); return $ok;
}

$pelea_id = isset($_GET['pelea_id']) && ctype_digit($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0) { echo json_encode(['ok'=>false,'error'=>'pelea_id inválido']); exit; }

$jids = [];

/* 1) Por asignaciones si existieran */
$asigTbl = null;
foreach (['asignaciones_jueces','jueces_pelea','asignacion_jueces'] as $t) {
  if (has_table($conexion,$t)) { $asigTbl=$t; break; }
}
if ($asigTbl && has_col($conexion,$asigTbl,'pelea_id') && has_col($conexion,$asigTbl,'juez_id')) {
  if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM `$asigTbl` WHERE pelea_id=?")) {
    $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($jid);
    while($st->fetch()){ $jids[(int)$jid]=1; } $st->close();
  }
}

/* 2) Cualquiera que haya puntuado */
if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM `puntuaciones_jueces` WHERE pelea_id=?")) {
  $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($jid);
  while($st->fetch()){ $jids[(int)$jid]=1; } $st->close();
}

$jueces = [];
$ids = array_keys($jids); sort($ids);

/* 3) Intentar nombres desde jueces */
if (!empty($ids) && has_table($conexion,'jueces') && has_col($conexion,'jueces','id')) {
  $hasApe = has_col($conexion,'jueces','apellido');
  $hasNom = has_col($conexion,'jueces','nombre');
  $in = implode(',', array_map('intval',$ids)); // seguro (ids ya int)
  $cols = 'id'.($hasApe?',apellido':'').($hasNom?',nombre':'');
  $q = $conexion->query("SELECT $cols FROM `jueces` WHERE id IN ($in)");
  $map = [];
  if ($q){ while($row=$q->fetch_assoc()){ $map[(int)$row['id']]=$row; } $q->close(); }
  foreach ($ids as $id) {
    $row = $map[$id] ?? null;
    $jueces[] = ['id'=>$id, 'apellido'=>$row['apellido']??null, 'nombre'=>$row['nombre']??null];
  }
} else {
  foreach ($ids as $id) $jueces[] = ['id'=>$id];
}

echo json_encode(['ok'=>true,'jueces'=>$jueces], JSON_UNESCAPED_UNICODE);
