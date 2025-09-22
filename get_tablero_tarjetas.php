<?php
// get_tablero_tarjetas.php — fuente del tablero en combate_en_vivo
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'pelea_id']); exit; }

/* ===== Resolver jueces habilitados/forzados ===== */
$habil = [];
if (!empty($_GET['jueces'])) {
  $forced = array_values(array_unique(array_filter(array_map(function($x){ $v=(int)trim($x); return $v>0?$v:null; }, explode(',', $_GET['jueces'])))));
  if (count($forced)===3) { $habil = $forced; }
}

if (count($habil)!==3) {
  // 1) jueces_por_pelea (habilitado=1)
  $haveJPP = ($rs=$conexion->query("SHOW TABLES LIKE 'jueces_por_pelea'")) && $rs->num_rows>0; if(isset($rs)&&$rs) $rs->close();
  if ($haveJPP){
    if ($st=$conexion->prepare("SELECT juez_id FROM jueces_por_pelea WHERE pelea_id=? AND habilitado=1 ORDER BY juez_id")){
      $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result();
      $tmp=[]; while($row=$r->fetch_assoc()) $tmp[]=(int)$row['juez_id']; $st->close();
      if (count($tmp)===3) $habil=$tmp;
    }
  }
}
if (count($habil)!==3) {
  // 2) primeros 3 que hayan puntuado
  if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM puntuaciones_jueces WHERE pelea_id=? ORDER BY juez_id LIMIT 3")){
    $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result();
    $tmp=[]; while($row=$r->fetch_assoc()) $tmp[]=(int)$row['juez_id']; $st->close();
    $habil = $tmp;
  }
}

// completar hasta 3 con '—' (placeholder) para mantener 3 columnas
$order = $habil;
while (count($order)<3) $order[] = '—';

/* ===== Nombres de jueces (opcional) ===== */
$names = [];
if (!empty($habil)){
  $in = implode(',', array_fill(0, count($habil), '?'));
  // Busco en jueces_evento si existe
  $haveJE = ($rs=$conexion->query("SHOW TABLES LIKE 'jueces_evento'")) && $rs->num_rows>0; if(isset($rs)&&$rs) $rs->close();
  if ($haveJE){
    $types = str_repeat('i', count($habil));
    $sql = "SELECT id, COALESCE(CONCAT(TRIM(apellido),' ',TRIM(nombre)), TRIM(nombre), CONCAT('Juez ',id)) AS nombre FROM jueces_evento WHERE id IN ($in)";
    if ($st = $conexion->prepare($sql)){
      $st->bind_param($types, ...$habil);
      $st->execute(); $res=$st->get_result();
      while($row=$res->fetch_assoc()){ $names[(int)$row['id']] = $row['nombre']; }
      $st->close();
    }
  }
}

