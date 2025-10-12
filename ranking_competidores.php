<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0;
  if ($q) $q->close();
  return $ok;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  $r=$db->query($sql);
  $ok = $r && $r->num_rows>0;
  if($r) $r->close();
  return $ok;
}
function pick_col(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }

/* ===== Normalización y similitud ===== */
function normalize_dni($dni){
  $d = preg_replace('~\D+~','',(string)$dni);
  return (strlen($d)===8) ? $d : null;
}
function strip_accents($str){
  $str = (string)$str;
  $rep = ['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
  $str = strtr($str,$rep);
  $x = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$str);
  if ($x !== false) $str = $x;
  return $str;
}
function norm_txt($s){
  $s = mb_strtolower(strip_accents(trim((string)$s)),'UTF-8');
  $s = preg_replace('~\s+~',' ',$s);
  return $s;
}
function normalize_name_key($ape,$nom){
  $ape = norm_txt($ape); $nom = norm_txt($nom);
  if ($ape === '' && $nom === '') return null;
  return trim($ape.' '.$nom);
}
function first_name($full){
  $t = trim((string)$full);
  $t = preg_replace('~\s+~',' ',$t);
  $parts = explode(' ', $t);
  return norm_txt($parts[0] ?? '');
}
function is_similar_lev($a,$b,$max=2){
  $a = norm_txt($a); $b = norm_txt($b);
  if ($a === '' || $b === '') return false;
  if ($a === $b) return true;
  return levenshtein($a,$b) <= $max;
}
function metaphone_similar($a,$b,$max=1){
  $a = norm_txt($a); $b = norm_txt($b);
  if ($a === '' || $b === '') return false;
  $ma = metaphone($a); $mb = metaphone($b);
  if ($ma === '' || $mb === '') return false;
  if ($ma === $mb) return true;
  return levenshtein($ma,$mb) <= $max;
}
function is_similar_name($a,$b,$maxLev=2){
  // Coincide si Levenshtein o Metaphone "cercanos"
  return is_similar_lev($a,$b,$maxLev) || metaphone_similar($a,$b,1);
}
function like_ratio($a,$b){
  $a = norm_txt($a); $b = norm_txt($b);
  similar_text($a,$b,$p);
  return $p;
}

/* ===== Tablas mínimas ===== */
if (!has_table($conexion,'competidores_evento')) { exit('❌ Falta la tabla requerida: competidores_evento'); }
$hasPeleas = has_table($conexion,'peleas_evento');

