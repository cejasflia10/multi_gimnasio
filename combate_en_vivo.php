<?php
/* ===========================
   COMBATE EN VIVO — UNA SOLA PÁGINA
   - Vista HTML
   - Endpoint AJAX interno (?ajax=tablero | ?ajax=finalizar)
   Lee DIRECTO de:
     • puntuaciones_jueces (con detección automática de columnas)
     • fallos_jueces (juez_id, ganador, tipo, round_fin, tiempo_segundos, observaciones)
   Extras:
     • Sonidos por <audio> con rutas locales
     • Logos/escuela y datos (División/Peso/Modalidad) de competidores
   =========================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1'); // en vista
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
$__BUILD = @filemtime(__FILE__) ?: time();

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
/* Limpia cualquier salida previa antes de responder JSON (evita romper el fetch con BOM/espacios) */
function json_clean_headers(){
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Set-Cookie'); // opcional
  header('Content-Type: application/json; charset=utf-8');
}

/* ===== Ruta de resultados ===== */
$RESULTADOS_RUTA = 'resultados.php';

/* ===== Rutas de SONIDOS =====
   En disco (XAMPP): C:\xampp\htdocs\multi_gimnasio\assets\sounds\ring_start_bell.mp3
   En web: /multi_gimnasio/assets/sounds/...
*/
$SND_BASE     = '/multi_gimnasio/assets/sounds/'; // <— AJUSTADO A TU RUTA WEB
$SND_START    = $SND_BASE.'ring_start_bell.mp3'; // inicio asalto
$SND_ROUNDEND = $SND_BASE.'ring_end_bell.mp3';   // fin asalto
$SND_RESTEND  = $SND_BASE.'ring_end_bell.mp3';   // fin descanso / próximo asalto
$SND_FIGHTEND = $SND_BASE.'ring_end_bell.mp3';   // fin combate
$SND_WARN10   = $SND_BASE.'ring_end_bell.mp3';   // aviso 10s (si tenés otro, cambialo)

/* ===== Detección de columnas en puntuaciones_jueces ===== */
function pick_cols_puntuaciones(mysqli $db): array {
  $cols=[];
  if ($r=$db->query("SHOW COLUMNS FROM `puntuaciones_jueces`")) {
    while($c=$r->fetch_assoc()){ $cols[strtolower($c['Field'])]=$c['Field']; }
    $r->close();
  }
  $C_PELEA = $cols['pelea_id'] ?? ($cols['id_pelea'] ?? ($cols['pelea'] ?? null));
  $C_JUEZ  = $cols['juez_id']  ?? ($cols['id_juez']  ?? null);
  $C_ROND  = $cols['round']    ?? ($cols['ronda']    ?? null);
  $C_APTS  = $cols['azul_puntos'] ?? ($cols['puntos_azul'] ?? ($cols['azul'] ?? null));
  $C_RPTS  = $cols['rojo_puntos'] ?? ($cols['puntos_rojo'] ?? ($cols['rojo'] ?? null));
  return compact('C_PELEA','C_JUEZ','C_ROND','C_APTS','C_RPTS');
}