/* ===== Traer puntajes ===== */
$rows = [];
if ($st=$conexion->prepare("SELECT `round` AS round, juez_id, azul_puntos, rojo_puntos
                            FROM puntuaciones_jueces
                            WHERE pelea_id=?
                            ORDER BY round ASC, juez_id ASC")){
  $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result();
  while($rw=$r->fetch_assoc()){ $rows[]=$rw; }
  $st->close();
}

/* ===== Indexar por round (filtrando si hay 3 habilitados) ===== */
$by = []; $hasAny = false;
if (!empty($rows)) {
  foreach($rows as $rw){
    if (!empty($habil) && count($habil)===3 && !in_array((int)$rw['juez_id'], $habil, true)) continue;
    $rn = (int)$rw['round'];
    $by[$rn][] = [
      'juez_id'     => (int)$rw['juez_id'],
      'azul_puntos' => (int)$rw['azul_puntos'],
      'rojo_puntos' => (int)$rw['rojo_puntos']
    ];
    $hasAny = true;
  }
}
ksort($by);

/* ===== Construir HTML del tablero ===== */
$html  = '<thead><tr><th>Round</th>';
foreach($order as $id){
  if ($id==='—'){ $html .= '<th>— — Juez —</th>'; }
  else {
    $label = isset($names[$id]) ? ('ID '.$id.' — '.htmlspecialchars($names[$id], ENT_QUOTES, 'UTF-8')) : ('ID '.$id);
    $html .= '<th>'.$label.'</th>';
  }
}
$html .= '<th>Rojo (Rds)</th><th>Azul (Rds)</th><th>Σ Rojo Pts</th><th>Σ Azul Pts</th></tr></thead><tbody>';

$sumR=0; $sumA=0; $TR=0; $TA=0; // tot rounds y puntos acumulados
$rnums = array_keys($by);
if (!$rnums) $rnums = [1];

$idx = [];
foreach($by as $rn=>$list){ $m=[]; foreach($list as $j){ $m[(string)$j['juez_id']]=$j; } $idx[$rn]=$m; }

foreach($rnums as $rn){
  $m = $idx[$rn] ?? [];
  $rR=0;$rA=0;$pR=0;$pA=0;
  $html .= '<tr><td>'.$rn.'</td>';
  foreach($order as $id){
    if ($id==='—'){ $html.='<td class="pending">—</td>'; continue; }
    $j = $m[(string)$id] ?? null;
    if (!$j){ $html.='<td class="pending">—</td>'; continue; }
    $a = (int)$j['azul_puntos'];
    $ro= (int)$j['rojo_puntos'];
    $g = ($a>$ro?'azul':($ro>$a?'rojo':'empate'));
    if($g==='rojo') $rR++; if($g==='azul') $rA++;
    $pR+=$ro; $pA+=$a;
    $ico = ($g==='rojo'?'🔴':($g==='azul'?'🔵':'⚖️'));
    $html .= '<td>'.$ico.'<div class="sub" style="opacity:.85">'.($a).'–'.($ro).'</div></td>';
  }
  $sumR+=$rR; $sumA+=$rA; $TR+=$pR; $TA+=$pA;
  $html .= '<td><b>'.$rR.'</b></td><td><b>'.$rA.'</b></td><td><b>'.$TR.'</b></td><td><b>'.$TA.'</b></td></tr>';
}
$html .= '<tr><td><b>Σ</b></td><td></td><td></td><td></td><td class="winR">'.$sumR.'</td><td class="winA">'.$sumA.'</td><td class="winR">'.$TR.'</td><td class="winA">'.$TA.'</td></tr>';
$html .= '</tbody>';

/* ===== Detalle por juez ===== */
$detalleMap=[]; $detOut=[];
foreach($rows as $rw){
  if (!empty($habil) && count($habil)===3 && !in_array((int)$rw['juez_id'], $habil, true)) continue;
  $jid=(int)$rw['juez_id'];
  if(!isset($detalleMap[$jid])) $detalleMap[$jid]=['juez_id'=>$jid,'nombre'=>($names[$jid]??null),'rows'=>[],'tot_az'=>0,'tot_ro'=>0];
  $detalleMap[$jid]['rows'][]=['round'=>(int)$rw['round'],'azul'=>(int)$rw['azul_puntos'],'rojo'=>(int)$rw['rojo_puntos']];
  $detalleMap[$jid]['tot_az']+=(int)$rw['azul_puntos']; $detalleMap[$jid]['tot_ro']+=(int)$rw['rojo_puntos'];
}
ksort($detalleMap);
foreach($detalleMap as $card){ $detOut[]=$card; }

$gw = ($TR>$TA?'rojo':($TA>$TR?'azul':'empate'));
$banner = ($gw==='rojo')
  ? ('🔴 <b>Ganador por puntos totales: RINCÓN ROJO</b> — Rojo '.$TR.' / Azul '.$TA)
  : (($gw==='azul') ? ('🔵 <b>Ganador por puntos totales: RINCÓN AZUL</b> — Azul '.$TA.' / Rojo '.$TR)
                    : ('⚖️ <b>Empate por puntos totales</b> — Rojo '.$TR.' / Azul '.$TA));

echo json_encode([
  'ok'           => true,
  'pelea_id'     => $pelea_id,
  'jueces'       => array_map(fn($id)=>['id'=>$id,'nombre'=>$names[$id]??null], array_filter($order, fn($x)=>$x!=='—')),
  'habilitados'  => array_values(array_filter($order, fn($x)=>$x!=='—')),
  'html_tabla'   => $html,
  'sum_rojo'     => $TR,
  'sum_azul'     => $TA,
  'ganador'      => $gw,
  'banner'       => $banner,
  'detalle'      => $detOut,
]);
