<?php
// get_tablero_flexible.php — Tablero dinámico con N jueces (>=3). Lee DIRECTO de `puntuaciones_jueces`
//
// Prioridad para la lista de jueces (orden de columnas):
//  1) GET jueces=15,16,14   (opcional, para forzar/ordenar)
//  2) jueces_por_pelea.habilitado=1 (si existe la tabla)
//  3) DISTINCT juez_id de `puntuaciones_jueces` de esta pelea
//
// Salida JSON: { ok, pelea_id, jueces:[...], html_tabla, sum_rojo, sum_azul, ganador, banner, detalle:[...] }

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($conexion) || !($conexion instanceof mysqli)) { echo json_encode(['ok'=>false,'error'=>'BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'pelea_id']); exit; }

/* 0) Si viene ?jueces=... forzar orden/lista */
$forced = [];
if (!empty($_GET['jueces'])) {
  foreach (explode(',', $_GET['jueces']) as $id) {
    $id = (int)trim($id);
    if ($id>0 && !in_array($id,$forced,true)) $forced[] = $id;
  }
}

/* 1) Posible lista de habilitados por tabla jueces_por_pelea */
$enabled = [];
if (!$forced) {
  $haveJP = ($rs=$conexion->query("SHOW TABLES LIKE 'jueces_por_pelea'")) && $rs->num_rows>0; if(isset($rs)&&$rs) $rs->close();
  if ($haveJP) {
    $cols=[]; if($c=$conexion->query("SHOW COLUMNS FROM `jueces_por_pelea`")){ while($r=$c->fetch_assoc()){ $cols[strtolower($r['Field'])]=$r['Field']; } $c->close(); }
    $C_HAB = $cols['habilitado'] ?? ($cols['activo'] ?? null);
    $haveOrden = $cols['orden'] ?? null;
    $ord = $haveOrden ? ("ORDER BY `".$haveOrden."` ASC, juez_id ASC") : "ORDER BY juez_id ASC";
    $sql = "SELECT juez_id FROM `jueces_por_pelea` WHERE pelea_id=? ".($C_HAB ? "AND `".$C_HAB."`=1 " : "").$ord;
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('i',$pelea_id); $st->execute(); $res=$st->get_result();
      while($row=$res->fetch_assoc()){ $jid=(int)$row['juez_id']; if($jid>0 && !in_array($jid,$enabled,true)) $enabled[]=$jid; }
      $st->close();
    }
  }
}

/* 2) Cuerpo de puntajes (todas las filas de esta pelea) */
$rows=[];
if($st=$conexion->prepare("SELECT `round` AS round, juez_id, azul_puntos, rojo_puntos
                           FROM puntuaciones_jueces
                           WHERE pelea_id=?
                           ORDER BY `round` ASC, juez_id ASC")){
  $st->bind_param('i',$pelea_id);
  $st->execute();
  $r=$st->get_result();
  while($rw=$r->fetch_assoc()){ $rows[]=$rw; }
  $st->close();
}

/* 3) Si no hay forzados ni habilitados, tomar DISTINCT juez_id de lo ya puntuado */
if (!$forced && !$enabled) {
  if ($st=$conexion->prepare("SELECT DISTINCT juez_id FROM puntuaciones_jueces WHERE pelea_id=? ORDER BY juez_id ASC")){
    $st->bind_param('i',$pelea_id); $st->execute(); $res=$st->get_result();
    while($row=$res->fetch_assoc()){ $jid=(int)$row['juez_id']; if($jid>0) $enabled[]=$jid; }
    $st->close();
  }
}

/* 4) Resolver lista final de columnas */
$colsUse = $forced ?: $enabled;

/* 5) Nombres de jueces (si existe jueces_evento) */
$names=[];
if ($colsUse){
  $haveJE = ($rs=$conexion->query("SHOW TABLES LIKE 'jueces_evento'")) && $rs->num_rows>0; if(isset($rs)&&$rs) $rs->close();
  if($haveJE){
    $in = implode(',', array_fill(0,count($colsUse),'?'));
    $types = str_repeat('i', count($colsUse));
    $sql = "SELECT id, COALESCE(NULLIF(CONCAT(TRIM(apellido),' ',TRIM(nombre)),''), TRIM(nombre), CONCAT('Juez ',id)) AS nombre
            FROM jueces_evento WHERE id IN ($in)";
    if($st=$conexion->prepare($sql)){
      $st->bind_param($types, ...$colsUse);
      $st->execute(); $res=$st->get_result();
      while($row=$res->fetch_assoc()){ $names[(int)$row['id']] = $row['nombre']; }
      $st->close();
    }
  }
}

/* 6) Indexar por round */
$index = []; $roundsSet=[];
foreach($rows as $rw){
  $rn=(int)$rw['round']; $jid=(int)$rw['juez_id'];
  $roundsSet[$rn]=true;
  if(!isset($index[$rn])) $index[$rn]=[];
  $index[$rn][$jid]=['a'=>(int)$rw['azul_puntos'], 'r'=>(int)$rw['rojo_puntos']];
}
$rnums = array_keys($roundsSet); sort($rnums);
if(!$rnums) $rnums=[1];

/* 7) Construir tabla N columnas */
$html = '<thead><tr><th>Round</th>';
if ($colsUse){
  foreach($colsUse as $id){
    $label = isset($names[$id]) ? ('ID '.$id.' — '.e($names[$id])) : ('ID '.$id);
    $html .= '<th>'.$label.'</th>';
  }
}else{
  $html .= '<th>— — Jueces —</th>';
}
$html .= '<th>Rojo (Rds)</th><th>Azul (Rds)</th><th>Σ Rojo Pts</th><th>Σ Azul Pts</th></tr></thead><tbody>';

$sumR=0; $sumA=0; $TR=0; $TA=0;

foreach($rnums as $rn){
  $m = $index[$rn] ?? [];
  $rR=0; $rA=0; $pR=0; $pA=0;
  $html.='<tr><td>'.$rn.'</td>';
  if ($colsUse){
    foreach($colsUse as $id){
      $j = $m[$id] ?? null;
      if(!$j){ $html.='<td class="pending">—</td>'; continue; }
      $a=$j['a']; $ro=$j['r'];
      $g = ($a>$ro?'azul':($ro>$a?'rojo':'empate'));
      if($g==='rojo') $rR++; if($g==='azul') $rA++;
      $pR += $ro; $pA += $a;
      $ico = ($g==='rojo'?'🔴':($g==='azul'?'🔵':'⚖️'));
      $html.='<td>'.$ico.'<div class="sub" style="opacity:.85">'.$a.'–'.$ro.'</div></td>';
    }
  } else {
    $html.='<td class="pending">—</td>';
  }
  $sumR += $rR; $sumA += $rA; $TR += $pR; $TA += $pA;
  $html.='<td><b>'.$rR.'</b></td><td><b>'.$rA.'</b></td><td><b>'.$TR.'</b></td><td><b>'.$TA.'</b></td></tr>';
}
$html.='<tr><td><b>Σ</b></td>';
$html.= ($colsUse ? str_repeat('<td></td>', count($colsUse)) : '<td></td>');
$html.='<td class="winR">'.$sumR.'</td><td class="winA">'.$sumA.'</td><td class="winR">'.$TR.'</td><td class="winA">'.$TA.'</td></tr>';
$html.='</tbody>';

/* 8) Detalle por juez */
$det=[];
foreach($colsUse as $jid){
  $card=['juez_id'=>$jid,'nombre'=>($names[$jid]??null),'rows'=>[],'tot_az'=>0,'tot_ro'=>0];
  foreach($rnums as $rn){
    if(isset($index[$rn][$jid])){
      $a=$index[$rn][$jid]['a']; $ro=$index[$rn][$jid]['r'];
      $card['rows'][]=['round'=>$rn,'azul'=>$a,'rojo'=>$ro];
      $card['tot_az']+=$a; $card['tot_ro']+=$ro;
    }
  }
  $det[]=$card;
}

$gw = ($TR>$TA?'rojo':($TA>$TR?'azul':'empate'));
$banner = ($gw==='rojo')
  ? ('🔴 <b>Ganador por puntos totales</b> — Rojo '.$TR.' / Azul '.$TA)
  : (($gw==='azul') ? ('🔵 <b>Ganador por puntos totales</b> — Azul '.$TA.' / Rojo '.$TR)
                    : ('⚖️ <b>Empate por puntos totales</b> — Rojo '.$TR.' / Azul '.$TA));

echo json_encode([
  'ok'=>true,
  'pelea_id'=>$pelea_id,
  'jueces'=>$colsUse,
  'html_tabla'=>$html,
  'sum_rojo'=>$TR,
  'sum_azul'=>$TA,
  'ganador'=>$gw,
  'banner'=>$banner,
  'detalle'=>$det
], JSON_UNESCAPED_UNICODE);