/* ===== Columnas dinámicas ===== */
$colsPe = [];
if ($hasPeleas && ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`"))) {
  while($r=$q->fetch_assoc()){ $colsPe[strtolower($r['Field'])]=$r['Field']; }
  $q->close();
}
$C_AZUL   = $hasPeleas ? pick_col(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'], $colsPe) : null;
$C_ROJO   = $hasPeleas ? pick_col(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'], $colsPe) : null;
$C_EVENTO = $hasPeleas ? pick_col(['evento_id','id_evento','evento'], $colsPe) : null;
$C_FECHA  = $hasPeleas ? pick_col(['fecha','fecha_pelea','fpelea','created_at'], $colsPe) : null;
$C_GANADOR_PELEA = $hasPeleas ? pick_col(['ganador','resultado','winner'], $colsPe) : null;

/* competidores_evento */
$colsCe=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){ while($r=$q->fetch_assoc()){ $colsCe[strtolower($r['Field'])]=$r['Field']; } $q->close(); }

$C_ID        = pick_col(['id','competidor_id'], $colsCe);
$C_DNI       = pick_col(['dni','documento','doc'], $colsCe);
$C_NOMBRE    = pick_col(['nombre'], $colsCe);
$C_APELLIDO  = pick_col(['apellido'], $colsCe);
$C_ESC_NOM   = pick_col(['escuela_nombre','academia','gimnasio','equipo'], $colsCe);
$C_ESC_LOGO  = pick_col(['escuela_logo','logo_escuela','logo_academia'], $colsCe);
$C_FOTO      = pick_col(['foto_competidor','foto','avatar'], $colsCe);
$C_PESO_ID   = pick_col(['categoria_peso_id','peso_id'], $colsCe);
$C_MODAL_ID  = pick_col(['modalidad_id'], $colsCe);
$C_ACTIVO    = pick_col(['activo'], $colsCe);
$C_ESTADO    = pick_col(['estado'], $colsCe);
$C_WINS      = pick_col(['wins','win','w','ganadas','ganada'], $colsCe);
$C_LOSSES    = pick_col(['losses','loss','l','perdidas','perdida'], $colsCe);
$C_DRAWS     = pick_col(['draws','draw','d','empates','empate'], $colsCe);
$C_NC        = pick_col(['no_contest','nocontest','nc','no_decision','no-decision','sin_decision','sin-decision'], $colsCe);

if (!$C_ID) { exit('❌ No se detectó columna ID en competidores_evento.'); }
$scoreColsPresent = (bool)($C_WINS && $C_LOSSES && $C_DRAWS && $C_NC);

/* ===== Mayoría por pelea (resultados_jueces, opcional) ===== */
$winnerByFight=[];
$hasResJueces = has_table($conexion,'resultados_jueces') && has_col($conexion,'resultados_jueces','pelea_id') && has_col($conexion,'resultados_jueces','ganador');
if ($hasResJueces) {
  $sql="SELECT pelea_id,
    CASE
      WHEN SUM(ganador='azul')>SUM(ganador='rojo') AND SUM(ganador='azul')>SUM(ganador='empate') THEN 'azul'
      WHEN SUM(ganador='rojo')>SUM(ganador='azul') AND SUM(ganador='rojo')>SUM(ganador='empate') THEN 'rojo'
      WHEN SUM(ganador='empate')>SUM(ganador='azul') AND SUM(ganador='empate')>SUM(ganador='rojo') THEN 'empate'
      ELSE NULL
    END AS g
    FROM resultados_jueces
    WHERE estado IS NULL OR estado='enviado'
    GROUP BY pelea_id";
  if ($r=$conexion->query($sql)) { while($row=$r->fetch_assoc()){ $winnerByFight[(int)$row['pelea_id']] = $row['g'] ?: null; } $r->close(); }
}

/* ===== Traer peleas (para sumar resultados) ===== */
$peleas=[];
if ($hasPeleas && $C_AZUL && $C_ROJO) {
  $peleaCols="p.id AS pelea_id, p.".bt($C_AZUL)." AS azul_id, p.".bt($C_ROJO)." AS rojo_id";
  if ($C_FECHA)  $peleaCols.=", p.".bt($C_FECHA)." AS f";
  if ($C_EVENTO) $peleaCols.=", p.".bt($C_EVENTO)." AS evento_id";
  if ($C_GANADOR_PELEA) $peleaCols.=", p.".bt($C_GANADOR_PELEA)." AS ganador_pelea";
  if ($r=$conexion->query("SELECT $peleaCols FROM `peleas_evento` p")){
    while($row=$r->fetch_assoc()){
      $row['pelea_id']=(int)$row['pelea_id'];
      $row['azul_id'] =(int)$row['azul_id'];
      $row['rojo_id'] =(int)$row['rojo_id'];
      $g = $winnerByFight[$row['pelea_id']] ?? null;
      if ($g===null && isset($row['ganador_pelea'])) {
        $gg = strtolower(trim((string)$row['ganador_pelea']));
        if (in_array($gg,['azul','rojo','empate'],true)) $g = $gg;
      }
      $row['g'] = $g;
      $peleas[]=$row;
    }
    $r->close();
  }
}

/* ===== Traer fichas crudas ===== */
$selCe = "c.".bt($C_ID)." AS id";
$selCe.= $C_DNI     ? ", c.".bt($C_DNI)    ." AS dni"       : ", NULL AS dni";
$selCe.= $C_APELLIDO? ", c.".bt($C_APELLIDO)." AS apellido" : ", NULL AS apellido";
$selCe.= $C_NOMBRE  ? ", c.".bt($C_NOMBRE)  ." AS nombre"   : ", NULL AS nombre";
$selCe.= $C_ESC_NOM ? ", c.".bt($C_ESC_NOM) ." AS escuela"  : ", NULL AS escuela";
$selCe.= $C_ESC_LOGO? ", c.".bt($C_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo";
$selCe.= $C_FOTO    ? ", c.".bt($C_FOTO)    ." AS foto"     : ", NULL AS foto";
$selCe.= $C_MODAL_ID? ", c.".bt($C_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id";
$selCe.= $C_PESO_ID ? ", c.".bt($C_PESO_ID) ." AS peso_id"       : ", NULL AS peso_id";
if ($scoreColsPresent){
  $selCe.= ", CAST(c.".bt($C_WINS)   ." AS SIGNED) AS wins";
  $selCe.= ", CAST(c.".bt($C_LOSSES) ." AS SIGNED) AS losses";
  $selCe.= ", CAST(c.".bt($C_DRAWS)  ." AS SIGNED) AS draws";
  $selCe.= ", CAST(c.".bt($C_NC)     ." AS SIGNED) AS nc";
}
if ($C_ACTIVO) $selCe.= ", c.".bt($C_ACTIVO)." AS activo";
if ($C_ESTADO) $selCe.= ", c.".bt($C_ESTADO)." AS estado";

$joins=""; $selExtra="";
if (has_table($conexion,'modalidades_evento'))     { $joins.=" LEFT JOIN modalidades_evento mo ON mo.id = c.".bt($C_MODAL_ID);  $selExtra.=", mo.nombre AS modalidad"; }
else { $selExtra.=", NULL AS modalidad"; }
if (has_table($conexion,'categorias_peso_evento')) { $joins.=" LEFT JOIN categorias_peso_evento cp ON cp.id = c.".bt($C_PESO_ID); $selExtra.=", cp.nombre AS peso"; }
else { $selExtra.=", NULL AS peso"; }

$fichas=[];
if ($r=$conexion->query("SELECT $selCe $selExtra FROM `competidores_evento` c $joins ORDER BY c.".bt($C_ID)." ASC")){
  while($row=$r->fetch_assoc()){
    $id=(int)$row['id'];
    $fichas[$id]=[
      'id'=>$id,
      'dni'=> $row['dni'] ?? null,
      'apellido'=>$row['apellido'] ?? '',
      'nombre'  =>$row['nombre'] ?? '',
      'escuela' =>$row['escuela'] ?? '',
      'escuela_logo'=>$row['escuela_logo'] ?? '',
      'foto'    =>$row['foto'] ?? '',
      'modalidad'=>$row['modalidad'] ?? '',
      'peso'    =>$row['peso'] ?? '',
      'W_base'=> (int)($row['wins']   ?? 0),
      'L_base'=> (int)($row['losses'] ?? 0),
      'D_base'=> (int)($row['draws']  ?? 0),
      'NC_base'=> (int)($row['nc']    ?? 0),
      'activo'   =>$row['activo'] ?? null,
      'estado'   =>$row['estado'] ?? null,
    ];
  }
  $r->close();
}

/* ===== Unificación de duplicados ===== */
$grupos = [];         // key => data unificada (datos de la ficha más nueva + acumuladores)
$idsPorGrupo = [];    // key => [ids...]
$indexApellidoDNI = []; // apellido normalizado => [dni válidos]
$indexApellidoGrupoConDNI = []; // apellido => key de grupo con DNI (si único)

/* 1) Primero crear grupos por DNI válido y preparar índices por apellido */
foreach ($fichas as $f) {
  $dniNorm = normalize_dni($f['dni'] ?? '');
  $apeNorm = norm_txt($f['apellido'] ?? '');
  if ($dniNorm) {
    $key = 'dni:'.$dniNorm;
    if (!isset($grupos[$key])) {
      $grupos[$key] = [
        'key'=>$key,'dni'=>$dniNorm,'id_base'=>$f['id'],
        'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],
        'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
        'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        // acumuladores de score: SUMA de todas las fichas del grupo
        'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
        'badge'=>(isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
      ];
      $idsPorGrupo[$key]=[$f['id']];
    } else {
      // usar el más nuevo para mostrar datos
      if ($f['id'] > $grupos[$key]['id_base']) {
        $grupos[$key]['id_base']=$f['id'];
        foreach (['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src = ($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??'');
          if (!empty($src)) $grupos[$key][$fld]=$src;
        }
      }
      // acumular score
      $grupos[$key]['W_acc'] += (int)$f['W_base'];
      $grupos[$key]['L_acc'] += (int)$f['L_base'];
      $grupos[$key]['D_acc'] += (int)$f['D_base'];
      $grupos[$key]['NC_acc']+= (int)$f['NC_base'];

      if (!empty($f['escuela'])) $grupos[$key]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$key][]=$f['id'];
    }
    if ($apeNorm!=='') $indexApellidoDNI[$apeNorm][$dniNorm]=true;
  }
}

/* Si para un apellido hay un único DNI canónico, guardamos referencia rápida */
foreach ($indexApellidoDNI as $ape=>$dniSet){
  $dnis = array_keys($dniSet);
  if (count($dnis)===1){
    $dni = $dnis[0];
    $indexApellidoGrupoConDNI[$ape] = 'dni:'.$dni;
  }
}

/* 2) Fichas SIN DNI válido: unir por apellido + nombre (Levenshtein + Metaphone) o por escuela parecida */
foreach ($fichas as $f) {
  $dniNorm = normalize_dni($f['dni'] ?? '');
  if ($dniNorm) continue;

  $ape = (string)($f['apellido'] ?? '');
  $nom = (string)($f['nombre'] ?? '');
  $apeNorm = norm_txt($ape);
  $nomNorm = norm_txt($nom);

  $attached = false;

  // 2.a) Grupo único con DNI para este apellido → comparar nombre (lev+metaphone) o escuela ~80%
  if ($apeNorm !== '' && isset($indexApellidoGrupoConDNI[$apeNorm])) {
    $k = $indexApellidoGrupoConDNI[$apeNorm];
    $g = $grupos[$k];

    $nombreBase = first_name($g['nombre'] ?? '');
    $nombreNuevo= first_name($nom);

    $escG = $g['escuela'] ?? '';
    $escN = $f['escuela'] ?? '';
    $escLike = like_ratio($escG,$escN);

    if ( ($nombreBase!=='' && $nombreNuevo!=='' && is_similar_name($nombreBase,$nombreNuevo,2)) || ($escG!=='' && $escN!=='' && $escLike>=80) ) {
      // datos visibles: ficha más nueva
      if ($f['id'] > $g['id_base']) {
        $grupos[$k]['id_base']=$f['id'];
        foreach (['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src = ($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??'');
          if (!empty($src)) $grupos[$k][$fld]=$src;
        }
      } else {
        if (!$grupos[$k]['escuela']   && !empty($f['escuela']))      $grupos[$k]['escuela'] = $f['escuela'];
        if (!$grupos[$k]['logo']      && !empty($f['escuela_logo'])) $grupos[$k]['logo'] = $f['escuela_logo'];
        if (!$grupos[$k]['foto']      && !empty($f['foto']))         $grupos[$k]['foto'] = $f['foto'];
        if (!$grupos[$k]['modalidad'] && !empty($f['modalidad']))    $grupos[$k]['modalidad'] = $f['modalidad'];
        if (!$grupos[$k]['peso']      && !empty($f['peso']))         $grupos[$k]['peso'] = $f['peso'];
      }
      // acumular score
      $grupos[$k]['W_acc'] += (int)$f['W_base'];
      $grupos[$k]['L_acc'] += (int)$f['L_base'];
      $grupos[$k]['D_acc'] += (int)$f['D_base'];
      $grupos[$k]['NC_acc']+= (int)$f['NC_base'];

      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id'];
      $attached = true;
    }
  }

  if ($attached) continue;

  /* 2.b) Agrupar por (Apellido+Nombre) con tolerancia (Levenshtein + Metaphone en ambos) */
  $nameKey = normalize_name_key($ape,$nom);
  if ($nameKey) {
    $k = 'nx:'.$nameKey;
    // buscar algún grupo nx:* muy similar en apellido y nombre (lev o metaphone)
    $foundKey = null;
    foreach ($grupos as $gk=>$g){
      if (strpos($gk,'nx:')===0){
        $okApe = is_similar_name($g['apellido'] ?? '', $ape, 2);
        $okNom = is_similar_name($g['nombre'] ?? '', $nom, 2);
        if ($okApe && $okNom){ $foundKey=$gk; break; }
      }
    }
    if ($foundKey!==null) $k = $foundKey;

    if (!isset($grupos[$k])){
      $grupos[$k]=[
        'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
        'apellido'=>$ape,'nombre'=>$nom,
        'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
        'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
        'badge'=>(isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
      ];
      $idsPorGrupo[$k]=[$f['id']];
    } else {
      if ($f['id'] > $grupos[$k]['id_base']) {
        $grupos[$k]['id_base']=$f['id'];
        foreach (['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src = ($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??'');
          if (!empty($src)) $grupos[$k][$fld]=$src;
        }
      } else {
        if (!$grupos[$k]['escuela']   && !empty($f['escuela']))      $grupos[$k]['escuela'] = $f['escuela'];
        if (!$grupos[$k]['logo']      && !empty($f['escuela_logo'])) $grupos[$k]['logo'] = $f['escuela_logo'];
        if (!$grupos[$k]['foto']      && !empty($f['foto']))         $grupos[$k]['foto'] = $f['foto'];
        if (!$grupos[$k]['modalidad'] && !empty($f['modalidad']))    $grupos[$k]['modalidad'] = $f['modalidad'];
        if (!$grupos[$k]['peso']      && !empty($f['peso']))         $grupos[$k]['peso'] = $f['peso'];
      }
      // acumuladores
      $grupos[$k]['W_acc'] += (int)$f['W_base'];
      $grupos[$k]['L_acc'] += (int)$f['L_base'];
      $grupos[$k]['D_acc'] += (int)$f['D_base'];
      $grupos[$k]['NC_acc']+= (int)$f['NC_base'];

      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id'];
    }
  } else {
    // sin datos para agrupar: queda por ID propio
    $k = 'id:'.$f['id'];
    $grupos[$k]=[
      'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
      'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],
      'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
      'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
      'badge'=>(isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $idsPorGrupo[$k]=[$f['id']];
  }
}

/* ===== Preparar lista final (antes de sumar peleas) ===== */
$global = [];
$mapIdToKey = [];
foreach ($grupos as $key => $g) {
  $nombre = trim(($g['apellido'] ?? '').' '.($g['nombre'] ?? ''));
  $escuelasUnicas = array_values(array_unique(array_filter($g['escuelas'])));
  $escuelaFinal = $g['escuela'] ?: ($escuelasUnicas ? end($escuelasUnicas) : '');

  $global[$key] = [
    'key'     => $key,
    'dni'     => $g['dni'],
    'id_base' => $g['id_base'],
    'nombre'  => ($nombre !== '') ? $nombre : '—',
    'escuela' => $escuelaFinal,
    'logo'    => $g['logo'] ?: '',
    'foto'    => $g['foto'] ?: '',
    'modalidad' => $g['modalidad'] ?: '',
    'peso'      => $g['peso'] ?: '',
    // empezar con la SUMA acumulada de fichas del grupo
    'W'       => (int)$g['W_acc'],
    'L'       => (int)$g['L_acc'],
    'D'       => (int)$g['D_acc'],
    'NC'      => (int)$g['NC_acc'],
    'badge'   => $g['badge'] ?? ''
  ];
  $mapIdToKey[$g['id_base']] = $key; // peleas del id_base (si hay score base por ficha)
}

/* ===== Sumar peleas ===== */
if ($peleas){
  if ($scoreColsPresent){
    // si existen columnas de score en la tabla, sumamos peleas SOLO a id_base de cada grupo
    foreach($peleas as $p){
      $g=$p['g']; if ($g===null) continue;
      foreach (['azul_id','rojo_id'] as $side){
        $cid = (int)$p[$side];
        if (!isset($mapIdToKey[$cid])) continue;
        $k = $mapIdToKey[$cid];
        if (!isset($global[$k])) continue;
        if     ($g==='azul' && $cid===(int)$p['azul_id']) $global[$k]['W']++;
        elseif ($g==='azul' && $cid===(int)$p['rojo_id']) $global[$k]['L']++;
        elseif ($g==='rojo' && $cid===(int)$p['rojo_id']) $global[$k]['W']++;
        elseif ($g==='rojo' && $cid===(int)$p['azul_id']) $global[$k]['L']++;
        elseif ($g==='empate')                            $global[$k]['D']++;
      }
    }
  } else {
    // si NO hay columnas de score, sumamos peleas a TODO el grupo (cualquier id dentro del grupo)
    foreach($peleas as $p){
      $g=$p['g']; if ($g===null) continue;
      foreach ($idsPorGrupo as $k => $idsList){
        $az = (int)$p['azul_id']; $ro = (int)$p['rojo_id'];
        if (in_array($az,$idsList,true) || in_array($ro,$idsList,true)){
          if     ($g==='empate'){ $global[$k]['D']++; }
          elseif ($g==='azul'){
            $global[$k]['W'] += in_array($az,$idsList,true) ? 1 : 0;
            $global[$k]['L'] += in_array($ro,$idsList,true) ? 1 : 0;
          } elseif ($g==='rojo'){
            $global[$k]['W'] += in_array($ro,$idsList,true) ? 1 : 0;
            $global[$k]['L'] += in_array($az,$idsList,true) ? 1 : 0;
          }
        }
      }
    }
  }
}

/* ===== Filtros/Orden ===== */
$busca = trim((string)($_GET['q'] ?? ''));
$orden = (string)($_GET['sort'] ?? 'wins'); // wins|name|gym
$lista = array_values($global);

if ($busca!==''){
  $q = mb_strtolower($busca,'UTF-8');
  $lista = array_values(array_filter($lista,function($c) use($q){
    $s = mb_strtolower(($c['nombre'].' '.$c['escuela']), 'UTF-8');
    return strpos($s,$q)!==false;
  }));
}

usort($lista,function($a,$b) use($orden){
  if ($orden==='name'){
    return strnatcasecmp($a['nombre'],$b['nombre']);
  } elseif ($orden==='gym'){
    return strnatcasecmp($a['escuela'] ?? '', $b['escuela'] ?? '');
  } else {
    $da = $b['W'] <=> $a['W']; if ($da) return $da;
    $db = ($a['L'] <=> $b['L']); if ($db) return $db;
    return strnatcasecmp($a['nombre'],$b['nombre']);
  }
});

/* ===== Placeholders (SVG embebidos) + rutas locales ===== */
$LOCAL_USER = 'assets/img/placeholder_user.png';
$LOCAL_GYM  = 'assets/img/placeholder_gym.png';

$SVG_USER = 'data:image/svg+xml;utf8,'.rawurlencode(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" fill="#0b0d12"/><circle cx="64" cy="46" r="26" fill="#2a3450"/><path d="M16 120c0-26 21-36 48-36s48 10 48 36" fill="#2a3450"/></svg>'
);
$SVG_GYM = 'data:image/svg+xml;utf8,'.rawurlencode(
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128"><rect width="128" height="128" fill="#0b0d12"/><rect x="18" y="54" width="92" height="20" fill="#2a3450"/><rect x="8" y="48" width="20" height="32" fill="#2a3450"/><rect x="100" y="48" width="20" height="32" fill="#2a3450"/></svg>'
);

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📊 Competidores — Global (Unificado)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- CSS UNIFICADO -->
  <link rel="stylesheet" href="estilo_unificado.css?v=6">
  <script>
    // Fallback en cadena: src original → archivo local → SVG embebido
    function phChain(img, localSrc, svgData) {
      const step = img.dataset.phStep || '0';
      if (step === '0') {
        img.dataset.phStep = '1';
        img.src = localSrc;
      } else {
        img.onerror = null;
        img.src = svgData;
      }
    }
  </script>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <div class="page-card">
    <div class="encabezado" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
      <h2 style="margin:0">📊 Competidores (unificados por DNI o Nombre+Apellido)</h2>
      <a class="btn" href="index.php">Volver</a>
    </div>

    <?php if (!$scoreColsPresent): ?>
      <div class="msg warn" style="margin-bottom:12px">
        No se detectaron las columnas <b>wins/losses/draws/no_contest</b> en <code>competidores_evento</code>.
        Se suman resultados por peleas de todos los IDs unificados.
      </div>
    <?php else: ?>
      <div class="msg" style="margin-bottom:12px">
        La puntuación muestra la <b>suma</b> de W/L/D/NC de las fichas unificadas, más las peleas del evento actual (id base).
      </div>
    <?php endif; ?>

    <form method="get" class="toolbar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
      <input class="input" type="text" name="q" placeholder="Buscar por nombre o academia…" value="<?= h($busca) ?>" style="min-width:220px">
      <select class="input" name="sort" aria-label="Orden">
        <option value="wins" <?= $orden==='wins'?'selected':''; ?>>Más ganadas</option>
        <option value="name" <?= $orden==='name'?'selected':''; ?>>Nombre</option>
        <option value="gym"  <?= $orden==='gym'?'selected':'';  ?>>Academia</option>
      </select>
      <button class="btn" type="submit">Aplicar</button>
    </form>

    <div class="table-wrap">
      <table aria-label="Listado global unificado de competidores">
        <thead>
          <tr>
            <th style="text-align:left">Competidor</th>
            <th style="text-align:left">Academia</th>
            <th>Modalidad</th>
            <th>Peso</th>
            <th>W</th>
            <th>L</th>
            <th>D</th>
            <th>NC</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$lista): ?>
          <tr><td colspan="8" class="muted" style="text-align:center">Sin registros.</td></tr>
        <?php else: foreach($lista as $c):
          $nombre = trim($c['nombre']) ?: '—';
          $foto = $c['foto'] ?: $LOCAL_USER; // primer intento: local
          $logo = $c['logo'] ?: $LOCAL_GYM;  // primer intento: local
          $perfilUrl = !empty($c['dni'])
            ? 'ver_competidor_ranking.php?dni='.urlencode($c['dni'])
            : 'ver_competidor_ranking.php?id='.(int)$c['id_base'];
        ?>
          <tr>
            <td style="text-align:left">
              <a class="rowlink" href="<?= h($perfilUrl) ?>" style="display:flex;gap:10px;align-items:center;color:inherit;text-decoration:none">
                <img class="pfp" src="<?= h($foto) ?>" alt="foto"
                     style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid var(--stroke);background:#0b0d12"
                     onerror="phChain(this,'<?= h($LOCAL_USER) ?>','<?= h($SVG_USER) ?>')">
                <div>
                  <div style="font-weight:800"><?= h($nombre) ?><?= h($c['badge'] ?? '') ?></div>
                  <?php if (!empty($c['dni'])): ?>
                    <div class="muted" style="font-size:12px">DNI: <?= h($c['dni']) ?> • Ficha base: <?= (int)$c['id_base'] ?></div>
                  <?php else: ?>
                    <div class="muted" style="font-size:12px">Ficha base: <?= (int)$c['id_base'] ?></div>
                  <?php endif; ?>
                </div>
              </a>
            </td>
            <td style="text-align:left">
              <div style="display:flex;gap:8px;align-items:center">
                <img class="logo" src="<?= h($logo) ?>" alt="logo"
                     style="width:40px;height:40px;object-fit:contain;border-radius:8px;border:1px solid var(--stroke);background:#0b0d12"
                     onerror="phChain(this,'<?= h($LOCAL_GYM) ?>','<?= h($SVG_GYM) ?>')">
                <div><?= h($c['escuela'] ?: '—') ?></div>
              </div>
            </td>
            <td><?= h($c['modalidad'] ?: '—') ?></td>
            <td><?= h($c['peso'] ?: '—') ?></td>
            <td><span class="pill"><?= (int)$c['W'] ?></span></td>
            <td><span class="pill"><?= (int)$c['L'] ?></span></td>
            <td><span class="pill"><?= (int)$c['D'] ?></span></td>
            <td><span class="pill"><?= (int)$c['NC'] ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p class="muted" style="margin-top:8px">
      • Se prioriza <b>DNI válido (8 dígitos)</b>. Registros sin DNI se adhieren por <b>Apellido+Nombre</b> con tolerancia (Levenshtein + Metaphone) o por <b>escuela muy parecida</b>.<br>
      • Si aparecen personas distintas con el mismo apellido pero nombres distintos (p. ej. Roberto vs Daniel), <u>no</u> se mezclan salvo que compartan el mismo DNI.<br>
      • La ficha visible es la <b>más nueva</b> del grupo; el score es la <b>suma</b> de todas las fichas unificadas + peleas.
    </p>
  </div>
</div>
</body>
</html>
