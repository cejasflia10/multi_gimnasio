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

/* Jueces con actividad */
$jueces_ids = [];
if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM `puntuaciones_jueces` WHERE pelea_id=? ORDER BY juez_id")) {
  $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($jid);
  while($st->fetch()){ $jueces_ids[]=(int)$jid; }
  $st->close();
}

/* Nombres desde jueces (si existe) */
$jueces = [];
if (!empty($jueces_ids) && has_table($conexion,'jueces') && has_col($conexion,'jueces','id')) {
  $hasApe = has_col($conexion,'jueces','apellido');
  $hasNom = has_col($conexion,'jueces','nombre');
  $in = implode(',', array_map('intval', $jueces_ids));
  $cols = 'id'.($hasApe?',apellido':'').($hasNom?',nombre':'');
  $q = $conexion->query("SELECT $cols FROM `jueces` WHERE id IN ($in)");
  $map = [];
  if ($q){ while($row=$q->fetch_assoc()){ $map[(int)$row['id']]=$row; } $q->close(); }
  foreach($jueces_ids as $id){
    $row = $map[$id] ?? null;
    $jueces[] = ['id'=>$id, 'apellido'=>$row['apellido']??null, 'nombre'=>$row['nombre']??null];
  }
} else {
  foreach($jueces_ids as $id){ $jueces[]=['id'=>$id]; }
}

/* Round x juez */
$rounds = []; // [round][juez_id] => datos
if ($st=$conexion->prepare("SELECT `round`, juez_id, azul_puntos, rojo_puntos FROM `puntuaciones_jueces` WHERE pelea_id=? ORDER BY `round`, juez_id")) {
  $st->bind_param('i',$pelea_id); $st->execute();
  $st->bind_result($rnd,$jid,$ap,$rp);
  while($st->fetch()){
    $gan = ($ap>$rp?'azul':($rp>$ap?'rojo':'empate'));
    if(!isset($rounds[$rnd])) $rounds[$rnd]=[];
    $rounds[$rnd][$jid] = [
      'juez_id'=>(int)$jid,
      'azul_puntos'=>(int)$ap,
      'rojo_puntos'=>(int)$rp,
      'ganador'=>$gan,
      'metodo'=>null
    ];
  }
  $st->close();
}

/* Estructura de salida */
$outRounds = [];
if (!empty($rounds)) {
  ksort($rounds);
  $order = $jueces_ids;
  if (empty($order)) {
    $first = array_key_first($rounds);
    $order = array_keys($rounds[$first]); sort($order, SORT_NUMERIC);
  }
  foreach($rounds as $rnum=>$byJudge){
    $row = ['round'=>(int)$rnum, 'judges'=>[]];
    foreach($order as $jid){
      $row['judges'][] = $byJudge[$jid] ?? ['juez_id'=>$jid,'ganador'=>null,'metodo'=>null];
    }
    $outRounds[] = $row;
  }
}

/* Proyección simple */
$totR=0; $totA=0;
foreach ($outRounds as $r) {
  $rR=0; $rA=0;
  foreach ($r['judges'] as $j) {
    if (($j['ganador']??null)==='rojo') $rR++;
    elseif (($j['ganador']??null)==='azul') $rA++;
  }
  $totR += $rR; $totA += $rA;
}
$proy = null;
if ($totR || $totA) $proy = ($totR>$totA ? "Rojo adelante ($totR-$totA)" : ($totA>$totR ? "Azul adelante ($totA-$totR)" : "Empate ($totA-$totR)"));

echo json_encode(['ok'=>true,'jueces'=>$jueces,'rounds'=>$outRounds,'proyeccion'=>$proy], JSON_UNESCAPED_UNICODE);
