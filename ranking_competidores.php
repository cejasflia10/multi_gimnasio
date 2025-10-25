<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$DEBUG = (isset($_GET['debug']) && $_GET['debug']=='1');

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
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function norm_txt($s){ $s = trim((string)$s); $s = preg_replace('~\s+~',' ',$s); return $s; }

/* ===== Flash ===== */
$flash_ok    = isset($_SESSION['flash_ok']) ? $_SESSION['flash_ok'] : '';
$flash_error = isset($_SESSION['flash_error']) ? $_SESSION['flash_error'] : '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* ===== Tablas mínimas ===== */
if (!has_table($conexion,'competidores_evento')) { exit('❌ Falta la tabla requerida: competidores_evento'); }
$hasPeleas = has_table($conexion,'peleas_evento');
if (!has_table($conexion,'resultados_combates')) { exit('❌ Falta la tabla requerida: resultados_combates'); }

/* ===== Columnas dinámicas en peleas_evento (para vincular IDs y evento) ===== */
$colsPe = array();
if ($hasPeleas && ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`"))) {
  while($r=$q->fetch_assoc()){ $colsPe[strtolower($r['Field'])]=$r['Field']; }
  $q->close();
}
$C_AZUL     = $hasPeleas ? pick_col(array('competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'), $colsPe) : null;
$C_ROJO     = $hasPeleas ? pick_col(array('competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'), $colsPe) : null;
$C_EVENTO_PE= $hasPeleas ? pick_col(array('evento_id','id_evento','evento'), $colsPe) : null;
$C_FECHA_PE = $hasPeleas ? pick_col(array('fecha','fecha_pelea','fpelea','created_at'), $colsPe) : null;
$C_GAN_PE   = $hasPeleas ? pick_col(array('ganador','resultado','winner'), $colsPe) : null;

/* ===== Columnas dinámicas en competidores_evento ===== */
$colsCe=array(); if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){ while($r=$q->fetch_assoc()){ $colsCe[strtolower($r['Field'])]=$r['Field']; } $q->close(); }
$CE_ID        = pick_col(array('id','competidor_id'), $colsCe);
$CE_DNI       = pick_col(array('dni','documento','doc'), $colsCe);
$CE_NOMBRE    = pick_col(array('nombre','nombres','display_name','nombre_completo','nombreyapellido'), $colsCe); if(!$CE_NOMBRE) $CE_NOMBRE='nombre';
$CE_APELLIDO  = pick_col(array('apellido','apellidos'), $colsCe); if(!$CE_APELLIDO) $CE_APELLIDO='apellido';
$CE_ESC_NOM   = pick_col(array('escuela_nombre','academia','gimnasio','equipo'), $colsCe);
$CE_ESC_LOGO  = pick_col(array('escuela_logo','logo_escuela','logo_academia'), $colsCe);
$CE_FOTO      = pick_col(array('foto_competidor','foto','avatar'), $colsCe);
$CE_PESO_ID   = pick_col(array('categoria_peso_id','peso_id'), $colsCe);
$CE_MODAL_ID  = pick_col(array('modalidad_id'), $colsCe);
$CE_WINS      = pick_col(array('wins','win','w','ganadas'), $colsCe);
$CE_LOSSES    = pick_col(array('losses','loss','l','perdidas'), $colsCe);
$CE_DRAWS     = pick_col(array('draws','draw','d','empates'), $colsCe);
$CE_NC        = pick_col(array('no_contest','nocontest','nc','sin_decision'), $colsCe);

/* ===== Sesión de evento ===== */
$evento_id_actual = isset($_SESSION['evento_id_actual']) ? (int)$_SESSION['evento_id_actual'] : null;

/* =========================================================
   POST: guardar competidor / resultado
   ========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = isset($_POST['action']) ? $_POST['action'] : '';

  if ($action === 'guardar_competidor') {
    $id = toIntOrNull(isset($_POST['id'])?$_POST['id']:'');
    if (!$id || !$CE_ID) { $_SESSION['flash_error'] = 'Faltan datos: id competidor.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    $sets = array(); $types=''; $vals=array();
    $map = array(
      $CE_APELLIDO => isset($_POST['apellido']) ? $_POST['apellido'] : null,
      $CE_NOMBRE   => isset($_POST['nombre']) ? $_POST['nombre'] : null,
    );
    if ($CE_ESC_NOM)  $map[$CE_ESC_NOM]  = $_POST['escuela_nombre'] ?? null;
    if ($CE_ESC_LOGO) $map[$CE_ESC_LOGO] = $_POST['escuela_logo'] ?? null;
    if ($CE_FOTO)     $map[$CE_FOTO]     = $_POST['foto_competidor'] ?? null;
    if ($CE_MODAL_ID) $map[$CE_MODAL_ID] = toIntOrNull($_POST['modalidad_id'] ?? '');
    if ($CE_PESO_ID)  $map[$CE_PESO_ID]  = toIntOrNull($_POST['peso_id'] ?? '');

    foreach($map as $col=>$val){
      if ($col===null || $val===null) continue;
      $sets[] = bt($col).'=?';
      if (is_int($val)) { $types.='i'; $vals[]=$val; } else { $types.='s'; $vals[]=norm_txt($val); }
    }

    if ($sets){
      $sql = "UPDATE `competidores_evento` SET ".implode(',',$sets)." WHERE ".bt($CE_ID)."=?";
      $types.='i'; $vals[]=$id;
      $st = $conexion->prepare($sql);
      if ($st){
        $bind = array($types); foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
        call_user_func_array(array($st,'bind_param'),$bind);
        if ($st->execute()){ $_SESSION['flash_ok'] = 'Competidor actualizado correctamente.'; }
        else { $_SESSION['flash_error'] = 'Error al actualizar competidor: '.$conexion->error; }
        $st->close();
      } else { $_SESSION['flash_error'] = 'No se pudo preparar actualización de competidor: '.$conexion->error; }
    } else {
      $_SESSION['flash_error'] = 'No hay cambios para aplicar.';
    }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  if ($action === 'guardar_resultado') {
    $pelea_id = toIntOrNull($_POST['pelea_id'] ?? '');
    $ganador_color = strtolower(trim((string)($_POST['ganador_color'] ?? '')));
    $ganador_id = toIntOrNull($_POST['ganador_id'] ?? '');
    $metodo = substr((string)($_POST['metodo'] ?? ''),0,10);
    $detalle = substr((string)($_POST['detalle'] ?? ''),0,255);
    $p_rojo = toIntOrNull($_POST['puntos_rojo'] ?? ''); if ($p_rojo===null) $p_rojo=0;
    $p_azul = toIntOrNull($_POST['puntos_azul'] ?? ''); if ($p_azul===null) $p_azul=0;

    $evento_id = toIntOrNull($_POST['evento_id'] ?? ''); if (!$evento_id) $evento_id = $evento_id_actual;
    if (!$pelea_id || !$evento_id || !in_array($ganador_color,array('rojo','azul','empate'),true)) {
      $_SESSION['flash_error'] = 'Faltan datos: pelea_id, evento_id o ganador_color inválido.';
      header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    if (!$ganador_id && $hasPeleas && $C_ROJO && $C_AZUL) {
      $sql = "SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('i',$pelea_id); $st->execute();
        $r=$st->get_result()->fetch_assoc(); $st->close();
        if ($r){ if ($ganador_color==='rojo') $ganador_id = (int)$r['rid']; elseif ($ganador_color==='azul') $ganador_id = (int)$r['aid']; }
      }
    }

    $conexion->begin_transaction();
    try {
      // Revertir previo
      $prev = null;
      $sqlPrev = "SELECT ganador_color FROM `resultados_combates` WHERE pelea_id=? LIMIT 1";
      if ($st=$conexion->prepare($sqlPrev)){
        $st->bind_param('i',$pelea_id); $st->execute(); $res=$st->get_result(); $prev=$res?$res->fetch_assoc():null; $st->close();
      } else { throw new Exception('No se pudo preparar lectura de resultado previo.'); }

      if ($prev && $hasPeleas && $C_ROJO && $C_AZUL && $CE_WINS && $CE_LOSSES && $CE_DRAWS) {
        $sqlPE = "SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1";
        $rid=null; $aid=null;
        if ($st=$conexion->prepare($sqlPE)){
          $st->bind_param('i',$pelea_id); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
          if ($row){ $rid=(int)$row['rid']; $aid=(int)$row['aid']; }
        }
        if ($rid && $aid){
          if ($prev['ganador_color']==='empate'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_DRAWS)."=".bt($CE_DRAWS)."-1 WHERE ".bt($CE_ID)." IN ($rid,$aid)");
          } elseif ($prev['ganador_color']==='rojo'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_WINS)."=".bt($CE_WINS)."-1 WHERE ".bt($CE_ID)."=".$rid);
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_LOSSES)."=".bt($CE_LOSSES)."-1 WHERE ".bt($CE_ID)."=".$aid);
          } elseif ($prev['ganador_color']==='azul'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_WINS)."=".bt($CE_WINS)."-1 WHERE ".bt($CE_ID)."=".$aid);
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_LOSSES)."=".bt($CE_LOSSES)."-1 WHERE ".bt($CE_ID)."=".$rid);
          }
        }
      }

      // Upsert resultado
      $colsRc = array();
      if ($q=$conexion->query("SHOW COLUMNS FROM `resultados_combates`")){ while($r=$q->fetch_assoc()){ $colsRc[strtolower($r['Field'])]=$r['Field']; } $q->close(); }
      $RC_PELEA_ID  = $colsRc['pelea_id'] ?? 'pelea_id';
      $RC_EVENTO_ID = $colsRc['evento_id'] ?? 'evento_id';
      $RC_GCOLOR    = $colsRc['ganador_color'] ?? 'ganador_color';
      $RC_GID       = $colsRc['ganador_id'] ?? 'ganador_id';
      $RC_METODO    = $colsRc['metodo'] ?? 'metodo';
      $RC_DETALLE   = $colsRc['detalle'] ?? 'detalle';
      $RC_PAZUL     = $colsRc['puntos_azul'] ?? 'puntos_azul';
      $RC_PROJO     = $colsRc['puntos_rojo'] ?? 'puntos_rojo';

      $sqlUp = "INSERT INTO `resultados_combates`
        (".bt($RC_PELEA_ID).",".bt($RC_EVENTO_ID).",".bt($RC_GCOLOR).",".bt($RC_GID).",".bt($RC_METODO).",".bt($RC_DETALLE).",".bt($RC_PROJO).",".bt($RC_PAZUL).")
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          ".bt($RC_EVENTO_ID)."=VALUES(".bt($RC_EVENTO_ID)."),
          ".bt($RC_GCOLOR)."=VALUES(".bt($RC_GCOLOR)."),
          ".bt($RC_GID)."=VALUES(".bt($RC_GID)."),
          ".bt($RC_METODO)."=VALUES(".bt($RC_METODO)."),
          ".bt($RC_DETALLE)."=VALUES(".bt($RC_DETALLE)."),
          ".bt($RC_PROJO)."=VALUES(".bt($RC_PROJO)."),
          ".bt($RC_PAZUL)."=VALUES(".bt($RC_PAZUL).")";
      if ($st=$conexion->prepare($sqlUp)){
        $st->bind_param('iissssii',$pelea_id,$evento_id,$ganador_color,$ganador_id,$metodo,$detalle,$p_rojo,$p_azul);
        if (!$st->execute()){ throw new Exception('Error al guardar resultado: '.$conexion->error); }
        $st->close();
      } else { throw new Exception('No se pudo preparar UPSERT de resultado: '.$conexion->error); }

      // Aplicar impacto nuevo (en columnas si existen)
      if ($hasPeleas && $C_ROJO && $C_AZUL && $CE_WINS && $CE_LOSSES && $CE_DRAWS) {
        $sqlPE = "SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1";
        $rid=null; $aid=null;
        if ($st=$conexion->prepare($sqlPE)){
          $st->bind_param('i',$pelea_id); $st->execute(); $row=$st->get_result()->fetch_assoc(); $st->close();
          if ($row){ $rid=(int)$row['rid']; $aid=(int)$row['aid']; }
        }
        if ($rid && $aid){
          if ($ganador_color==='empate'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_DRAWS)."=".bt($CE_DRAWS)."+1 WHERE ".bt($CE_ID)." IN ($rid,$aid)");
          } elseif ($ganador_color==='rojo'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_WINS)."=".bt($CE_WINS)."+1 WHERE ".bt($CE_ID)."=".$rid);
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_LOSSES)."=".bt($CE_LOSSES)."+1 WHERE ".bt($CE_ID)."=".$aid);
          } elseif ($ganador_color==='azul'){
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_WINS)."=".bt($CE_WINS)."+1 WHERE ".bt($CE_ID)."=".$aid);
            $conexion->query("UPDATE `competidores_evento` SET ".bt($CE_LOSSES)."=".bt($CE_LOSSES)."+1 WHERE ".bt($CE_ID)."=".$rid);
          }
        }
      }

      $conexion->commit();
      $_SESSION['flash_ok'] = 'Resultado guardado correctamente.';
    } catch (Throwable $e) {
      $conexion->rollback();
      $_SESSION['flash_error'] = 'No se pudo guardar el resultado: '.$e->getMessage();
    }

    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
}

/* =========================================================
   A partir de acá: Unificación + listado
   ========================================================= */

/* ===== Normalización y similitud ===== */
function normalize_dni($dni){
  $d = preg_replace('~\D+~','',(string)$dni);
  return (strlen($d)===8) ? $d : null;
}
function strip_accents($str){
  $str = (string)$str;
  $rep = array('Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n');
  $str = strtr($str,$rep);
  $x = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$str);
  if ($x !== false) $str = $x;
  return $str;
}
function norm_txt2($s){
  $s = mb_strtolower(strip_accents(trim((string)$s)),'UTF-8');
  $s = preg_replace('~\s+~',' ',$s);
  return $s;
}
function first_name($full){
  $t = trim((string)$full);
  $t = preg_replace('~\s+~',' ',$t);
  $parts = explode(' ', $t);
  return norm_txt2(isset($parts[0])?$parts[0]:'');
}
function is_similar_lev($a,$b,$max=2){
  $a = norm_txt2($a); $b = norm_txt2($b);
  if ($a === '' || $b === '') return false;
  if ($a === $b) return true;
  return levenshtein($a,$b) <= $max;
}
function metaphone_similar($a,$b,$max=1){
  $a = norm_txt2($a); $b = norm_txt2($b);
  if ($a === '' || $b === '') return false;
  $ma = metaphone($a); $mb = metaphone($b);
  if ($ma === '' || $mb === '') return false;
  if ($ma === $mb) return true;
  return levenshtein($ma,$mb) <= $max;
}
function is_similar_name($a,$b,$maxLev=2){
  return is_similar_lev($a,$b,$maxLev) || metaphone_similar($a,$b,1);
}

/* ===== Resultados de jueces (opcional) ===== */
$winnerByFight=array();
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
  if ($r=$conexion->query($sql)) { while($row=$r->fetch_assoc()){ $winnerByFight[(int)$row['pelea_id']] = $row['g'] ? $row['g'] : null; } $r->close(); }
}

/* ===== Traer peleas (opcional) ===== */
$peleas=array();
if ($hasPeleas && $C_AZUL && $C_ROJO) {
  $peleaCols="p.id AS pelea_id, p.".bt($C_AZUL)." AS azul_id, p.".bt($C_ROJO)." AS rojo_id";
  if ($C_FECHA_PE)    $peleaCols.=", p.".bt($C_FECHA_PE)." AS f";
  if ($C_EVENTO_PE)   $peleaCols.=", p.".bt($C_EVENTO_PE)." AS evento_id";
  if ($C_GAN_PE)      $peleaCols.=", p.".bt($C_GAN_PE)." AS ganador_pelea";
  if ($r=$conexion->query("SELECT $peleaCols FROM `peleas_evento` p")){
    while($row=$r->fetch_assoc()){
      $row['pelea_id']=(int)$row['pelea_id'];
      $row['azul_id'] =(int)$row['azul_id'];
      $row['rojo_id'] =(int)$row['rojo_id'];
      $g = isset($winnerByFight[$row['pelea_id']]) ? $winnerByFight[$row['pelea_id']] : null;
      if ($g===null && isset($row['ganador_pelea'])) {
        $gg = strtolower(trim((string)$row['ganador_pelea']));
        if (in_array($gg,array('azul','rojo','empate'),true)) $g = $gg;
      }
      $row['g'] = $g;
      $peleas[]=$row;
    }
    $r->close();
  }
}

/* ===== Traer fichas crudas de competidores_evento ===== */
$selCe = "c.".bt($CE_ID)." AS id";
$selCe.= $CE_DNI       ? ", c.".bt($CE_DNI)    ." AS dni"       : ", NULL AS dni";
$selCe.= $CE_APELLIDO  ? ", c.".bt($CE_APELLIDO)." AS apellido" : ", NULL AS apellido";
$selCe.= $CE_NOMBRE    ? ", c.".bt($CE_NOMBRE)  ." AS nombre"   : ", NULL AS nombre";
$selCe.= $CE_ESC_NOM   ? ", c.".bt($CE_ESC_NOM) ." AS escuela"  : ", NULL AS escuela";
$selCe.= $CE_ESC_LOGO  ? ", c.".bt($CE_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo";
$selCe.= $CE_FOTO      ? ", c.".bt($CE_FOTO)    ." AS foto"     : ", NULL AS foto";
$selCe.= $CE_MODAL_ID  ? ", c.".bt($CE_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id";
$selCe.= $CE_PESO_ID   ? ", c.".bt($CE_PESO_ID) ." AS peso_id"       : ", NULL AS peso_id";
if ($CE_WINS)   $selCe.= ", CAST(c.".bt($CE_WINS)   ." AS SIGNED) AS wins";
if ($CE_LOSSES) $selCe.= ", CAST(c.".bt($CE_LOSSES) ." AS SIGNED) AS losses";
if ($CE_DRAWS)  $selCe.= ", CAST(c.".bt($CE_DRAWS)  ." AS SIGNED) AS draws";
if ($CE_NC)     $selCe.= ", CAST(c.".bt($CE_NC)     ." AS SIGNED) AS nc";

$joins=""; $selExtra="";
if (has_table($conexion,'modalidades_evento') && $CE_MODAL_ID)     { $joins.=" LEFT JOIN modalidades_evento mo ON mo.id = c.".bt($CE_MODAL_ID);  $selExtra.=", mo.nombre AS modalidad"; }
else { $selExtra.=", NULL AS modalidad"; }
if (has_table($conexion,'categorias_peso_evento') && $CE_PESO_ID) { $joins.=" LEFT JOIN categorias_peso_evento cp ON cp.id = c.".bt($CE_PESO_ID); $selExtra.=", cp.nombre AS peso"; }
else { $selExtra.=", NULL AS peso"; }

$fichas=array();
if ($r=$conexion->query("SELECT $selCe $selExtra FROM `competidores_evento` c $joins ORDER BY c.".bt($CE_ID)." ASC")){
  while($row=$r->fetch_assoc()){
    $id=(int)$row['id'];
    $fichas[$id]=array(
      'id'=>$id,
      'dni'=> $row['dni'] ?? null,
      'apellido'=> $row['apellido'] ?? '',
      'nombre'  => $row['nombre'] ?? '',
      'escuela' => $row['escuela'] ?? '',
      'escuela_logo'=> $row['escuela_logo'] ?? '',
      'foto'    => $row['foto'] ?? '',
      'modalidad'=> $row['modalidad'] ?? '',
      'peso'    => $row['peso'] ?? '',
      'W_base'=> (int)($row['wins']   ?? 0),
      'L_base'=> (int)($row['losses'] ?? 0),
      'D_base'=> (int)($row['draws']  ?? 0),
      'NC_base'=> (int)($row['nc']    ?? 0),
    );
  }
  $r->close();
}

/* ===== Unificación ===== */
$grupos = array(); $idsPorGrupo = array(); $indexApellidoDNI = array(); $indexApellidoGrupoConDNI = array();

foreach ($fichas as $f) {
  $dniNorm = normalize_dni($f['dni'] ?? '');
  $apeNorm = norm_txt2($f['apellido'] ?? '');
  if ($dniNorm) {
    $key = 'dni:'.$dniNorm;
    if (!isset($grupos[$key])) {
      $grupos[$key] = array(
        'key'=>$key,'dni'=>$dniNorm,'id_base'=>$f['id'],
        'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],
        'escuela'=>$f['escuela'],'escuelas'=>array_filter(array($f['escuela'])),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
        'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
        'badge'=>''
      );
      $idsPorGrupo[$key]=array($f['id']);
    } else {
      if ($f['id'] > $grupos[$key]['id_base']) {
        $grupos[$key]['id_base']=$f['id'];
        foreach (array('apellido','nombre','escuela','logo','foto','modalidad','peso') as $fld){
          $src = ($fld==='logo')? ($f['escuela_logo']??'') : ($f[$fld]??'');
          if (!empty($src)) $grupos[$key][$fld]=$src;
        }
      }
      $grupos[$key]['W_acc'] += (int)$f['W_base'];
      $grupos[$key]['L_acc'] += (int)$f['L_base'];
      $grupos[$key]['D_acc'] += (int)$f['D_base'];
      $grupos[$key]['NC_acc']+= (int)$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$key]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$key][]=$f['id'];
    }
    if ($apeNorm!=='') { if (!isset($indexApellidoDNI[$apeNorm])) $indexApellidoDNI[$apeNorm]=array(); $indexApellidoDNI[$apeNorm][$dniNorm]=true; }
  }
}
foreach ($indexApellidoDNI as $ape=>$dniSet){
  $dnis = array_keys($dniSet);
  if (count($dnis)===1){ $indexApellidoGrupoConDNI[$ape] = 'dni:'.$dnis[0]; }
}

/* Fichas sin DNI: agrupar por apellido+nombre (tolerancia) */
foreach ($fichas as $f) {
  $dniNorm = normalize_dni($f['dni'] ?? ''); if ($dniNorm) continue;
  $ape = $f['apellido'] ?? ''; $nom = $f['nombre'] ?? '';
  $apeNorm = norm_txt2($ape);
  $attached=false;

  if ($apeNorm !== '' && isset($indexApellidoGrupoConDNI[$apeNorm])) {
    $k = $indexApellidoGrupoConDNI[$apeNorm];
    $g = $grupos[$k];
    $nombreBase = first_name($g['nombre'] ?? '');
    $nombreNuevo= first_name($nom);
    if ($nombreBase!=='' && $nombreNuevo!=='' && is_similar_name($nombreBase,$nombreNuevo,2)) {
      if ($f['id'] > $g['id_base']) {
        $grupos[$k]['id_base']=$f['id'];
        foreach (array('apellido','nombre','escuela','logo','foto','modalidad','peso') as $fld){
          $src = ($fld==='logo') ? ($f['escuela_logo']??'') : ($f[$fld]??'');
          if (!empty($src)) $grupos[$k][$fld]=$src;
        }
      }
      $grupos[$k]['W_acc'] += (int)$f['W_base'];
      $grupos[$k]['L_acc'] += (int)$f['L_base'];
      $grupos[$k]['D_acc'] += (int)$f['D_base'];
      $grupos[$k]['NC_acc']+= (int)$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id']; $attached=true;
    }
  }
  if ($attached) continue;

  $nameKey = norm_txt2(trim($ape.' '.$nom));
  if ($nameKey) {
    $k = 'nx:'.$nameKey;
    if (!isset($grupos[$k])){
      $grupos[$k] = array(
        'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
        'apellido'=>$ape,'nombre'=>$nom,
        'escuela'=>$f['escuela'],'escuelas'=>array_filter(array($f['escuela'])),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
        'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
        'badge'=>''
      );
      $idsPorGrupo[$k] = array($f['id']);
    } else {
      if ($f['id'] > $grupos[$k]['id_base']) {
        $grupos[$k]['id_base'] = $f['id'];
        foreach (array('apellido','nombre','escuela','logo','foto','modalidad','peso') as $fld){
          $src = ($fld==='logo') ? ($f['escuela_logo']??'') : ($f[$fld]??'');
          if (!empty($src)) $grupos[$k][$fld] = $src;
        }
      }
      $grupos[$k]['W_acc'] += (int)$f['W_base'];
      $grupos[$k]['L_acc'] += (int)$f['L_base'];
      $grupos[$k]['D_acc'] += (int)$f['D_base'];
      $grupos[$k]['NC_acc']+= (int)$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][] = $f['escuela'];
      $idsPorGrupo[$k][] = $f['id'];
    }
  } else {
    $k = 'id:'.$f['id'];
    $grupos[$k] = array(
      'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
      'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],
      'escuela'=>$f['escuela'],'escuelas'=>array_filter(array($f['escuela'])),
      'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W_acc'=>(int)$f['W_base'],'L_acc'=>(int)$f['L_base'],
      'D_acc'=>(int)$f['D_base'],'NC_acc'=>(int)$f['NC_base'],
      'badge'=>''
    );
    $idsPorGrupo[$k] = array($f['id']);
  }
}

/* ===== Preparar lista final ===== */
$global = array(); $mapIdToKey=array(); $mapAnyIdToKey=array();
foreach ($grupos as $key=>$g){
  $nombre = trim(($g['apellido'] ?? '').' '.($g['nombre'] ?? ''));
  $escuelasUnicas = array_values(array_unique(isset($g['escuelas'])?array_filter($g['escuelas']):array()));
  $escuelaFinal = !empty($g['escuela']) ? $g['escuela'] : ($escuelasUnicas ? end($escuelasUnicas) : '');
  $global[$key]=array(
    'key'=>$key,'dni'=>$g['dni'] ?? null,'id_base'=>$g['id_base'],
    'nombre'=> ($nombre!=='' ? $nombre : '—'),
    'escuela'=> $escuelaFinal, 'logo'=> $g['logo'] ?? '', 'foto'=> $g['foto'] ?? '',
    'modalidad'=> $g['modalidad'] ?? '', 'peso'=> $g['peso'] ?? '',
    'W'=> (int)$g['W_acc'], 'L'=> (int)$g['L_acc'], 'D'=> (int)$g['D_acc'], 'NC'=> (int)$g['NC_acc'],
    'badge'=> $g['badge'] ?? ''
  );
  $mapIdToKey[(int)$g['id_base']]=$key;
}
foreach ($idsPorGrupo as $k=>$ids){
  foreach ($ids as $cid){ $mapAnyIdToKey[(int)$cid] = $k; }
}

/* ===== Sumar peleas (si hay) por ganador calculado/columna (opcional) ===== */
if ($peleas){
  foreach($peleas as $p){
    $g = $p['g'] ?? null; if ($g===null) continue;
    $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id'];
    if (isset($mapAnyIdToKey[$az])){
      $k = $mapAnyIdToKey[$az];
      if ($g==='empate'){ $global[$k]['D']++; }
      elseif ($g==='azul'){ $global[$k]['W']++; }
      elseif ($g==='rojo'){ $global[$k]['L']++; }
    }
    if (isset($mapAnyIdToKey[$ro])){
      $k = $mapAnyIdToKey[$ro];
      if ($g==='empate'){ $global[$k]['D']++; }
      elseif ($g==='rojo'){ $global[$k]['W']++; }
      elseif ($g==='azul'){ $global[$k]['L']++; }
    }
  }
}

/* ===== NUEVO: sumar resultados desde resultados_combates (fuente oficial) ===== */
if ($hasPeleas && $C_ROJO && $C_AZUL) {
  $sqlRC = "SELECT rc.ganador_color, pe.".bt($C_ROJO)." AS rid, pe.".bt($C_AZUL)." AS aid, rc.evento_id
            FROM resultados_combates rc
            LEFT JOIN peleas_evento pe ON pe.id = rc.pelea_id";
  if ($evento_id_actual) { $sqlRC .= " WHERE rc.evento_id = ?"; }
  if ($st = $conexion->prepare($sqlRC)) {
    if ($evento_id_actual) { $st->bind_param('i',$evento_id_actual); }
    $st->execute(); $res = $st->get_result();
    while ($res && ($row = $res->fetch_assoc())) {
      $gc = strtolower((string)$row['ganador_color']);
      $rid = (int)($row['rid'] ?? 0); $aid = (int)($row['aid'] ?? 0);
      // rojo corner
      if (isset($mapAnyIdToKey[$rid])) {
        $k = $mapAnyIdToKey[$rid];
        if     ($gc==='rojo')   $global[$k]['W']++;
        elseif ($gc==='azul')   $global[$k]['L']++;
        elseif ($gc==='empate') $global[$k]['D']++;
      }
      // azul corner
      if (isset($mapAnyIdToKey[$aid])) {
        $k = $mapAnyIdToKey[$aid];
        if     ($gc==='azul')   $global[$k]['W']++;
        elseif ($gc==='rojo')   $global[$k]['L']++;
        elseif ($gc==='empate') $global[$k]['D']++;
      }
    }
    $st->close();
  } elseif ($DEBUG){
    echo '<div class="bad">No se pudo preparar suma desde resultados_combates.</div>';
  }
}

/* ===== Filtros / orden ===== */
$busca = trim((string)($_GET['q'] ?? ''));
$orden = (string)($_GET['sort'] ?? 'wins'); // wins|name|gym
$lista = array_values($global);

if ($busca!==''){
  $q = mb_strtolower($busca,'UTF-8');
  $tmp=array();
  foreach($lista as $c){
    $s = mb_strtolower(($c['nombre'].' '.$c['escuela']), 'UTF-8');
    if (strpos($s,$q)!==false) $tmp[]=$c;
  }
  $lista = $tmp;
}
usort($lista,function($a,$b) use($orden){
  if ($orden==='name'){ return strnatcasecmp($a['nombre'],$b['nombre']); }
  elseif ($orden==='gym'){ return strnatcasecmp($a['escuela'] ?? '', $b['escuela'] ?? ''); }
  else {
    $da = $b['W'] <=> $a['W']; if ($da) return $da;
    $db = ($a['L'] <=> $b['L']); if ($db) return $db;
    return strnatcasecmp($a['nombre'],$b['nombre']);
  }
});
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>📊 Competidores — Admin & Resultados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#fff; --fg:#111; --muted:#6b7280; --border:#e5e7eb;
      --card:#fff; --card-b:#e5e7eb; --pill:#f3f4f6;
      --okbg:#ecfdf5; --okbd:#10b981; --okfg:#065f46;
      --badbg:#fef2f2; --badbd:#ef4444; --badfg:#7f1d1d;
      --btn:#111; --btnfg:#fff;
    }
    *{box-sizing:border-box} body{background:var(--bg);color:var(--fg);font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:1200px;margin:0 auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--card-b);border-radius:12px;padding:12px;margin:12px 0}
    .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    .btn{background:var(--btn);color:var(--btnfg);border:0;border-radius:10px;padding:10px 14px;cursor:pointer;text-decoration:none;display:inline-block}
    .btn2{border:1px solid var(--border);background:#fff;color:#111;border-radius:10px;padding:8px 12px;cursor:pointer}
    .input, select{border:1px solid var(--border);border-radius:10px;padding:8px 10px}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid var(--border);padding:8px 10px;text-align:left}
    th{background:#f9fafb}
    .pill{display:inline-block;background:var(--pill);padding:2px 8px;border-radius:999px}
    .ok{background:var(--okbg);border:1px solid var(--okbd);color:var(--okfg);padding:10px;border-radius:10px}
    .bad{background:var(--badbg);border:1px solid var(--badbd);color:var(--badfg);padding:10px;border-radius:10px}
    .muted{color:var(--muted)}
    .grid{display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:10px}
    @media (max-width:800px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>
<div class="wrap">

  <h2>📊 Competidores — Administración & Carga de Resultados</h2>

  <?php if (!empty($flash_ok)): ?><div class="ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if (!empty($flash_error)): ?><div class="bad"><?= h($flash_error) ?></div><?php endif; ?>

  <?php if ($DEBUG): ?>
    <div class="card"><div class="muted">
      DEBUG: evento en sesión = <?= h($evento_id_actual !== null ? $evento_id_actual : 'null') ?>
    </div></div>
  <?php endif; ?>

  <!-- FORM: Guardar resultado -->
  <div class="card">
    <h3 style="margin:0 0 8px">📝 Cargar / actualizar resultado</h3>
    <form method="post" class="grid" action="">
      <input type="hidden" name="action" value="guardar_resultado">
      <label>Pelea ID
        <input class="input" type="number" name="pelea_id" required>
      </label>
      <label>Evento ID (si vacío usa sesión)
        <input class="input" type="number" name="evento_id" value="<?= h($evento_id_actual !== null ? $evento_id_actual : '') ?>">
      </label>
      <label>Ganador (color)
        <select class="input" name="ganador_color" required>
          <option value="rojo">rojo</option>
          <option value="azul">azul</option>
          <option value="empate">empate</option>
        </select>
      </label>
      <label>Ganador ID (opcional, se deduce por color si está en peleas_evento)
        <input class="input" type="number" name="ganador_id">
      </label>
      <label>Método (KO/TKO/PTS/SUM…)
        <input class="input" type="text" name="metodo" maxlength="10">
      </label>
      <label>Detalle
        <input class="input" type="text" name="detalle" maxlength="255" placeholder="Ej.: PTS — por decisión unánime">
      </label>
      <label>Puntos Rojo
        <input class="input" type="number" name="puntos_rojo" value="0">
      </label>
      <label>Puntos Azul
        <input class="input" type="number" name="puntos_azul" value="0">
      </label>
      <div style="grid-column:1/-1;display:flex;gap:8px">
        <button class="btn" type="submit">💾 Guardar resultado</button>
        <a class="btn2" href="resultados_combates.php">👁 Ver historial de resultados</a>
      </div>
    </form>
    <p class="muted" style="margin:6px 0 0">• Si no indicás <em>Ganador ID</em>, se toma del rincón correspondiente en <code>peleas_evento</code>.</p>
  </div>

  <!-- FORM: Actualizar competidor -->
  <div class="card">
    <h3 style="margin:0 0 8px">👤 Actualizar datos de un competidor</h3>
    <form method="post" class="grid" action="">
      <input type="hidden" name="action" value="guardar_competidor">
      <label>ID Competidor (competidores_evento)
        <input class="input" type="number" name="id" required>
      </label>
      <label>Apellido
        <input class="input" type="text" name="apellido">
      </label>
      <label>Nombre
        <input class="input" type="text" name="nombre">
      </label>
      <label>Academia / Escuela
        <input class="input" type="text" name="escuela_nombre">
      </label>
      <label>Logo escuela (URL)
        <input class="input" type="text" name="escuela_logo">
      </label>
      <label>Foto competidor (URL)
        <input class="input" type="text" name="foto_competidor">
      </label>
      <label>Modalidad ID
        <input class="input" type="number" name="modalidad_id">
      </label>
      <label>Peso ID
        <input class="input" type="number" name="peso_id">
      </label>
      <div style="grid-column:1/-1">
        <button class="btn" type="submit">💾 Guardar competidor</button>
      </div>
    </form>
  </div>

  <!-- HISTORIAL: Resultados cargados -->
  <?php
  $hist = array();
  $sql = "SELECT rc.id, rc.pelea_id, rc.evento_id, rc.ganador_color, rc.ganador_id,
                 rc.metodo, rc.detalle, rc.puntos_rojo, rc.puntos_azul, rc.creado_en";

  if ($hasPeleas && $C_ROJO && $C_AZUL) {
    $sql .= ", pe.".bt($C_ROJO)." AS rid, pe.".bt($C_AZUL)." AS aid";
  } else {
    $sql .= ", NULL AS rid, NULL AS aid";
  }

  $sql .= ",
        cr.".bt($CE_APELLIDO)." AS r_apellido, cr.".bt($CE_NOMBRE)." AS r_nombre,
        ca.".bt($CE_APELLIDO)." AS a_apellido, ca.".bt($CE_NOMBRE)." AS a_nombre,
        gw.".bt($CE_APELLIDO)." AS g_apellido, gw.".bt($CE_NOMBRE)." AS g_nombre
      FROM resultados_combates rc";

  if ($hasPeleas && $C_ROJO && $C_AZUL) {
    $sql .= " LEFT JOIN peleas_evento pe ON pe.id = rc.pelea_id
              LEFT JOIN competidores_evento cr ON cr.".bt($CE_ID)." = pe.".bt($C_ROJO)."
              LEFT JOIN competidores_evento ca ON ca.".bt($CE_ID)." = pe.".bt($C_AZUL);
  } else {
    $sql .= " LEFT JOIN peleas_evento pe ON pe.id = rc.pelea_id
              LEFT JOIN competidores_evento cr ON 1=0
              LEFT JOIN competidores_evento ca ON 1=0";
  }

  $sql .= " LEFT JOIN competidores_evento gw ON gw.".bt($CE_ID)." = rc.ganador_id";

  if ($evento_id_actual) { $sql .= " WHERE rc.evento_id = ?"; }
  $sql .= " ORDER BY rc.id DESC LIMIT 300";

  $st = $conexion->prepare($sql);
  if ($st) {
    if ($evento_id_actual) { $st->bind_param('i', $evento_id_actual); }
    $st->execute();
    $res = $st->get_result();
    while ($res && $row = $res->fetch_assoc()) {
      $rojo_name = trim((string)($row['r_apellido'] ?? '').' '.(string)($row['r_nombre'] ?? ''));
      $azul_name = trim((string)($row['a_apellido'] ?? '').' '.(string)($row['a_nombre'] ?? ''));
      $gan_name  = trim((string)($row['g_apellido'] ?? '').' '.(string)($row['g_nombre'] ?? ''));

      if ($rojo_name==='') $rojo_name = ($row['rid'] ? '#'.$row['rid'] : '—');
      if ($azul_name==='') $azul_name = ($row['aid'] ? '#'.$row['aid'] : '—');
      if ($gan_name==='')  $gan_name  = ($row['ganador_id'] ? '#'.$row['ganador_id'] : '—');

      $hist[] = array(
        'id'            => (int)$row['id'],
        'pelea_id'      => (int)$row['pelea_id'],
        'evento_id'     => isset($row['evento_id']) ? (int)$row['evento_id'] : null,
        'ganador_color' => (string)$row['ganador_color'],
        'ganador_id'    => isset($row['ganador_id']) ? (int)$row['ganador_id'] : null,
        'ganador_name'  => $gan_name,
        'metodo'        => (string)($row['metodo'] ?? ''),
        'detalle'       => (string)($row['detalle'] ?? ''),
        'p_rojo'        => (int)($row['puntos_rojo'] ?? 0),
        'p_azul'        => (int)($row['puntos_azul'] ?? 0),
        'fecha'         => (string)($row['creado_en'] ?? ''),
        'rid'           => isset($row['rid']) ? (int)$row['rid'] : null,
        'aid'           => isset($row['aid']) ? (int)$row['aid'] : null,
        'rojo_name'     => $rojo_name,
        'azul_name'     => $azul_name,
      );
    }
    $st->close();
  } else if ($DEBUG){
    echo '<div class="bad">No se pudo preparar el historial.</div>';
    echo '<div class="bad" style="white-space:pre-wrap"><b>SQL:</b> '.h($sql)."\n<b>Error:</b> ".h($conexion->error).'</div>';
  }
  ?>

  <div class="card">
    <h3 style="margin:0 0 8px">📜 Historial de resultados<?= $evento_id_actual ? ' — Evento #'.(int)$evento_id_actual : '' ?></h3>
    <div class="muted" style="margin-bottom:8px">
      Se muestran los últimos 300 registros <?= $evento_id_actual ? 'del evento actual' : 'de todos los eventos' ?>.
    </div>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Pelea</th>
          <th>Rojo</th>
          <th>Azul</th>
          <th>Ganador</th>
          <th>Método</th>
          <th>Detalle</th>
          <th>Puntos (R-A)</th>
          <th>Fecha</th>
          <th>Acción</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$hist): ?>
        <tr><td colspan="10" class="muted" style="text-align:center">No hay resultados cargados.</td></tr>
      <?php else: foreach($hist as $h): ?>
        <tr>
          <td><?= (int)$h['id'] ?></td>
          <td>#<?= (int)$h['pelea_id'] ?></td>
          <td><?= h($h['rojo_name']) ?></td>
          <td><?= h($h['azul_name']) ?></td>
          <td>
            <?php
              $gc = $h['ganador_color'];
              $lbl = ($gc==='rojo' ? '🔴 ROJO' : ($gc==='azul' ? '🔵 AZUL' : '⚖️ EMPATE'));
              echo h($lbl).' · '.h($h['ganador_name']);
            ?>
          </td>
          <td><?= h($h['metodo']) ?></td>
          <td><?= h($h['detalle']) ?></td>
          <td><?= (int)$h['p_rojo'] ?> — <?= (int)$h['p_azul'] ?></td>
          <td><?= h($h['fecha']) ?></td>
          <td>
            <button class="btn2"
              onclick="cargarEdicionResultado(
                <?= (int)$h['pelea_id'] ?>,
                '<?= h($h['ganador_color']) ?>',
                <?= $h['ganador_id'] !== null ? (int)$h['ganador_id'] : 'null' ?>,
                '<?= h($h['metodo']) ?>',
                '<?= h($h['detalle']) ?>',
                <?= (int)$h['p_rojo'] ?>,
                <?= (int)$h['p_azul'] ?>,
                <?= $h['evento_id'] !== null ? (int)$h['evento_id'] : 'null' ?>
              )">Editar</button>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <script>
    function cargarEdicionResultado(peleaId, ganadorColor, ganadorId, metodo, detalle, pRojo, pAzul, eventoId){
      const f = document.querySelector('form[action=""] input[name="action"][value="guardar_resultado"]')
                 ? document.querySelector('form[action=""]').closest('form')
                 : document.querySelector('form input[name="action"][value="guardar_resultado"]').closest('form');
      if (!f) return;
      f.querySelector('input[name="pelea_id"]').value = peleaId;
      const sel = f.querySelector('select[name="ganador_color"]'); if (sel) sel.value = ganadorColor || 'empate';
      const gId = f.querySelector('input[name="ganador_id"]'); if (gId) gId.value = (ganadorId!=null) ? ganadorId : '';
      const met = f.querySelector('input[name="metodo"]'); if (met) met.value = metodo || '';
      const det = f.querySelector('input[name="detalle"]'); if (det) det.value = detalle || '';
      const pr = f.querySelector('input[name="puntos_rojo"]'); if (pr) pr.value = (pRojo!=null) ? pRojo : 0;
      const pa = f.querySelector('input[name="puntos_azul"]'); if (pa) pa.value = (pAzul!=null) ? pAzul : 0;
      const ev = f.querySelector('input[name="evento_id"]'); if (ev) ev.value = (eventoId!=null && eventoId!=='') ? eventoId : (ev.value||'');
      f.scrollIntoView({behavior:'smooth', block:'start'});
    }
  </script>

  <!-- Herramientas de listado -->
  <div class="card">
    <form method="get" class="row" action="">
      <input class="input" type="text" name="q" placeholder="Buscar por nombre o academia…" value="<?= h($busca) ?>" style="min-width:220px">
      <select class="input" name="sort" aria-label="Orden">
        <option value="wins" <?= $orden==='wins'?'selected':''; ?>>Más ganadas</option>
        <option value="name" <?= $orden==='name'?'selected':''; ?>>Nombre</option>
        <option value="gym"  <?= $orden==='gym'?'selected':'';  ?>>Academia</option>
      </select>
      <button class="btn" type="submit">Aplicar</button>
      <a class="btn2" href="<?= h(strtok($_SERVER['REQUEST_URI'],'?')) ?>">Limpiar</a>
    </form>
  </div>

  <!-- Listado -->
  <div class="card">
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
        $perfilUrl = !empty($c['dni'])
          ? 'ver_competidor_ranking.php?dni='.urlencode($c['dni'])
          : 'ver_competidor_ranking.php?id='.(int)$c['id_base'];
      ?>
        <tr>
          <td style="text-align:left">
            <a class="rowlink" href="<?= h($perfilUrl) ?>" style="text-decoration:none;color:inherit">
              <strong><?= h($nombre) ?></strong>
              <?php if (!empty($c['badge'])): ?><span class="muted"><?= h($c['badge']) ?></span><?php endif; ?>
              <div class="muted" style="font-size:12px">Ficha base: <?= (int)$c['id_base'] ?><?= !empty($c['dni'])?' • DNI: '.h($c['dni']):'' ?></div>
            </a>
          </td>
          <td style="text-align:left"><?= h(!empty($c['escuela']) ? $c['escuela'] : '—') ?></td>
          <td><?= h(!empty($c['modalidad']) ? $c['modalidad'] : '—') ?></td>
          <td><?= h(!empty($c['peso']) ? $c['peso'] : '—') ?></td>
          <td><span class="pill"><?= (int)$c['W'] ?></span></td>
          <td><span class="pill"><?= (int)$c['L'] ?></span></td>
          <td><span class="pill"><?= (int)$c['D'] ?></span></td>
          <td><span class="pill"><?= (int)$c['NC'] ?></span></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px">
      • Unifica por <b>DNI</b> (si es válido) o por <b>Apellido+Nombre</b> con tolerancia.<br>
      • El score parte de las columnas de la ficha y se suman: (a) ganador detectado en <code>resultados_jueces/peleas_evento</code> y, sobre todo, (b) los <b>resultados de <code>resultados_combates</code></b>.
    </p>
  </div>

</div>
</body>
</html>
