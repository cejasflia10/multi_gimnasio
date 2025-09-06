<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>'BD']);
  exit;
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  if ($r = $db->query("SHOW TABLES LIKE '$t'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t' AND COLUMN_NAME = '$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* Params */
$pelea_id = isset($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0){ echo json_encode(['ok'=>false,'msg'=>'pelea_id requerido']); exit; }

/* Rondas esperadas (si existen) */
$rondas = 3;
if (table_exists($conexion,'peleas_evento') && has_col($conexion,'peleas_evento','rondas')) {
  if ($st=$conexion->prepare("SELECT `rondas` FROM `peleas_evento` WHERE `id`=? LIMIT 1")) {
    $st->bind_param('i',$pelea_id);
    $st->execute();
    if ($res=$st->get_result()) {
      if ($row=$res->fetch_assoc()) {
        if ((int)$row['rondas']>0) $rondas = (int)$row['rondas'];
      }
    }
    $st->close();
  }
}

/* ====== JUECES ASIGNADOS ======
   Soporta ambas tablas: pelea_jueces (recomendada) o peleas_jueces (variación del usuario).
   Si ninguna existe o no hay asignaciones, tomamos los jueces que ya cargaron tarjetas. */
$jueces = [];
$asig_tbl = null;
if (table_exists($conexion,'pelea_jueces'))      $asig_tbl = 'pelea_jueces';
elseif (table_exists($conexion,'peleas_jueces')) $asig_tbl = 'peleas_jueces';

if ($asig_tbl) {
  $sql = "SELECT pj.juez_id AS id,
                 TRIM(CONCAT(COALESCE(j.apellido,''),' ',COALESCE(j.nombre,''))) AS nombre
          FROM `$asig_tbl` pj
          LEFT JOIN `jueces_evento` j ON j.id = pj.juez_id
          WHERE pj.pelea_id = ?
          ORDER BY pj.juez_id";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('i',$pelea_id);
    $st->execute();
    $res=$st->get_result();
    while($row=$res->fetch_assoc()){
      $nombre = $row['nombre'];
      if ($nombre===' ' || $nombre==='') $nombre = 'Juez #'.$row['id'];
      $jueces[(int)$row['id']] = ['id'=>(int)$row['id'],'nombre'=>$nombre];
    }
    $st->close();
  }
}

/* ====== FUENTE DE TARJETAS ======
   1) puntajes_round (usa ganador_round directo)
   2) puntuaciones_jueces (deriva ganador comparando azul_puntos vs rojo_puntos) */
$use_pr = table_exists($conexion,'puntajes_round') && has_col($conexion,'puntajes_round','round_num')
          && has_col($conexion,'puntajes_round','juez_id') && has_col($conexion,'puntajes_round','ganador_round');

$use_pj = table_exists($conexion,'puntuaciones_jueces') && has_col($conexion,'puntuaciones_jueces','round')
          && has_col($conexion,'puntuaciones_jueces','juez_id') && has_col($conexion,'puntuaciones_jueces','azul_puntos')
          && has_col($conexion,'puntuaciones_jueces','rojo_puntos');

/* Si no hay asignaciones, poblar jueces desde la fuente de tarjetas */
if (!$jueces) {
  if ($use_pr) {
    $sql = "SELECT DISTINCT pr.juez_id AS id,
                   TRIM(CONCAT(COALESCE(j.apellido,''),' ',COALESCE(j.nombre,''))) AS nombre
            FROM `puntajes_round` pr
            LEFT JOIN `jueces_evento` j ON j.id = pr.juez_id
            WHERE pr.pelea_id = ?
            ORDER BY pr.juez_id";
    if ($st=$conexion->prepare($sql)) {
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $res=$st->get_result();
      while($row=$res->fetch_assoc()){
        $nombre = $row['nombre'];
        if ($nombre===' ' || $nombre==='') $nombre = 'Juez #'.$row['id'];
        $jueces[(int)$row['id']] = ['id'=>(int)$row['id'],'nombre'=>$nombre];
      }
      $st->close();
    }
  } elseif ($use_pj) {
    $sql = "SELECT DISTINCT p.juez_id AS id,
                   TRIM(CONCAT(COALESCE(j.apellido,''),' ',COALESCE(j.nombre,''))) AS nombre
            FROM `puntuaciones_jueces` p
            LEFT JOIN `jueces_evento` j ON j.id = p.juez_id
            WHERE p.pelea_id = ?
            ORDER BY p.juez_id";
    if ($st=$conexion->prepare($sql)) {
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $res=$st->get_result();
      while($row=$res->fetch_assoc()){
        $nombre = $row['nombre'];
        if ($nombre===' ' || $nombre==='') $nombre = 'Juez #'.$row['id'];
        $jueces[(int)$row['id']] = ['id'=>(int)$row['id'],'nombre'=>$nombre];
      }
      $st->close();
    }
  }
}

/* Normalizar jueces a lista ordenada */
$judgeMap = $jueces; // id => {id,nombre}
$jueces = array_values($jueces);
usort($jueces, function($a,$b){ return $a['id'] <=> $b['id']; });

/* ====== Cargar tarjetas por round ====== */
$byRound = [];  // round => [juez_id => {juez_id, nombre, ganador, metodo}]
$maxRound = 0;

if ($use_pr) {
  $sql = "SELECT pr.round_num, pr.juez_id, pr.ganador_round, pr.metodo,
                 TRIM(CONCAT(COALESCE(j.apellido,''),' ',COALESCE(j.nombre,''))) AS nombre
          FROM `puntajes_round` pr
          LEFT JOIN `jueces_evento` j ON j.id = pr.juez_id
          WHERE pr.pelea_id = ?
          ORDER BY pr.round_num ASC, pr.juez_id ASC";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('i',$pelea_id);
    $st->execute();
    $res=$st->get_result();
    while($r=$res->fetch_assoc()){
      $rn = (int)$r['round_num'];
      $jid = (int)$r['juez_id'];
      if (!isset($byRound[$rn])) $byRound[$rn] = [];
      $nombre = $r['nombre']; if ($nombre===' ' || $nombre==='') $nombre = isset($judgeMap[$jid]) ? $judgeMap[$jid]['nombre'] : ('Juez #'.$jid);
      $gan    = $r['ganador_round']; // 'rojo','azul','empate'
      $met    = $r['metodo'];
      $byRound[$rn][$jid] = ['juez_id'=>$jid,'nombre'=>$nombre,'ganador'=>$gan,'metodo'=>$met];
      if ($rn>$maxRound) $maxRound=$rn;
    }
    $st->close();
  }
} elseif ($use_pj) {
  // Derivar ganador comparando puntos
  $sql = "SELECT p.`round`, p.`juez_id`, p.`azul_puntos`, p.`rojo_puntos`,
                 TRIM(CONCAT(COALESCE(j.apellido,''),' ',COALESCE(j.nombre,''))) AS nombre
          FROM `puntuaciones_jueces` p
          LEFT JOIN `jueces_evento` j ON j.id = p.juez_id
          WHERE p.pelea_id = ?
          ORDER BY p.`round` ASC, p.juez_id ASC";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('i',$pelea_id);
    $st->execute();
    $res=$st->get_result();
    while($r=$res->fetch_assoc()){
      $rn = (int)$r['round'];
      $jid= (int)$r['juez_id'];
      if (!isset($byRound[$rn])) $byRound[$rn] = [];
      $nombre = $r['nombre']; if ($nombre===' ' || $nombre==='') $nombre = isset($judgeMap[$jid]) ? $judgeMap[$jid]['nombre'] : ('Juez #'.$jid);
      $gan = null;
      if (is_numeric($r['azul_puntos']) && is_numeric($r['rojo_puntos'])) {
        $az = (int)$r['azul_puntos']; $ro = (int)$r['rojo_puntos'];
        if     ($az > $ro) $gan='azul';
        elseif ($ro > $az) $gan='rojo';
        else               $gan='empate';
      }
      $byRound[$rn][$jid] = ['juez_id'=>$jid,'nombre'=>$nombre,'ganador'=>$gan,'metodo'=>null];
      if ($rn>$maxRound) $maxRound=$rn;
    }
    $st->close();
  }
}