/* ===== Endpoint AJAX: TABLERO ===== */
if (isset($_GET['ajax']) && $_GET['ajax']==='tablero') {
  ini_set('display_errors', '0'); // no romper JSON
  json_clean_headers();

  $t0 = microtime(true);
  $pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
  if ($pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'pelea_id_invalido']); exit; }

  $map = pick_cols_puntuaciones($conexion);
  if (!$map['C_PELEA'] || !$map['C_JUEZ'] || !$map['C_ROND'] || !$map['C_APTS'] || !$map['C_RPTS']) {
    echo json_encode(['ok'=>false,'error'=>'schema_puntuaciones_incompleto','debug'=>$map], JSON_UNESCAPED_UNICODE); exit;
  }

  // jueces forzados por QS
  $forced=[]; if(!empty($_GET['jueces'])){ foreach(explode(',',$_GET['jueces']) as $id){ $id=(int)trim($id); if($id>0 && !in_array($id,$forced,true)) $forced[]=$id; } }

  // lista de jueces detectados por esa pelea
  $jueces = $forced;
  if (!$jueces) {
    $sqlJ = "SELECT DISTINCT ".bt($map['C_JUEZ'])." FROM puntuaciones_jueces WHERE ".bt($map['C_PELEA'])."=? ORDER BY ".bt($map['C_JUEZ'])." ASC";
    if ($st=$conexion->prepare($sqlJ)){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($jid);
      while($st->fetch()){ $jueces[]=(int)$jid; }
      $st->close();
    }
  }

  // filas de puntuaciones
  $rows=[]; $rowCount=0;
  $sqlP = "SELECT ".bt($map['C_ROND'])." AS r, ".bt($map['C_JUEZ'])." AS j, "
        . bt($map['C_APTS'])." AS a, ".bt($map['C_RPTS'])." AS ro "
        . "FROM puntuaciones_jueces WHERE ".bt($map['C_PELEA'])."=? "
        . "ORDER BY r ASC, j ASC";
  if ($st=$conexion->prepare($sqlP)){
    $st->bind_param('i',$pelea_id);
    $st->execute();
    $st->bind_result($ronda,$jid,$az,$ro);
    while($st->fetch()){ $rows[]=['ronda'=>(int)$ronda,'juez_id'=>(int)$jid,'a'=>(int)$az,'r'=>(int)$ro]; $rowCount++; }
    $st->close();
  }

  // nombres jueces
  $names=[];
  if ($jueces && table_exists($conexion,'jueces_evento')) {
    $in = implode(',', array_fill(0,count($jueces),'?'));
    $typ = str_repeat('i', count($jueces));
    $sqlN = "SELECT id, COALESCE(NULLIF(CONCAT(TRIM(apellido),' ',TRIM(nombre)),''), TRIM(nombre), CONCAT('Juez ',id)) AS nombre
             FROM jueces_evento WHERE id IN ($in)";
    if($st=$conexion->prepare($sqlN)){
      $st->bind_param($typ, ...$jueces);
      $st->execute(); $st->store_result();
      $st->bind_result($rid,$rnombre);
      while($st->fetch()){ $names[(int)$rid]=$rnombre; }
      $st->close();
    }
  }

  // index + totales por juez
  $index=[]; $roundsSet=[];
  foreach($rows as $rw){
    $rn=$rw['ronda']; $jid=$rw['juez_id'];
    $roundsSet[$rn]=true;
    if(!isset($index[$rn])) $index[$rn]=[];
    $index[$rn][$jid]=['a'=>$rw['a'],'r'=>$rw['r']];
  }
  $rnums = array_keys($roundsSet); sort($rnums); if(!$rnums) $rnums=[1];

  $totA=[]; $totR=[]; $winA=[]; $winR=[];
  foreach($jueces as $jid){ $totA[$jid]=0; $totR[$jid]=0; $winA[$jid]=0; $winR[$jid]=0; }

  // HTML tarjetas x juez
  $html = '<thead><tr><th>Round</th>';
  if ($jueces){
    foreach($jueces as $jid){
      $label = isset($names[$jid]) ? ('ID '.$jid.' — '.h($names[$jid])) : ('ID '.$jid);
      $html.='<th>'.$label.'</th>';
    }
  } else {
    $html.='<th>— Jueces —</th>';
  }
  $html.='</tr></thead><tbody>';

  foreach($rnums as $rn){
    $html.='<tr><td>'.$rn.'</td>';
    if ($jueces){
      $m = $index[$rn] ?? [];
      foreach($jueces as $jid){
        if (!isset($m[$jid])) { $html.='<td class="pending">—</td>'; continue; }
        $a=$m[$jid]['a']; $r=$m[$jid]['r'];
        $ico = ($a>$r?'🔵':($r>$a?'🔴':'⚖️'));
        $totA[$jid]+=$a; $totR[$jid]+=$r;
        if ($a>$r) $winA[$jid]++; elseif ($r>$a) $winR[$jid]++;
        $html.='<td>'.$ico.'<div class="sub" style="opacity:.85">'.$a.'–'.$r.'</div></td>';
      }
    } else { $html.='<td class="pending">—</td>'; }
    $html.='</tr>';
  }

  // Σ Pts
  $html.='<tr><td><b>Σ Pts</b></td>';
  if ($jueces){
    foreach($jueces as $jid){ $html.='<td><b>Azul '.$totA[$jid].' / Rojo '.$totR[$jid].'</b></td>'; }
  } else { $html.='<td></td>'; }
  $html.='</tr>';

  // Σ Rds
  $html.='<tr><td><b>Σ Rds</b></td>';
  if ($jueces){
    foreach($jueces as $jid){ $html.='<td>🔵 '.$winA[$jid].' / 🔴 '.$winR[$jid].'</td>'; }
  } else { $html.='<td></td>'; }
  $html.='</tr>';

  // Banner por tarjetas (default)
  $countAz=0; $countRo=0; $countEmp=0; $cards=[];
  foreach($jueces as $jid){
    $a=$totA[$jid]; $r=$totR[$jid];
    if ($a>$r){ $countAz++; $cards[]='Juez '.$jid.': 🔵 Azul '.$a.'–'.$r; }
    elseif ($r>$a){ $countRo++; $cards[]='Juez '.$jid.': 🔴 Rojo '.$r.'–'.$a; }
    else { $countEmp++; $cards[]='Juez '.$jid.': ⚖️ Empate '.$a.'–'.$r; }
  }
  if ($countAz>$countRo) $resTar='Ganador por tarjetas: 🔵 Azul ('.$countAz.'–'.$countRo.($countEmp?(', '.$countEmp.' emp.'):'' ).')';
  elseif ($countRo>$countAz) $resTar='Ganador por tarjetas: 🔴 Rojo ('.$countRo.'–'.$countAz.($countEmp?(', '.$countEmp.' emp.'):'' ).')';
  else $resTar='Empate en tarjetas ('.$countAz.'–'.$countRo.($countEmp?(', '.$countEmp.' emp.'):'' ).')';
  $banner = '<div>'.$resTar.'</div><div class="sub" style="margin-top:3px">'.h(implode(' · ',$cards)).'</div>';

  /* ===== OVERRIDE por FALLOS ===== */
  $fallos = []; $fallosCards=[];
  $falloAz=0; $falloRo=0; $falloEmp=0; $tipoCount=[]; $rMin=null;

  if (table_exists($conexion,'fallos_jueces')) {
    if ($st=$conexion->prepare("SELECT juez_id, ganador, tipo, COALESCE(round_fin,0) AS rf
                                FROM fallos_jueces WHERE pelea_id=?")){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($fjid,$fg,$ft,$fr);
      while($st->fetch()){
        $g=strtolower((string)$fg); $t=strtoupper((string)$ft); $rf=(int)$fr; $jid=(int)$fjid;
        $fallos[]=['juez_id'=>$jid,'ganador'=>$g,'tipo'=>$t,'round_fin'=>$rf];
        if ($g==='azul'){ $falloAz++; $tipoCount[$t]=($tipoCount[$t]??0)+1; }
        elseif ($g==='rojo'){ $falloRo++; $tipoCount[$t]=($tipoCount[$t]??0)+1; }
        else { $falloEmp++; }
        if ($rf>0){ $rMin = is_null($rMin)?$rf:min($rMin,$rf); }
      }
      $st->close();
    }
  }

  if ($fallos) {
    foreach($fallos as $f){
      $label = 'Juez '.$f['juez_id'];
      if (isset($names[$f['juez_id']])) $label .= ' — '.$names[$f['juez_id']];
      $who = $f['ganador']==='azul'?'🔵 Azul':($f['ganador']==='rojo'?'🔴 Rojo':'⚖️ Sin ganador');
      $extra = $f['tipo'] ? (' '.$f['tipo']) : '';
      if ($f['round_fin']>0) $extra .= ' R'.$f['round_fin'];
      $fallosCards[] = $label.': '.$who.$extra;
    }

    if ($falloAz>$falloRo || $falloRo>$falloAz) {
      arsort($tipoCount); $tipoTop = key($tipoCount);
      $ganColor = ($falloAz>$falloRo)?'🔵 Azul':'🔴 Rojo';
      $score = ($falloAz>$falloRo)? ($falloAz.'–'.$falloRo) : ($falloRo.'–'.$falloAz);
      $banner = '<div>Fallo en vivo: '.$ganColor.' por '.$tipoTop.($rMin?(' en R'.$rMin):'').' ('.$score.')</div>'
              . '<div class="sub" style="margin-top:3px">'.h(implode(' · ',$fallosCards)).'</div>';
    } else {
      $banner .= '<div class="sub" style="margin-top:6px">Fallos cargados (sin mayoría): '.h(implode(' · ',$fallosCards)).'</div>';
    }
  }

  $t1 = microtime(true);
  echo json_encode([
    'ok'=>true,
    'jueces'=>$jueces,
    'html_tabla'=>$html,
    'banner'=>$banner,
    'debug'=>[
      'rows'=>$rowCount,
      'jueces_detectados'=>count($jueces),
      'ms'=>round(($t1-$t0)*1000,1),
      'cols'=>$map
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Endpoint AJAX: FINALIZAR (decide y redirige) ===== */
if (isset($_GET['ajax']) && $_GET['ajax']==='finalizar') {
  ini_set('display_errors', '0');
  json_clean_headers();
  $pelea_id = isset($_POST['pelea_id']) ? (int)$_POST['pelea_id'] : 0;
  if ($pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'pelea_id_invalido']); exit; }

  // 1) Fallos por juez
  $fallos=[];
  if (table_exists($conexion,'fallos_jueces')) {
    if ($st=$conexion->prepare("SELECT ganador,tipo,COALESCE(round_fin,0) AS rf FROM fallos_jueces WHERE pelea_id=?")){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($g,$t,$rf);
      while($st->fetch()){ $fallos[]=['ganador'=>strtolower($g),'tipo'=>strtoupper($t),'round_fin'=>(int)$rf]; }
      $st->close();
    }
  }

  // 2) Conteo por puntos
  $map = pick_cols_puntuaciones($conexion);
  $cards=[];
  if ($map['C_PELEA'] && $map['C_JUEZ'] && $map['C_APTS'] && $map['C_RPTS']) {
    $sql = "SELECT ".bt($map['C_JUEZ'])." AS j, SUM(".bt($map['C_APTS']).") AS az, SUM(".bt($map['C_RPTS']).") AS ro "
         . "FROM puntuaciones_jueces WHERE ".bt($map['C_PELEA'])."=? GROUP BY j ORDER BY j";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($jid,$az,$ro);
      while($st->fetch()){ $cards[(int)$jid]=['az'=>(int)$az,'ro'=>(int)$ro]; }
      $st->close();
    }
  }

  // 3) Mayoría por fallos
  $cF=['azul'=>0,'rojo'=>0]; $tiposWin=[]; $rMin=null;
  foreach($fallos as $f){
    if ($f['ganador']==='azul'||$f['ganador']==='rojo'){
      $cF[$f['ganador']]++;
      $tiposWin[$f['tipo']] = ($tiposWin[$f['tipo']] ?? 0) + 1;
      if ($f['round_fin']>0) $rMin = is_null($rMin) ? $f['round_fin'] : min($rMin,$f['round_fin']);
    }
  }
  $gan=''; $metodo=''; $detalle='';
  if ($cF['azul']>$cF['rojo']) { $gan='azul'; arsort($tiposWin); $metodo=key($tiposWin)?:'KO'; $detalle='por '.$metodo.($rMin?(' en R'.$rMin):''); }
  elseif ($cF['rojo']>$cF['azul']) { $gan='rojo'; arsort($tiposWin); $metodo=key($tiposWin)?:'KO'; $detalle='por '.$metodo.($rMin?(' en R'.$rMin):''); }

  // 4) Si no hay mayoría de fallos, decidir por tarjetas
  if ($gan==='') {
    $cP=['azul'=>0,'rojo'=>0,'emp'=>0];
    foreach($cards as $c){
      if ($c['az']>$c['ro']) $cP['azul']++;
      elseif ($c['ro']>$c['az']) $cP['rojo']++;
      else $cP['emp']++;
    }
    if ($cP['azul']>$cP['rojo']) { $gan='azul'; $metodo='PTS'; $detalle='por puntos ('.$cP['azul'].'–'.$cP['rojo'].($cP['emp']?(', '.$cP['emp'].' emp.'):'').')'; }
    elseif ($cP['rojo']>$cP['azul']) { $gan='rojo'; $metodo='PTS'; $detalle='por puntos ('.$cP['rojo'].'–'.$cP['azul'].($cP['emp']?(', '.$cP['emp'].' emp.'):'').')'; }
    else { $gan='empate'; $metodo='DRAW'; $detalle='empate en tarjetas'; }
  }

  // 5) Intentar actualizar peleas_evento
  $C_GAN_COLOR = has_col($conexion,'peleas_evento','ganador_color') ? 'ganador_color' : (has_col($conexion,'peleas_evento','ganador')?'ganador':null);
  $C_ESTADO    = has_col($conexion,'peleas_evento','estado') ? 'estado' : null;
  $C_DETALLE   = has_col($conexion,'peleas_evento','detalle_resultado') ? 'detalle_resultado' : (has_col($conexion,'peleas_evento','resolucion')?'resolucion':null);
  $C_AZUL_ID   = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_ROJO_ID   = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_GAN_ID    = has_col($conexion,'peleas_evento','ganador_id') ? 'ganador_id' : null;

  $azul_id = $rojo_id = null;
  if ($C_AZUL_ID || $C_ROJO_ID) {
    $sel = [];
    if ($C_AZUL_ID) $sel[] = bt($C_AZUL_ID).' AS az';
    if ($C_ROJO_ID) $sel[] = bt($C_ROJO_ID).' AS ro';
    if ($sel && $st=$conexion->prepare("SELECT ".implode(',',$sel)." FROM peleas_evento WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      if ($C_AZUL_ID && $C_ROJO_ID) { $st->bind_result($azul_id,$rojo_id); }
      elseif ($C_AZUL_ID) { $st->bind_result($azul_id); }
      elseif ($C_ROJO_ID) { $st->bind_result($rojo_id); }
      $st->fetch(); $st->close();
    }
  }

  if ($C_ESTADO){
    if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_ESTADO)."='finalizada' WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id); $st->execute(); $st->close();
    }
  }
  if ($C_GAN_COLOR){
    $val = ($gan==='empate'?'empate':$gan);
    if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_COLOR)."=? WHERE id=? LIMIT 1")){
      $st->bind_param('si',$val,$pelea_id); $st->execute(); $st->close();
    }
  }
  if ($C_GAN_ID && ($azul_id || $rojo_id)){
    $gid = ($gan==='azul'?$azul_id:($gan==='rojo'?$rojo_id:null));
    if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=? WHERE id=? LIMIT 1")){
      if ($gid===null) { $null = null; $st->bind_param('ii',$null,$pelea_id); }
      else { $st->bind_param('ii',$gid,$pelea_id); }
      $st->execute(); $st->close();
    }
  }
  if ($C_DETALLE){
    $txt = strtoupper($metodo).' — '.$detalle;
    if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_DETALLE)."=? WHERE id=? LIMIT 1")){
      $st->bind_param('si',$txt,$pelea_id); $st->execute(); $st->close();
    }
  }

  $redir = $RESULTADOS_RUTA.'?pelea_id='.$pelea_id;
  echo json_encode(['ok'=>true,'ganador'=>$gan,'metodo'=>$metodo,'detalle'=>$detalle,'redirect'=>$redir], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ===== Vista HTML ===== */
$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>'; exit; }

/* Info pelea + competidores + logos + datos (División/Peso/Modalidad) */
$evento_id = null; $rondasEsperadas=3;
$azul_nom='Azul'; $rojo_nom='Rojo'; $azul_logo=''; $rojo_logo='';
$azul_escuela=''; $rojo_escuela='';
$azul_div=''; $rojo_div=''; $azul_peso=''; $rojo_peso=''; $azul_mod=''; $rojo_mod='';

if (table_exists($conexion,'peleas_evento')) {
  // detectar columnas clave
  $C_AZUL_ID = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_ROJO_ID = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_RONDAS  = has_col($conexion,'peleas_evento','rondas') ? 'rondas' : (has_col($conexion,'peleas_evento','total_rounds')?'total_rounds':null);
  $C_EVENTO  = has_col($conexion,'peleas_evento','evento_id') ? 'evento_id' : null;

  $az_id = $ro_id = null;

  if ($C_AZUL_ID && $C_ROJO_ID) {
    $sel = ($C_EVENTO?bt($C_EVENTO).' AS ev':'NULL AS ev');
    $sel .= ', '.($C_RONDAS? bt($C_RONDAS).' AS rds':'NULL AS rds');
    $sel .= ', '.bt($C_AZUL_ID).' AS az, '.bt($C_ROJO_ID).' AS ro';
    if ($st=$conexion->prepare("SELECT $sel FROM peleas_evento WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id);
      $st->execute();
      $st->bind_result($ev,$rds,$az_id,$ro_id);
      if($st->fetch()){ $evento_id = is_null($ev)?null:(int)$ev; $rondasEsperadas = (int)$rds>0?(int)$rds:3; }
      $st->close();
    }
  }

  // fallback nombres embebidos en pelea
  if (($az_id===null || $ro_id===null) && (has_col($conexion,'peleas_evento','azul_nombre') || has_col($conexion,'peleas_evento','competidor_a'))) {
    $C_AZ_TXT = has_col($conexion,'peleas_evento','azul_nombre') ? 'azul_nombre' : 'competidor_a';
    $C_RO_TXT = has_col($conexion,'peleas_evento','rojo_nombre') ? 'rojo_nombre' : (has_col($conexion,'peleas_evento','competidor_b')?'competidor_b':null);
    $sel = ($C_EVENTO?bt($C_EVENTO).' AS ev':'NULL AS ev');
    $sel .= ', '.($C_RONDAS?bt($C_RONDAS).' AS rds':'NULL AS rds');
    $sel .= ', '.($C_AZ_TXT?bt($C_AZ_TXT).' AS an':'NULL AS an');
    $sel .= ', '.($C_RO_TXT?bt($C_RO_TXT).' AS rn':'NULL AS rn');
    if ($st=$conexion->prepare("SELECT $sel FROM peleas_evento WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id); $st->execute();
      $st->bind_result($ev,$rds,$an,$rn);
      if($st->fetch()){
        $evento_id = is_null($ev)?null:(int)$ev;
        $rds = (int)$rds; if($rds>0)$rondasEsperadas=$rds;
        if ($an) $azul_nom=$an;
        if ($rn) $rojo_nom=$rn;
      }
      $st->close();
    }
  }

  // Datos competidores_evento (nombre, escuela, logo, división, peso, modalidad)
  if (table_exists($conexion,'competidores_evento') && $az_id && $ro_id) {
    $LOGO_CANDS = ['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela'];
    $C_ESCUELA = has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre' : (has_col($conexion,'competidores_evento','gimnasio')?'gimnasio':null);
    $C_LOGO = null; foreach($LOGO_CANDS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }

    $haveDV  = table_exists($conexion,'divisiones_evento');
    $haveCP  = table_exists($conexion,'categorias_peso_evento');
    $haveMD  = table_exists($conexion,'modalidades_evento');

    $C_DIV_ID  = has_col($conexion,'competidores_evento','division_id') ? 'division_id' : (has_col($conexion,'competidores_evento','id_division')?'id_division':null);
    $C_DIV_TXT = has_col($conexion,'competidores_evento','division') ? 'division' : null;

    $C_PESO_ID  = has_col($conexion,'competidores_evento','categoria_peso_id') ? 'categoria_peso_id' : (has_col($conexion,'competidores_evento','id_categoria_peso')?'id_categoria_peso':null);
    $C_PESO_TXT = has_col($conexion,'competidores_evento','peso') ? 'peso' : (has_col($conexion,'competidores_evento','categoria_peso')?'categoria_peso':null);

    $C_MOD_ID  = has_col($conexion,'competidores_evento','modalidad_id') ? 'modalidad_id' : null;
    $C_MOD_TXT = has_col($conexion,'competidores_evento','modalidad') ? 'modalidad' : null;

    $cols = "ce.id, TRIM(CONCAT(COALESCE(ce.apellido,''),' ',COALESCE(ce.nombre,''))) AS nom";
    $cols .= $C_ESCUELA?(", ce.".bt($C_ESCUELA)." AS esc") : ", NULL AS esc";
    $cols .= $C_LOGO?(", ce.".bt($C_LOGO)." AS logo") : ", NULL AS logo";

    if ($haveDV && $C_DIV_ID) { $cols .= ", dv.nombre AS division"; }
    elseif ($C_DIV_TXT) { $cols .= ", ce.".bt($C_DIV_TXT)." AS division"; }
    else { $cols .= ", NULL AS division"; }

    if ($haveCP && $C_PESO_ID) { $cols .= ", cp.nombre AS peso"; }
    elseif ($C_PESO_TXT) { $cols .= ", ce.".bt($C_PESO_TXT)." AS peso"; }
    else { $cols .= ", NULL AS peso"; }

    if ($haveMD && $C_MOD_ID) { $cols .= ", md.nombre AS modalidad"; }
    elseif ($C_MOD_TXT) { $cols .= ", ce.".bt($C_MOD_TXT)." AS modalidad"; }
    else { $cols .= ", NULL AS modalidad"; }

    $joins = "";
    if ($haveDV && $C_DIV_ID)  $joins .= " LEFT JOIN divisiones_evento dv ON dv.id = ce.".bt($C_DIV_ID);
    if ($haveCP && $C_PESO_ID) $joins .= " LEFT JOIN categorias_peso_evento cp ON cp.id = ce.".bt($C_PESO_ID);
    if ($haveMD && $C_MOD_ID)  $joins .= " LEFT JOIN modalidades_evento md ON md.id = ce.".bt($C_MOD_ID);

    $in = [$az_id,$ro_id];
    $ph = implode(',', array_fill(0,count($in),'?'));
    $typ = str_repeat('i', count($in));
    $sql = "SELECT $cols FROM competidores_evento ce $joins WHERE ce.id IN ($ph)";

    if ($st=$conexion->prepare($sql)){
      $st->bind_param($typ, ...$in);
      $st->execute(); $st->store_result();
      $st->bind_result($cid,$nom,$esc,$logo,$division,$peso,$modalidad);
      while($st->fetch()){
        if ((int)$cid === (int)$az_id){
          $azul_nom = $nom?:$azul_nom; $azul_escuela=(string)($esc??''); $azul_logo=(string)($logo??'');
          $azul_div=(string)($division??''); $azul_peso=(string)($peso??''); $azul_mod=(string)($modalidad??'');
        }
        if ((int)$cid === (int)$ro_id){
          $rojo_nom = $nom?:$rojo_nom; $rojo_escuela=(string)($esc??''); $rojo_logo=(string)($logo??'');
          $rojo_div=(string)($division??''); $rojo_peso=(string)($peso??''); $rojo_mod=(string)($modalidad??'');
        }
      }
      $st->close();
    }
  }
}
$return_to = 'ver_peleas_evento.php'.($evento_id?('?evento_id='.(int)$evento_id):'');

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></title>
<link rel="stylesheet" href="estilo_unificado.css?v=<?= $__BUILD ?>">
<style>
  :root{ --panel-bg:#121416; --panel-br:#26313a; --muted:#b6c7d8; }
  body{background:#0c0f12;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0}
  .stage{max-width:1300px;margin:0 auto;padding:16px;text-align:center}
  .grid{display:grid;grid-template-columns:1fr 1.6fr 1fr;gap:14px}
  @media (max-width:1100px){ .grid{grid-template-columns:1fr} }
  .panel{background:var(--panel-bg);border:1px solid var(--panel-br);border-radius:14px;padding:16px}
  .red{background:linear-gradient(#260608,#160203);border-color:#3a0c10}
  .blue{background:linear-gradient(#06223a,#021426);border-color:#0c305a}
  .corner-title{font-weight:800;margin-bottom:8px;letter-spacing:.5px;opacity:.9}
  .name{font-size:22px;font-weight:800}
  .sub{font-size:14px;color:var(--muted)}
  .esc{display:flex;gap:10px;align-items:center;justify-content:center;margin-top:8px}
  .esc img{width:56px;height:56px;object-fit:contain;background:#0b0e12;border-radius:10px;border:1px solid #213142}
  .esc .esc-name{font-weight:700;opacity:.95}
  .tags{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:8px}
  .tag{padding:5px 10px;border-radius:999px;background:#1b2430;border:1px solid #2a3a4a;font-size:12px}
  .controls{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-top:12px}
  .btn{padding:12px 16px;border-radius:10px;border:0;cursor:pointer}
  .btn-primary{background:#00b894;color:#fff}.btn-warn{background:#ffb300}.btn-danger{background:#e53935}.btn-gray{background:#2a2f35;color:#fff}
  .btn-ghost{background:transparent;border:1px solid #334250}
  .timer{font-size:110px;font-weight:900;line-height:1;letter-spacing:1px}
  .round{font-size:18px;font-weight:700;margin-bottom:6px}
  .score-panel{margin-top:14px;background:#0e1216;border:1px solid #22303c;border-radius:12px;padding:12px;text-align:left}
  .score-table{width:100%;border-collapse:collapse}
  .score-table th,.score-table td{border:1px solid #1f2c39;padding:7px 8px;font-size:13px;vertical-align:top}
  .score-table th{background:#121a24}
  .muted{color:var(--muted)}
  .build{position:fixed;right:8px;bottom:8px;background:#111;color:#9fe;border:1px solid #234;padding:6px 8px;border-radius:8px;font:12px/1.1 monospace;z-index:99999}
</style>
</head>
<body>
<div class="stage">
  <h2 style="margin:0 0 6px">🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></h2>
  <div class="sub" style="margin-bottom:10px">
    <?= $evento_id!==null ? ('Evento #'.(int)$evento_id) : '(sin evento_id)' ?>
    · Rondas configuradas: <b><?= (int)$rondasEsperadas ?></b>
  </div>

  <div class="grid">
    <!-- ROJO -->
    <section class="panel red">
      <div class="corner-title">🔴 RINCÓN ROJO</div>
      <div class="name"><?= h($rojo_nom) ?></div>
      <?php if ($rojo_logo || $rojo_escuela): ?>
      <div class="esc">
        <?php if ($rojo_logo): ?><img src="<?= h($rojo_logo) ?>" alt="Logo escuela roja"><?php endif; ?>
        <?php if ($rojo_escuela): ?><div class="esc-name"><?= h($rojo_escuela) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($rojo_div): ?><span class="tag">División: <?= h($rojo_div) ?></span><?php endif; ?>
        <?php if ($rojo_peso): ?><span class="tag">Peso: <?= h($rojo_peso) ?></span><?php endif; ?>
        <?php if ($rojo_mod): ?><span class="tag">Modalidad: <?= h($rojo_mod) ?></span><?php endif; ?>
      </div>
    </section>

    <!-- CENTRO: TIMER XXL + CONTROLES + TABLERO -->
    <section class="panel">
      <div class="round">Round <span id="round">1</span></div>
      <div id="timer" class="timer">3:00</div>
      <div class="controls">
        <button id="btnStart" class="btn btn-primary">▶️ Iniciar</button>
        <button id="btnPause" class="btn btn-warn">⏸️ Pausar</button>
        <button id="btnReset" class="btn btn-danger">⟲ Reiniciar</button>
        <button id="btnNext"  class="btn btn-ghost">⏭️ Siguiente round</button>
        <button id="btnSound" class="btn btn-gray">🔈 Sonido: ON</button>
      </div>
      <div class="controls">
        <span class="sub">Duración (seg):</span><input id="selDur" type="number" class="btn btn-gray" style="width:110px" min="30" max="900" step="5" value="180">
        <span class="sub">Descanso (seg):</span><input id="selRest" type="number" class="btn btn-gray" style="width:110px" min="10" max="600" step="5" value="60">
      </div>

      <!-- Tablero -->
      <div class="score-panel">
        <div class="sub" style="margin-bottom:6px;">Tablero (por tarjeta / juez)</div>
        <div class="table-wrap">
          <table class="score-table" id="scores"><tr><td style="padding:10px">Esperando datos…</td></tr></table>
        </div>
        <div id="banner" class="sub" style="margin-top:6px;opacity:.9"></div>
      </div>

      <div class="controls">
        <a class="btn btn-gray" href="<?= h($return_to) ?>">↩️ Volver</a>
        <button id="btnFinish" class="btn btn-danger">🏁 Finalizar combate</button>
      </div>
    </section>

    <!-- AZUL -->
    <section class="panel blue">
      <div class="corner-title">🔵 RINCÓN AZUL</div>
      <div class="name"><?= h($azul_nom) ?></div>
      <?php if ($azul_logo || $azul_escuela): ?>
      <div class="esc">
        <?php if ($azul_logo): ?><img src="<?= h($azul_logo) ?>" alt="Logo escuela azul"><?php endif; ?>
        <?php if ($azul_escuela): ?><div class="esc-name"><?= h($azul_escuela) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($azul_div): ?><span class="tag">División: <?= h($azul_div) ?></span><?php endif; ?>
        <?php if ($azul_peso): ?><span class="tag">Peso: <?= h($azul_peso) ?></span><?php endif; ?>
        <?php if ($azul_mod): ?><span class="tag">Modalidad: <?= h($azul_mod) ?></span><?php endif; ?>
      </div>
    </section>
  </div>
</div>

<!-- ====== Sonidos por archivos ====== -->
<audio id="snd-start"    src="<?= h($SND_START) ?>"    preload="auto"></audio>
<audio id="snd-10"       src="<?= h($SND_WARN10) ?>"   preload="auto"></audio>
<audio id="snd-roundend" src="<?= h($SND_ROUNDEND) ?>" preload="auto"></audio>
<audio id="snd-restend"  src="<?= h($SND_RESTEND) ?>"  preload="auto"></audio>
<audio id="snd-fightend" src="<?= h($SND_FIGHTEND) ?>" preload="auto"></audio>

<div class="build">combate_en_vivo.php · build <?= $__BUILD ?></div>

<script>
(function(){
  // ====== Sonidos <audio> ======
  let soundOn = true;
  const btnSound = document.getElementById('btnSound');
  const S = {
    start:    document.getElementById('snd-start'),
    warn10:   document.getElementById('snd-10'),
    roundEnd: document.getElementById('snd-roundend'),
    restEnd:  document.getElementById('snd-restend'),
    finish:   document.getElementById('snd-fightend'),
  };
  function syncMute(){
    for (const k in S){ if(S[k]) S[k].muted = !soundOn; }
    btnSound.textContent = (soundOn ? '🔈 Sonido: ON' : '🔇 Sonido: OFF');
  }
  function play(name){
    if (!soundOn || !S[name]) return;
    try { S[name].currentTime = 0; S[name].play().catch(()=>{}); } catch(e){}
  }
  btnSound.onclick = ()=>{ soundOn = !soundOn; syncMute(); };
  syncMute();

  // ===== Timer =====
  let duration=180, rest=60, remain=duration, round=1, t=null, inRest=false;
  const timer=document.getElementById('timer'), rnd=document.getElementById('round');
  const selDur=document.getElementById('selDur'), selRest=document.getElementById('selRest');
  const btnStart=document.getElementById('btnStart'), btnPause=document.getElementById('btnPause'),
        btnReset=document.getElementById('btnReset'), btnNext=document.getElementById('btnNext');

  function clamp(n,min,max){n=parseInt(n,10);if(isNaN(n))return min;return Math.max(min,Math.min(max,n));}
  function fmt(s){const m=Math.floor(s/60), ss=String(s%60).padStart(2,'0'); return `${m}:${ss}`;}
  function paint(){ timer.textContent=fmt(remain); rnd.textContent=round; }
  function tick(){
    if(remain>0){
      remain--;
      if(remain===10 && !inRest) play('warn10');
      return paint();
    }
    if(!inRest){
      play('roundEnd'); inRest=true; remain=rest;
    } else {
      play('restEnd'); inRest=false; round++; remain=duration;
    }
    paint();
  }
  btnStart.onclick=()=>{ if(!t){ t=setInterval(tick,1000); play('start'); } };
  btnPause.onclick=()=>{ if(t){clearInterval(t); t=null; } };
  btnReset.onclick=()=>{ if(t){clearInterval(t); t=null;} inRest=false; round=1; remain=duration; paint(); };
  btnNext.onclick =()=>{ if(t){clearInterval(t); t=null;} inRest=false; round++; remain=duration; paint(); play('restEnd'); };
  selDur.onchange =()=>{ duration=clamp(selDur.value,30,900); selDur.value=duration; if(!inRest&&remain>duration) remain=duration; paint(); };
  selRest.onchange=()=>{ rest=clamp(selRest.value,10,600); selRest.value=rest; if(inRest&&remain>rest) remain=rest; paint(); };
  paint();

  // ===== Tablero (AJAX cada 1.5s, con logs) =====
  const peleaId = <?= (int)$pelea_id ?>;
  const tabla=document.getElementById('scores'), banner=document.getElementById('banner');
  let lastHTML = '', lastBanner = '';
  function bust(u){ const s=u.includes('?')?'&':'?'; return u+s+'_='+(Date.now()); }
  async function fetchJSON(url, opt={}){
    const ctrl=new AbortController(); const to=setTimeout(()=>ctrl.abort(), opt.timeout||5000);
    try{
      const r=await fetch(url,{cache:'no-store',signal:ctrl.signal,method:opt.method||'GET',headers:{'Accept':'application/json'},body:opt.body||null});
      if(!r.ok){ console.warn('AJAX tablero status', r.status); return null; }
      return await r.json();
    }catch(e){ console.warn('AJAX tablero error', e); return null; } finally{ clearTimeout(to); }
  }
  async function loadBoard(){
    const j=await fetchJSON(bust('combate_en_vivo.php?ajax=tablero&pelea_id='+peleaId), {timeout:6000});
    if(!j||!j.ok){
      if (!lastHTML) { tabla.innerHTML='<tr><td style="padding:10px">Sin datos (todavía)…</td></tr>'; banner.textContent=''; }
      return;
    }
    if (j.debug) console.log('tablero:', j.debug);
    if (j.html_tabla && j.html_tabla!==lastHTML){ tabla.innerHTML = j.html_tabla; lastHTML=j.html_tabla; }
    if (j.banner && j.banner!==lastBanner){ banner.innerHTML = j.banner; lastBanner=j.banner; }
  }
  loadBoard();
  setInterval(loadBoard, 1500);

  // ===== Finalizar combate =====
  const btnFinish = document.getElementById('btnFinish');
  btnFinish.onclick = async ()=>{
    if(!confirm('¿Finalizar el combate y enviar a resultados?')) return;
    if(t){ clearInterval(t); t=null; }
    play('finish');
    const form = new FormData(); form.append('pelea_id', String(peleaId));
    const j = await fetchJSON('combate_en_vivo.php?ajax=finalizar', {method:'POST', body:form, timeout:8000});
    if (!j || !j.ok){ alert('No se pudo finalizar. Verificá que existan puntuaciones o fallos.'); return; }
    banner.innerHTML = `<b>Resultado:</b> ${j.ganador.toUpperCase()} — ${j.metodo} · <span class="muted">${j.detalle||''}</span>`;
    setTimeout(()=>{ window.location.href = j.redirect || '<?= h($RESULTADOS_RUTA) ?>?pelea_id='+peleaId; }, 800);
  };
})();
</script>
</body>
</html>