/* Si no hay ninguna fuente válida, devolver vacío pero OK */
if (!$use_pr && !$use_pj) {
  echo json_encode(['ok'=>true,'jueces'=>$jueces,'rounds'=>[],'proyeccion'=>'En curso…'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ====== Conteos por juez y panel ====== */
$perJudgeWins = []; // juez_id => ['rojo'=>X,'azul'=>Y,'empate'=>Z]
foreach ($jueces as $j) { $perJudgeWins[$j['id']] = ['rojo'=>0,'azul'=>0,'empate'=>0]; }

foreach ($byRound as $rn => $row) {
  foreach ($row as $jid => $cell) {
    $g = $cell['ganador'];
    if ($g === 'rojo' || $g === 'azul' || $g === 'empate') {
      $perJudgeWins[$jid][$g]++;
    }
  }
}

/* Voto por juez (quién va ganando por MÁS rounds para ese juez) */
$panelVotes = ['rojo'=>0,'azul'=>0,'empate'=>0];
foreach ($perJudgeWins as $jid => $w) {
  if ($w['rojo'] > $w['azul'])      $panelVotes['rojo']++;
  elseif ($w['azul'] > $w['rojo'])  $panelVotes['azul']++;
  else                              $panelVotes['empate']++; // igualados o sólo empates
}

/* Proyección (etiquetas amigables para 3 jueces; genéricas para N jueces) */
function decisionLabel(array $v): string {
  $total = (int)($v['rojo'] + $v['azul'] + $v['empate']);
  if ($total === 0) return 'En curso…';

  // Casos clásicos de 3 jueces
  if ($total === 3) {
    if ($v['rojo']===3) return 'Decisión unánime (3–0) ROJO';
    if ($v['azul']===3) return 'Decisión unánime (3–0) AZUL';
    if ($v['rojo']===2 && $v['azul']===1) return 'Decisión dividida (2–1) ROJO';
    if ($v['azul']===2 && $v['rojo']===1) return 'Decisión dividida (2–1) AZUL';
    if ($v['rojo']===2 && $v['empate']===1) return 'Decisión mayoritaria (2–0–1) ROJO';
    if ($v['azul']===2 && $v['empate']===1) return 'Decisión mayoritaria (2–0–1) AZUL';
    if ($v['empate']===3) return 'Empate (0–0–3)';
    if ($v['rojo']===1 && $v['azul']===1 && $v['empate']===1) return 'Empate (1–1–1)';
  }

  // Genérico para N jueces
  $lead = 'empate';
  if ($v['rojo'] > $v['azul'] && $v['rojo'] >= $v['empate']) $lead = 'ROJO';
  elseif ($v['azul'] > $v['rojo'] && $v['azul'] >= $v['empate']) $lead = 'AZUL';
  else $lead = 'Empate';

  return 'Fallo parcial: lidera ' . $lead . " ({$v['rojo']}-{$v['azul']}-{$v['empate']})";
}
$proyeccion = decisionLabel($panelVotes);

/* ====== Salida por round, asegurando filas hasta max(round_detectado, rondas_esperadas) ====== */
ksort($byRound);
$judgeIdsOrdered = array_map(fn($j)=>$j['id'], $jueces);

$outRounds = [];
$to = max($maxRound, $rondas);
for ($rn=1; $rn<=$to; $rn++) {
  $fila = [];
  foreach ($judgeIdsOrdered as $jid) {
    if (isset($byRound[$rn][$jid])) {
      $fila[] = $byRound[$rn][$jid];
    } else {
      $nombre = isset($judgeMap[$jid]) ? $judgeMap[$jid]['nombre'] : ('Juez #'.$jid);
      $fila[] = ['juez_id'=>$jid, 'nombre'=>$nombre, 'ganador'=>null, 'metodo'=>null];
    }
  }
  $outRounds[] = ['round'=>$rn, 'judges'=>$fila];
}

/* ====== Respuesta ====== */
echo json_encode([
  'ok'        => true,
  'jueces'    => $jueces,      // [{id,nombre}, ...]
  'rounds'    => $outRounds,   // [{round, judges:[{juez_id,nombre,ganador,metodo}, ...]}, ...]
  'proyeccion'=> $proyeccion
], JSON_UNESCAPED_UNICODE);
