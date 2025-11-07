<?php
/* ============================================================================
   ranking_competidores.php — Admin + Ranking (GLOBAL / por evento)
   • Trae EXACTO lo que cargaste en ver_competidores_evento.php para evento N
   • Detecta la columna de evento en competidores_evento con múltiples alias
   • Fallback: si competidores_evento no tiene columna de evento, filtra por
     vinculación en peleas_evento (rojo/azul) para ese evento_id
   • Evita doble conteo (resultados_combates > jueces/peleas)
   • Incluye: buscar/traer competidor, actualizar, carga rápida, resultados
   ============================================================================ */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* No-cache para reflejar fin de pelea al instante */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$DEBUG = isset($_GET['debug']) && $_GET['debug']=='1';

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function pick_col(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }

/* has_table + has_col (tolerante a 2/3 args) */
function has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $q = $db->query("SHOW TABLES LIKE '$t'");
  $ok = $q && $q->num_rows>0; if ($q) $q->close(); return $ok;
}
function has_col($a, $b=null, $c=null): bool {
  if ($a instanceof mysqli) { $db=$a; $table=(string)$b; $col=(string)$c; }
  else { global $conexion; if (!isset($conexion) || !($conexion instanceof mysqli)) return false; $db=$conexion; $table=(string)$a; $col=(string)$b; }
  if ($table==='' || $col==='') return false;
  $t=$db->real_escape_string($table); $cn=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$cn' LIMIT 1";
  $r=$db->query($sql); $ok=$r && $r->num_rows>0; if ($r) $r->close(); return $ok;
}

/* Normalización de nombres/DNI */
function normalize_dni($dni){ $d=preg_replace('~\D+~','',(string)$dni); return (strlen($d)===8)?$d:null; }
function strip_accents($str){
  $rep=['Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n'];
  $str=strtr((string)$str,$rep);
  $x=@iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$str);
  return $x!==false?$x:$str;
}
function norm_txt2($s){ $s=mb_strtolower(strip_accents(trim((string)$s)),'UTF-8'); return preg_replace('~\s+~',' ',$s); }
function first_name($full){ $t=preg_replace('~\s+~',' ',trim((string)$full)); $p=explode(' ',$t); return norm_txt2($p[0]??''); }
function is_similar_lev($a,$b,$max=2){ $a=norm_txt2($a); $b=norm_txt2($b); if($a===''||$b==='')return false; if($a===$b)return true; return levenshtein($a,$b) <= $max; }
function metaphone_similar($a,$b,$max=1){ $a=norm_txt2($a); $b=norm_txt2($b); if($a===''||$b==='')return false; $ma=metaphone($a); $mb=metaphone($b); if($ma===''||$mb==='')return false; if($ma===$mb)return true; return levenshtein($ma,$mb) <= $max; }
function is_similar_name($a,$b,$maxLev=2){ return is_similar_lev($a,$b,$maxLev) || metaphone_similar($a,$b,1); }

/* ===== Alcance (GLOBAL / por evento) ===== */
$scope = strtolower(trim((string)($_GET['scope'] ?? 'global')));
if (!in_array($scope, ['global','evento'], true)) $scope='global';
$evento_get = toIntOrNull($_GET['evento_id'] ?? ($_GET['e'] ?? ''));
if ($scope==='evento' && $evento_get) $_SESSION['evento_id_actual']=$evento_get;
$evento_id_actual = ($scope==='evento') ? ($_SESSION['evento_id_actual'] ?? null) : null;
$evento_id_actual = $evento_id_actual ? (int)$evento_id_actual : null;

/* ===== Tablas mínimas ===== */
if (!has_table($conexion,'competidores_evento')) { exit('❌ Falta la tabla requerida: competidores_evento'); }
$hasPeleas = has_table($conexion,'peleas_evento');
if (!has_table($conexion,'resultados_combates')) { exit('❌ Falta la tabla requerida: resultados_combates'); }

/* ===== Columnas dinámicas en peleas_evento ===== */
$colsPe=[]; if ($hasPeleas && ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`"))){ while($r=$q->fetch_assoc()){ $colsPe[strtolower($r['Field'])]=$r['Field']; } $q->close(); }
$C_AZUL      = $hasPeleas ? pick_col(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'], $colsPe) : null;
$C_ROJO      = $hasPeleas ? pick_col(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'], $colsPe) : null;
$C_EVENTO_PE = $hasPeleas ? pick_col(['evento_id','id_evento','evento','evento_deportivo_id','id_evento_deportivo'], $colsPe) : null;
$C_FECHA_PE  = $hasPeleas ? pick_col(['fecha','fecha_pelea','fpelea','created_at'], $colsPe) : null;
$C_GAN_PE    = $hasPeleas ? pick_col(['ganador','ganador_color','resultado','winner'], $colsPe) : null;

/* ===== Columnas en competidores_evento ===== */
$colsCe=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){ while($r=$q->fetch_assoc()){ $colsCe[strtolower($r['Field'])]=$r['Field']; } $q->close(); }
$CE_ID        = pick_col(['id','competidor_id'], $colsCe);
$CE_DNI       = pick_col(['dni','documento','doc'], $colsCe);
$CE_NOMBRE    = pick_col(['nombre','nombres','display_name','nombre_completo','nombreyapellido'], $colsCe) ?: 'nombre';
$CE_APELLIDO  = pick_col(['apellido','apellidos'], $colsCe) ?: 'apellido';
$CE_ESC_NOM   = pick_col(['escuela_nombre','academia','gimnasio','equipo'], $colsCe);
$CE_ESC_LOGO  = pick_col(['escuela_logo','logo_escuela','logo_academia'], $colsCe);
$CE_FOTO      = pick_col(['foto_competidor','foto','avatar'], $colsCe);
$CE_PESO_ID   = pick_col(['categoria_peso_id','peso_id'], $colsCe);
$CE_MODAL_ID  = pick_col(['modalidad_id'], $colsCe);
$CE_WINS      = pick_col(['wins','win','w','ganadas'], $colsCe);
$CE_LOSSES    = pick_col(['losses','loss','l','perdidas'], $colsCe);
$CE_DRAWS     = pick_col(['draws','draw','d','empates'], $colsCe);
$CE_NC        = pick_col(['no_contest','nocontest','nc','sin_decision'], $colsCe);
/* 👇 AUMENTADO: más alias para la columna del evento en competidores_evento */
$CE_EVENTO_ID = pick_col(['evento_id','id_evento','evento','evento_deportivo_id','id_evento_deportivo'], $colsCe);

/* ===== Flash ===== */
$flash_ok    = $_SESSION['flash_ok']    ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* =========================================================
   POST: Crear / Actualizar competidor + Guardar resultado
   ========================================================= */
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $action = $_POST['action'] ?? '';

  /* Crear competidor (carga rápida) */
  if ($action==='crear_competidor') {
    if (!$CE_ID) { $_SESSION['flash_error']='La tabla competidores_evento no tiene columna ID.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }
    $dni      = trim((string)($_POST['dni'] ?? ''));
    $apellido = trim((string)($_POST['apellido'] ?? ''));
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $escuela  = trim((string)($_POST['escuela_nombre'] ?? ''));
    $logo     = trim((string)($_POST['escuela_logo'] ?? ''));
    $foto     = trim((string)($_POST['foto_competidor'] ?? ''));
    $modal_id = toIntOrNull($_POST['modalidad_id'] ?? '');
    $peso_id  = toIntOrNull($_POST['peso_id'] ?? '');
    $ev_id    = toIntOrNull($_POST['evento_id'] ?? '');
    if ($scope==='evento' && !$ev_id) $ev_id = $evento_id_actual;

    $cols=[]; $ph=[]; $types=''; $vals=[];
    if ($CE_DNI && $dni!==''){ $cols[]=bt($CE_DNI); $ph[]='?'; $types.='s'; $vals[]=$dni; }
    if ($CE_APELLIDO){ $cols[]=bt($CE_APELLIDO); $ph[]='?'; $types.='s'; $vals[]=$apellido; }
    if ($CE_NOMBRE){ $cols[]=bt($CE_NOMBRE); $ph[]='?'; $types.='s'; $vals[]=$nombre; }
    if ($CE_ESC_NOM){ $cols[]=bt($CE_ESC_NOM); $ph[]='?'; $types.='s'; $vals[]=$escuela; }
    if ($CE_ESC_LOGO){ $cols[]=bt($CE_ESC_LOGO); $ph[]='?'; $types.='s'; $vals[]=$logo; }
    if ($CE_FOTO){ $cols[]=bt($CE_FOTO); $ph[]='?'; $types.='s'; $vals[]=$foto; }
    if ($CE_MODAL_ID && $modal_id!==null){ $cols[]=bt($CE_MODAL_ID); $ph[]='?'; $types.='i'; $vals[]=$modal_id; }
    if ($CE_PESO_ID  && $peso_id !==null){ $cols[]=bt($CE_PESO_ID);  $ph[]='?'; $types.='i'; $vals[]=$peso_id; }
    if ($CE_EVENTO_ID && $ev_id!==null){ $cols[]=bt($CE_EVENTO_ID); $ph[]='?'; $types.='i'; $vals[]=$ev_id; }

    if (!$cols){ $_SESSION['flash_error']='No hay columnas para insertar.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    $sql="INSERT INTO `competidores_evento` (".implode(',',$cols).") VALUES (".implode(',',$ph).")";
    if ($st=$conexion->prepare($sql)){
      $bind=[$types]; foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
      call_user_func_array([$st,'bind_param'],$bind);
      if ($st->execute()){ $_SESSION['flash_ok']='✅ Competidor creado (ID '.$st->insert_id.').'; }
      else { $_SESSION['flash_error']='No se pudo crear el competidor: '.$conexion->error; }
      $st->close();
    } else { $_SESSION['flash_error']='No se pudo preparar el INSERT: '.$conexion->error; }
    header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit;
  }

  /* Actualizar competidor */
  if ($action==='guardar_competidor') {
    $id = toIntOrNull($_POST['id'] ?? '');
    if (!$id || !$CE_ID) { $_SESSION['flash_error']='Faltan datos: id competidor.'; header('Location: '.$_SERVER['REQUEST_URI']); exit; }

    $map=[];
    if ($CE_APELLIDO)  $map[$CE_APELLIDO]  = $_POST['apellido'] ?? null;
    if ($CE_NOMBRE)    $map[$CE_NOMBRE]    = $_POST['nombre'] ?? null;
    if ($CE_DNI)       $map[$CE_DNI]       = $_POST['dni'] ?? null;
    if ($CE_ESC_NOM)   $map[$CE_ESC_NOM]   = $_POST['escuela_nombre'] ?? null;
    if ($CE_ESC_LOGO)  $map[$CE_ESC_LOGO]  = $_POST['escuela_logo'] ?? null;
    if ($CE_FOTO)      $map[$CE_FOTO]      = $_POST['foto_competidor'] ?? null;
    if ($CE_MODAL_ID)  $map[$CE_MODAL_ID]  = toIntOrNull($_POST['modalidad_id'] ?? '');
    if ($CE_PESO_ID)   $map[$CE_PESO_ID]   = toIntOrNull($_POST['peso_id'] ?? '');
    if ($CE_EVENTO_ID) $map[$CE_EVENTO_ID] = toIntOrNull($_POST['evento_id'] ?? '');

    $sets=[]; $types=''; $vals=[];
    foreach($map as $col=>$val){
      if ($col===null || $val===null) continue;
      $sets[]=bt($col).'=?';
      if (is_int($val)){ $types.='i'; $vals[]=$val; } else { $types.='s'; $vals[]=trim((string)$val); }
    }

    if ($sets){
      $sql="UPDATE `competidores_evento` SET ".implode(',',$sets)." WHERE ".bt($CE_ID)."=?";
      $types.='i'; $vals[]=$id;
      if ($st=$conexion->prepare($sql)){
        $bind=[$types]; foreach($vals as $k=>$v){ $bind[]=&$vals[$k]; }
        call_user_func_array([$st,'bind_param'],$bind);
        if ($st->execute()){ $_SESSION['flash_ok']='✅ Competidor actualizado.'; }
        else { $_SESSION['flash_error']='Error al actualizar: '.$conexion->error; }
        $st->close();
      } else { $_SESSION['flash_error']='No se pudo preparar UPDATE: '.$conexion->error; }
    } else { $_SESSION['flash_error']='No hay cambios para aplicar.'; }
    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }

  /* Guardar resultado oficial */
  if ($action==='guardar_resultado') {
    $pelea_id = toIntOrNull($_POST['pelea_id'] ?? '');
    $ganador_color = strtolower(trim((string)($_POST['ganador_color'] ?? '')));
    $ganador_id = toIntOrNull($_POST['ganador_id'] ?? '');
    $metodo = substr((string)($_POST['metodo'] ?? ''),0,10);
    $detalle = substr((string)($_POST['detalle'] ?? ''),0,255);
    $p_rojo = toIntOrNull($_POST['puntos_rojo'] ?? ''); if ($p_rojo===null) $p_rojo=0;
    $p_azul = toIntOrNull($_POST['puntos_azul'] ?? ''); if ($p_azul===null) $p_azul=0;

    $evento_id = toIntOrNull($_POST['evento_id'] ?? ''); if (!$evento_id) $evento_id = $evento_id_actual;
    if (!$pelea_id || !$evento_id || !in_array($ganador_color,['rojo','azul','empate'],true)) {
      $_SESSION['flash_error'] = 'Faltan datos: pelea_id, evento_id o ganador_color inválido.'; header('Location: '.$_SERVER['REQUEST_URI']); exit;
    }

    if (!$ganador_id && $hasPeleas && $C_ROJO && $C_AZUL) {
      $sql="SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1";
      if ($st=$conexion->prepare($sql)){
        $st->bind_param('i',$pelea_id); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
        if ($r){ if ($ganador_color==='rojo') $ganador_id=(int)$r['rid']; elseif ($ganador_color==='azul') $ganador_id=(int)$r['aid']; }
      }
    }

    $conexion->begin_transaction();
    try {
      $prev=null;
      if ($st=$conexion->prepare("SELECT ganador_color FROM resultados_combates WHERE pelea_id=? LIMIT 1")){
        $st->bind_param('i',$pelea_id); $st->execute(); $res=$st->get_result(); $prev=$res?$res->fetch_assoc():null; $st->close();
      }

      /* revertir impacto previo si tenés columnas W/L/D */
      if ($prev && $hasPeleas && $C_ROJO && $C_AZUL && $CE_WINS && $CE_LOSSES && $CE_DRAWS) {
        $rid=null; $aid=null;
        if ($st=$conexion->prepare("SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1")){
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

      /* upsert oficial */
      $colsRc=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `resultados_combates`")){ while($r=$q->fetch_assoc()){ $colsRc[strtolower($r['Field'])]=$r['Field']; } $q->close(); }
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

      /* aplicar impacto nuevo en W/L/D si existen */
      if ($hasPeleas && $C_ROJO && $C_AZUL && $CE_WINS && $CE_LOSSES && $CE_DRAWS) {
        $rid=null; $aid=null;
        if ($st=$conexion->prepare("SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM `peleas_evento` WHERE id=? LIMIT 1")){
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
      $_SESSION['flash_ok']='✅ Resultado guardado.';
    } catch (Throwable $e) {
      $conexion->rollback();
      $_SESSION['flash_error']='No se pudo guardar el resultado: '.$e->getMessage();
    }

    header('Location: '.$_SERVER['REQUEST_URI']); exit;
  }
}

/* =========================================================
   PREFILL: traer datos de competidor (como ver_competidores_evento.php)
   ========================================================= */
$prefill = [
  'id'=>'','dni'=>'','apellido'=>'','nombre'=>'',
  'escuela_nombre'=>'','escuela_logo'=>'','foto_competidor'=>'',
  'modalidad_id'=>'','peso_id'=>'','evento_id'=> ($scope==='evento' && $evento_id_actual)?$evento_id_actual:''
];
$buscar_id  = toIntOrNull($_GET['comp_id'] ?? '');
$buscar_dni = trim((string)($_GET['dni'] ?? ''));

if ($buscar_id || $buscar_dni!=='') {
  $where = ''; $types=''; $vals=[];
  if ($buscar_id){ $where = " WHERE c.".bt($CE_ID)."=?"; $types='i'; $vals[]=$buscar_id; }
  elseif ($CE_DNI && $buscar_dni!==''){ $where = " WHERE c.".bt($CE_DNI)."=?"; $types='s'; $vals[]=$buscar_dni; }

  $sel = "c.".bt($CE_ID)." AS id";
  $sel.= $CE_DNI      ? ", c.".bt($CE_DNI)." AS dni" : ", NULL AS dni";
  $sel.= ", c.".bt($CE_APELLIDO)." AS apellido, c.".bt($CE_NOMBRE)." AS nombre";
  $sel.= $CE_ESC_NOM  ? ", c.".bt($CE_ESC_NOM)." AS escuela_nombre" : ", NULL AS escuela_nombre";
  $sel.= $CE_ESC_LOGO ? ", c.".bt($CE_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo";
  $sel.= $CE_FOTO     ? ", c.".bt($CE_FOTO)." AS foto_competidor" : ", NULL AS foto_competidor";
  $sel.= $CE_MODAL_ID ? ", c.".bt($CE_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id";
  $sel.= $CE_PESO_ID  ? ", c.".bt($CE_PESO_ID)." AS peso_id" : ", NULL AS peso_id";
  $sel.= $CE_EVENTO_ID? ", c.".bt($CE_EVENTO_ID)." AS evento_id" : ", NULL AS evento_id";

  $sql = "SELECT $sel FROM `competidores_evento` c".$where." LIMIT 1";
  $st = $conexion->prepare($sql);
  if ($st){
    if ($types!==''){ $st->bind_param($types, ...$vals); }
    $st->execute(); $r=$st->get_result(); $row=$r?$r->fetch_assoc():null; $st->close();
    if ($row){ foreach($prefill as $k=>$_){ if (array_key_exists($k,$row)) $prefill[$k] = (string)$row[$k]; } $prefill['id']=(string)($row['id'] ?? ''); }
    else { $flash_error = $flash_error ?: 'No se encontró el competidor.'; }
  }
}

/* =========================================================
   Winner por jueces (heurística solo si NO hay oficial)
   ========================================================= */
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

/* =========================================================
   Conjuntos por EVENTO (para filtrar y evitar doble conteo)
   ========================================================= */
$peleaConRC=[];     // peleas con resultado oficial
$idsEventoViaPE=[]; // competidores del evento por peleas_evento (fallback)
if ($scope==='evento' && $evento_id_actual){
  /* resultados oficiales del evento */
  if ($st=$conexion->prepare("SELECT pelea_id FROM resultados_combates WHERE evento_id=?")){
    $st->bind_param('i',$evento_id_actual); $st->execute(); $res=$st->get_result();
    while($res && ($row=$res->fetch_assoc())){ $peleaConRC[(int)$row['pelea_id']]=true; }
    $st->close();
  }
  /* fallback: ids de competidores que pelean en este evento */
  if ($hasPeleas && $C_EVENTO_PE && $C_ROJO && $C_AZUL){
    if ($st=$conexion->prepare("SELECT ".bt($C_ROJO)." AS rid, ".bt($C_AZUL)." AS aid FROM peleas_evento WHERE ".bt($C_EVENTO_PE)."=?")){
      $st->bind_param('i',$evento_id_actual); $st->execute(); $res=$st->get_result();
      while($res && ($row=$res->fetch_assoc())){
        $r=(int)$row['rid']; $a=(int)$row['aid'];
        if ($r>0) $idsEventoViaPE[$r]=true;
        if ($a>0) $idsEventoViaPE[$a]=true;
      }
      $st->close();
    }
  }
} else {
  $r=$conexion->query("SELECT pelea_id FROM resultados_combates");
  if ($r){ while($row=$r->fetch_assoc()){ $peleaConRC[(int)$row['pelea_id']]=true; } $r->close(); }
}

/* =========================================================
   Traer PELEAS (resumidas con posible ganador heurístico)
   ========================================================= */
$peleas=[];
if ($hasPeleas && $C_AZUL && $C_ROJO) {
  $cols="p.id AS pelea_id, p.".bt($C_AZUL)." AS azul_id, p.".bt($C_ROJO)." AS rojo_id";
  if ($C_EVENTO_PE) $cols.=", p.".bt($C_EVENTO_PE)." AS evento_id";
  if ($C_FECHA_PE)  $cols.=", p.".bt($C_FECHA_PE)." AS f";
  if ($C_GAN_PE)    $cols.=", p.".bt($C_GAN_PE)." AS ganador_pelea";
  $sqlPe = "SELECT $cols FROM peleas_evento p";
  $bind=null; $types='';
  if ($scope==='evento' && $evento_id_actual && $C_EVENTO_PE){
    $sqlPe.=" WHERE p.".bt($C_EVENTO_PE)."=?"; $bind=[$evento_id_actual]; $types='i';
  }
  $st = $conexion->prepare($sqlPe);
  if ($st){
    if ($bind){ $st->bind_param($types, $bind[0]); }
    $st->execute(); $r=$st->get_result();
    while($r && ($row=$r->fetch_assoc())){
      $row['pelea_id']=(int)$row['pelea_id'];
      $row['azul_id'] =(int)$row['azul_id'];
      $row['rojo_id'] =(int)$row['rojo_id'];
      $g=$winnerByFight[$row['pelea_id']] ?? null;
      if ($g===null && isset($row['ganador_pelea'])){
        $gg=strtolower(trim((string)$row['ganador_pelea']));
        if (in_array($gg,['azul','rojo','empate'],true)) $g=$gg;
      }
      $row['g']=$g;
      $peleas[]=$row;
    }
    $st->close();
  }
}

/* =========================================================
   Traer FICHAS de competidores (GLOBAL o por evento)
   ========================================================= */
$selCe = "c.".bt($CE_ID)." AS id";
$selCe.= $CE_DNI      ? ", c.".bt($CE_DNI)." AS dni" : ", NULL AS dni";
$selCe.= ", c.".bt($CE_APELLIDO)." AS apellido, c.".bt($CE_NOMBRE)." AS nombre";
$selCe.= $CE_ESC_NOM  ? ", c.".bt($CE_ESC_NOM)." AS escuela" : ", NULL AS escuela";
$selCe.= $CE_ESC_LOGO ? ", c.".bt($CE_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo";
$selCe.= $CE_FOTO     ? ", c.".bt($CE_FOTO)." AS foto" : ", NULL AS foto";
$selCe.= $CE_MODAL_ID ? ", c.".bt($CE_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id";
$selCe.= $CE_PESO_ID  ? ", c.".bt($CE_PESO_ID)." AS peso_id" : ", NULL AS peso_id";
if ($CE_WINS)   $selCe.= ", CAST(c.".bt($CE_WINS)." AS SIGNED) AS wins";
if ($CE_LOSSES) $selCe.= ", CAST(c.".bt($CE_LOSSES)." AS SIGNED) AS losses";
if ($CE_DRAWS)  $selCe.= ", CAST(c.".bt($CE_DRAWS)." AS SIGNED) AS draws";
if ($CE_NC)     $selCe.= ", CAST(c.".bt($CE_NC)." AS SIGNED) AS nc";

/* JOINs opcionales para nombres de modalidad/peso */
$joins=""; $selExtra="";
if (has_table($conexion,'modalidades_evento') && $CE_MODAL_ID){ $joins.=" LEFT JOIN modalidades_evento mo ON mo.id=c.".bt($CE_MODAL_ID); $selExtra.=", mo.nombre AS modalidad"; }
else { $selExtra.=", NULL AS modalidad"; }
if (has_table($conexion,'categorias_peso_evento') && $CE_PESO_ID){ $joins.=" LEFT JOIN categorias_peso_evento cp ON cp.id=c.".bt($CE_PESO_ID); $selExtra.=", cp.nombre AS peso"; }
else { $selExtra.=", NULL AS peso"; }

/* WHERE por evento:
   1) Preferente: c.<evento_id detectado>
   2) Fallback: si no existe la col de evento, filtrar por IDs presentes en peleas_evento del evento
*/
$whereCe=''; $bindCe=null; $typesCe='';
if ($scope==='evento' && $evento_id_actual){
  if ($CE_EVENTO_ID){
    $whereCe=" WHERE c.".bt($CE_EVENTO_ID)."=?"; $bindCe=[$evento_id_actual]; $typesCe='i';
  } else if (!empty($idsEventoViaPE)) {
    // construir IN (...) seguro y acotado
    $ids = array_map('intval', array_keys($idsEventoViaPE));
    $ids = array_values(array_unique(array_filter($ids, fn($x)=>$x>0)));
    if ($ids){
      $whereCe=" WHERE c.".bt($CE_ID)." IN (".implode(',', $ids).")";
    } else {
      $whereCe=" WHERE 1=0"; // no hay nada asociado al evento
    }
  }
}

/* Ejecutar fetch de fichas */
$fichas=[];
$sqlCe="SELECT $selCe $selExtra FROM competidores_evento c $joins $whereCe ORDER BY c.".bt($CE_ID)." ASC";
$stCe=$conexion->prepare($sqlCe);
if ($stCe){
  if ($bindCe){ $stCe->bind_param($typesCe, $bindCe[0]); }
  $stCe->execute(); $r=$stCe->get_result();
  while($r && ($row=$r->fetch_assoc())){
    $id=(int)$row['id'];
    $fichas[$id]=[
      'id'=>$id,
      'dni'=>$row['dni'] ?? null,
      'apellido'=>$row['apellido'] ?? '',
      'nombre'=>$row['nombre'] ?? '',
      'escuela'=>$row['escuela'] ?? '',
      'escuela_logo'=>$row['escuela_logo'] ?? '',
      'foto'=>$row['foto'] ?? '',
      'modalidad'=>$row['modalidad'] ?? '',
      'peso'=>$row['peso'] ?? '',
      'W_base'=>(int)($row['wins'] ?? 0),
      'L_base'=>(int)($row['losses'] ?? 0),
      'D_base'=>(int)($row['draws'] ?? 0),
      'NC_base'=>(int)($row['nc'] ?? 0),
    ];
  }
  $stCe->close();
}

/* =========================================================
   Unificación de fichas por DNI o nombre (tolerante)
   ========================================================= */
$grupos=[]; $idsPorGrupo=[]; $indexApellidoDNI=[]; $indexApellidoGrupoConDNI=[];
foreach($fichas as $f){
  $dniNorm=normalize_dni($f['dni'] ?? ''); $apeNorm=norm_txt2($f['apellido'] ?? '');
  if ($dniNorm){
    $k='dni:'.$dniNorm;
    if (!isset($grupos[$k])){
      $grupos[$k]=[
        'key'=>$k,'dni'=>$dniNorm,'id_base'=>$f['id'],
        'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        'W_acc'=>$f['W_base'],'L_acc'=>$f['L_base'],'D_acc'=>$f['D_base'],'NC_acc'=>$f['NC_base'],'badge'=>''
      ];
      $idsPorGrupo[$k]=[$f['id']];
    } else {
      if ($f['id']>$grupos[$k]['id_base']){
        $grupos[$k]['id_base']=$f['id'];
        foreach(['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src=($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??''); if ($src!=='') $grupos[$k][$fld]=$src;
        }
      }
      $grupos[$k]['W_acc']+=$f['W_base']; $grupos[$k]['L_acc']+=$f['L_base']; $grupos[$k]['D_acc']+=$f['D_base']; $grupos[$k]['NC_acc']+=$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id'];
    }
    if ($apeNorm!==''){ if(!isset($indexApellidoDNI[$apeNorm])) $indexApellidoDNI[$apeNorm]=[]; $indexApellidoDNI[$apeNorm][$dniNorm]=true; }
  }
}
foreach ($indexApellidoDNI as $ape=>$dniSet){ $dnis=array_keys($dniSet); if (count($dnis)===1){ $indexApellidoGrupoConDNI[$ape]='dni:'.$dnis[0]; } }
foreach($fichas as $f){
  $dniNorm=normalize_dni($f['dni'] ?? ''); if ($dniNorm) continue;
  $ape=$f['apellido'] ?? ''; $nom=$f['nombre'] ?? '';
  $apeNorm=norm_txt2($ape);
  $attached=false;
  if ($apeNorm!=='' && isset($indexApellidoGrupoConDNI[$apeNorm])){
    $k=$indexApellidoGrupoConDNI[$apeNorm]; $g=$grupos[$k];
    $nb=first_name($g['nombre'] ?? ''); $nn=first_name($nom);
    if ($nb!=='' && $nn!=='' && is_similar_name($nb,$nn,2)){
      if ($f['id']>$g['id_base']){
        $grupos[$k]['id_base']=$f['id'];
        foreach(['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src=($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??''); if ($src!=='') $grupos[$k][$fld]=$src;
        }
      }
      $grupos[$k]['W_acc']+=$f['W_base']; $grupos[$k]['L_acc']+=$f['L_base']; $grupos[$k]['D_acc']+=$f['D_base']; $grupos[$k]['NC_acc']+=$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id']; $attached=true;
    }
  }
  if ($attached) continue;

  $nameKey=norm_txt2(trim($ape.' '.$nom));
  if ($nameKey){
    $k='nx:'.$nameKey;
    if (!isset($grupos[$k])){
      $grupos[$k]=[
        'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
        'apellido'=>$ape,'nombre'=>$nom,'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
        'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
        'W_acc'=>$f['W_base'],'L_acc'=>$f['L_base'],'D_acc'=>$f['D_base'],'NC_acc'=>$f['NC_base'],'badge'=>''
      ];
      $idsPorGrupo[$k]=[$f['id']];
    } else {
      if ($f['id']>$grupos[$k]['id_base']){
        $grupos[$k]['id_base']=$f['id'];
        foreach(['apellido','nombre','escuela','logo','foto','modalidad','peso'] as $fld){
          $src=($fld==='logo')?($f['escuela_logo']??''):($f[$fld]??''); if ($src!=='') $grupos[$k][$fld]=$src;
        }
      }
      $grupos[$k]['W_acc']+=$f['W_base']; $grupos[$k]['L_acc']+=$f['L_base']; $grupos[$k]['D_acc']+=$f['D_base']; $grupos[$k]['NC_acc']+=$f['NC_base'];
      if (!empty($f['escuela'])) $grupos[$k]['escuelas'][]=$f['escuela'];
      $idsPorGrupo[$k][]=$f['id'];
    }
  } else {
    $k='id:'.$f['id'];
    $grupos[$k]=[
      'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
      'apellido'=>$f['apellido'],'nombre'=>$f['nombre'],'escuela'=>$f['escuela'],'escuelas'=>array_filter([$f['escuela']]),
      'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W_acc'=>$f['W_base'],'L_acc'=>$f['L_base'],'D_acc'=>$f['D_base'],'NC_acc'=>$f['NC_base'],'badge'=>''
    ];
    $idsPorGrupo[$k]=[$f['id']];
  }
}

/* Mapas + suma de resultados */
$global=[]; $mapAnyIdToKey=[];
foreach($grupos as $key=>$g){
  $nombre=trim(($g['apellido'] ?? '').' '.($g['nombre'] ?? ''));
  $escuelasUnicas=array_values(array_unique(isset($g['escuelas'])?array_filter($g['escuelas']):[]));
  $escuelaFinal = !empty($g['escuela']) ? $g['escuela'] : ($escuelasUnicas ? end($escuelasUnicas) : '');
  $global[$key]=[
    'key'=>$key,'dni'=>$g['dni'] ?? null,'id_base'=>$g['id_base'],
    'nombre'=>($nombre!==''?$nombre:'—'),'escuela'=>$escuelaFinal,'logo'=>$g['logo'] ?? '','foto'=>$g['foto'] ?? '',
    'modalidad'=>$g['modalidad'] ?? '','peso'=>$g['peso'] ?? '',
    'W'=>(int)$g['W_acc'],'L'=>(int)$g['L_acc'],'D'=>(int)$g['D_acc'],'NC'=>(int)$g['NC_acc'],
    'badge'=>$g['badge'] ?? ''
  ];
}
foreach($idsPorGrupo as $k=>$ids){ foreach($ids as $cid){ $mapAnyIdToKey[(int)$cid]=$k; } }

/* Heurística desde peleas_evento si NO hay oficial */
if ($peleas){
  foreach($peleas as $p){
    $pid=(int)$p['pelea_id']; if (isset($peleaConRC[$pid])) continue; // evitar doble conteo
    $g=$p['g'] ?? null; if ($g===null) continue;
    $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id'];
    if (isset($mapAnyIdToKey[$az])){
      $k=$mapAnyIdToKey[$az];
      if ($g==='empate') $global[$k]['D']++; elseif($g==='azul') $global[$k]['W']++; elseif($g==='rojo') $global[$k]['L']++;
    }
    if (isset($mapAnyIdToKey[$ro])){
      $k=$mapAnyIdToKey[$ro];
      if ($g==='empate') $global[$k]['D']++; elseif($g==='rojo') $global[$k]['W']++; elseif($g==='azul') $global[$k]['L']++;
    }
  }
}

/* Resultados oficiales */
if ($hasPeleas && $C_ROJO && $C_AZUL) {
  $sqlRC="SELECT rc.ganador_color, pe.".bt($C_ROJO)." AS rid, pe.".bt($C_AZUL)." AS aid
          FROM resultados_combates rc
          LEFT JOIN peleas_evento pe ON pe.id=rc.pelea_id";
  $bind=null; $types='';
  if ($scope==='evento' && $evento_id_actual){ $sqlRC.=" WHERE rc.evento_id=?"; $bind=[$evento_id_actual]; $types='i'; }
  $st=$conexion->prepare($sqlRC);
  if ($st){
    if ($bind){ $st->bind_param($types, $bind[0]); }
    $st->execute(); $res=$st->get_result();
    while($res && ($row=$res->fetch_assoc())){
      $gc=strtolower((string)$row['ganador_color']);
      $rid=(int)($row['rid'] ?? 0); $aid=(int)($row['aid'] ?? 0);
      if (isset($mapAnyIdToKey[$rid])){
        $k=$mapAnyIdToKey[$rid];
        if ($gc==='rojo') $global[$k]['W']++; elseif($gc==='azul') $global[$k]['L']++; elseif($gc==='empate') $global[$k]['D']++;
      }
      if (isset($mapAnyIdToKey[$aid])){
        $k=$mapAnyIdToKey[$aid];
        if ($gc==='azul') $global[$k]['W']++; elseif($gc==='rojo') $global[$k]['L']++; elseif($gc==='empate') $global[$k]['D']++;
      }
    }
    $st->close();
  }
}

/* ===== Filtros/orden ===== */
$busca = trim((string)($_GET['q'] ?? ''));
$orden = (string)($_GET['sort'] ?? 'wins'); // wins|name|gym
$lista = array_values($global);
if ($busca!==''){
  $q = mb_strtolower($busca,'UTF-8'); $tmp=[];
  foreach($lista as $c){ $s=mb_strtolower(($c['nombre'].' '.$c['escuela']), 'UTF-8'); if (strpos($s,$q)!==false) $tmp[]=$c; }
  $lista=$tmp;
}
usort($lista,function($a,$b) use($orden){
  if ($orden==='name'){ return strnatcasecmp($a['nombre'],$b['nombre']); }
  if ($orden==='gym'){  return strnatcasecmp($a['escuela'] ?? '', $b['escuela'] ?? ''); }
  $da = $b['W'] <=> $a['W']; if ($da) return $da;
  $db = ($a['L'] <=> $b['L']); if ($db) return $db;
  return strnatcasecmp($a['nombre'],$b['nombre']);
});
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>🏆 Ranking + Admin — <?= $scope==='evento' ? ('Evento #'.(int)$evento_id_actual) : 'GLOBAL' ?></title>
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
    .card{background:#fff;border:1px solid var(--card-b);border-radius:12px;padding:12px;margin:12px 0}
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
    @media (max-width:900px){ .grid{grid-template-columns:1fr} }
    .rowlink{display:block;text-decoration:none;color:inherit}
  </style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>
<div class="wrap">

  <h2>🏆 Ranking + Administración — <span class="muted"><?= $scope==='evento' ? ('Evento #'.(int)$evento_id_actual) : 'GLOBAL' ?></span></h2>

  <?php if (!empty($flash_ok)): ?><div class="ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if (!empty($flash_error)): ?><div class="bad"><?= h($flash_error) ?></div><?php endif; ?>

  <?php if ($DEBUG): ?>
    <div class="card"><div class="muted">
      DEBUG: scope=<?= h($scope) ?> · evento_id=<?= h($evento_id_actual ?? 'null') ?><br>
      DEBUG: CE_EVENTO_ID detectado = <b><?= h($CE_EVENTO_ID ?? 'NULL (using fallback por peleas_evento)') ?></b><br>
      DEBUG: idsEventoViaPE = <?= h(implode(',', array_keys($idsEventoViaPE))) ?>
    </div></div>
  <?php endif; ?>

  <!-- Selector de alcance/filtros -->
  <div class="card">
    <form method="get" class="row" action="">
      <label>Alcance
        <select class="input" name="scope">
          <option value="global" <?= $scope==='global'?'selected':''; ?>>GLOBAL (todos)</option>
          <option value="evento" <?= $scope==='evento'?'selected':''; ?>>Por evento</option>
        </select>
      </label>
      <label>Evento ID
        <input class="input" type="number" name="evento_id" value="<?= h($evento_id_actual ?? '') ?>" style="width:140px">
      </label>
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

  <!-- 🔎 Traer/Prefill -->
  <div class="card">
    <h3 style="margin:0 0 8px">🔎 Buscar/Traer datos del competidor</h3>
    <form method="get" class="row" action="">
      <input type="hidden" name="scope" value="<?= h($scope) ?>">
      <?php if ($scope==='evento' && $evento_id_actual): ?>
        <input type="hidden" name="evento_id" value="<?= (int)$evento_id_actual ?>">
      <?php endif; ?>
      <label>ID <input class="input" type="number" name="comp_id" value="<?= h($_GET['comp_id'] ?? '') ?>" style="width:140px"></label>
      <?php if ($CE_DNI): ?>
      <label>DNI <input class="input" type="text" name="dni" value="<?= h($_GET['dni'] ?? '') ?>" style="width:160px"></label>
      <?php endif; ?>
      <button class="btn" type="submit">Traer datos</button>
    </form>
  </div>

  <!-- 👤 Actualizar -->
  <div class="card">
    <h3 style="margin:0 0 8px">👤 Actualizar competidor</h3>
    <form method="post" class="grid" action="">
      <input type="hidden" name="action" value="guardar_competidor">
      <label>ID Competidor <input class="input" type="number" name="id" value="<?= h($prefill['id']) ?>" required></label>
      <?php if ($CE_DNI): ?><label>DNI <input class="input" type="text" name="dni" value="<?= h($prefill['dni']) ?>"></label><?php endif; ?>
      <label>Apellido <input class="input" type="text" name="apellido" value="<?= h($prefill['apellido']) ?>"></label>
      <label>Nombre   <input class="input" type="text" name="nombre"   value="<?= h($prefill['nombre']) ?>"></label>
      <label>Academia <input class="input" type="text" name="escuela_nombre" value="<?= h($prefill['escuela_nombre']) ?>"></label>
      <label>Logo (URL) <input class="input" type="text" name="escuela_logo" value="<?= h($prefill['escuela_logo']) ?>"></label>
      <label>Foto (URL) <input class="input" type="text" name="foto_competidor" value="<?= h($prefill['foto_competidor']) ?>"></label>
      <label>Modalidad ID <input class="input" type="number" name="modalidad_id" value="<?= h($prefill['modalidad_id']) ?>"></label>
      <label>Peso ID <input class="input" type="number" name="peso_id" value="<?= h($prefill['peso_id']) ?>"></label>
      <?php if ($CE_EVENTO_ID): ?><label>Evento ID <input class="input" type="number" name="evento_id" value="<?= h($prefill['evento_id']) ?>"></label><?php endif; ?>
      <div style="grid-column:1/-1"><button class="btn" type="submit">💾 Guardar competidor</button></div>
    </form>
  </div>

  <!-- ⚡ Carga rápida -->
  <div class="card">
    <h3 style="margin:0 0 8px">⚡ Carga rápida de competidor</h3>
    <form method="post" class="grid" action="">
      <input type="hidden" name="action" value="crear_competidor">
      <?php if ($CE_DNI): ?><label>DNI <input class="input" type="text" name="dni"></label><?php endif; ?>
      <label>Apellido <input class="input" type="text" name="apellido" required></label>
      <label>Nombre   <input class="input" type="text" name="nombre" required></label>
      <label>Academia <input class="input" type="text" name="escuela_nombre"></label>
      <label>Logo (URL) <input class="input" type="text" name="escuela_logo"></label>
      <label>Foto (URL) <input class="input" type="text" name="foto_competidor"></label>
      <label>Modalidad ID <input class="input" type="number" name="modalidad_id"></label>
      <label>Peso ID <input class="input" type="number" name="peso_id"></label>
      <?php if ($CE_EVENTO_ID): ?>
        <label>Evento ID (vacío usa el del scope)
          <input class="input" type="number" name="evento_id" value="<?= h($scope==='evento' && $evento_id_actual ? $evento_id_actual : '') ?>">
        </label>
      <?php endif; ?>
      <div style="grid-column:1/-1"><button class="btn" type="submit">➕ Crear competidor</button></div>
    </form>
  </div>

  <!-- 📝 Resultados -->
  <div class="card">
    <h3 style="margin:0 0 8px">📝 Cargar / actualizar resultado</h3>
    <form method="post" class="grid" action="">
      <input type="hidden" name="action" value="guardar_resultado">
      <label>Pelea ID <input class="input" type="number" name="pelea_id" required></label>
      <label>Evento ID (si vacío usa sesión) <input class="input" type="number" name="evento_id" value="<?= h($evento_id_actual ?? '') ?>"></label>
      <label>Ganador
        <select class="input" name="ganador_color" required>
          <option value="rojo">rojo</option><option value="azul">azul</option><option value="empate">empate</option>
        </select>
      </label>
      <label>Ganador ID (opcional) <input class="input" type="number" name="ganador_id"></label>
      <label>Método <input class="input" type="text" name="metodo" maxlength="10"></label>
      <label>Detalle <input class="input" type="text" name="detalle" maxlength="255" placeholder="Ej.: PTS — decisión unánime"></label>
      <label>Puntos Rojo <input class="input" type="number" name="puntos_rojo" value="0"></label>
      <label>Puntos Azul <input class="input" type="number" name="puntos_azul" value="0"></label>
      <div style="grid-column:1/-1;display:flex;gap:8px">
        <button class="btn" type="submit">💾 Guardar resultado</button>
        <a class="btn2" href="resultados_combates.php">👁 Ver historial</a>
      </div>
    </form>
  </div>

  <!-- 📊 Ranking -->
  <div class="card">
    <table aria-label="Ranking de competidores">
      <thead>
        <tr>
          <th style="text-align:left">Competidor</th>
          <th style="text-align:left">Academia</th>
          <th>Modalidad</th><th>Peso</th>
          <th>W</th><th>L</th><th>D</th><th>NC</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$lista): ?>
        <tr><td colspan="8" class="muted" style="text-align:center">Sin registros para este alcance.</td></tr>
      <?php else: foreach($lista as $c):
        $nombre = trim($c['nombre']) ?: '—';
        $perfilUrl = !empty($c['dni'])
          ? 'ver_competidor_ranking.php?dni='.urlencode($c['dni'])
          : 'ver_competidor_ranking.php?id='.(int)$c['id_base'];
        $perfilUrl .= '&scope='.$scope.($scope==='evento' && $evento_id_actual ? '&evento_id='.(int)$evento_id_actual : '');
      ?>
        <tr>
          <td style="text-align:left">
            <a class="rowlink" href="<?= h($perfilUrl) ?>">
              <strong><?= h($nombre) ?></strong>
              <div class="muted" style="font-size:12px">Ficha base: <?= (int)$c['id_base'] ?><?= !empty($c['dni'])?' • DNI: '.h($c['dni']):'' ?></div>
            </a>
          </td>
          <td style="text-align:left"><?= h($c['escuela'] ?: '—') ?></td>
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
    <p class="muted" style="margin-top:8px">
      • Si <code>competidores_evento</code> tiene columna de evento (<?= h($CE_EVENTO_ID ?? '—') ?>), se filtra por ahí; si no, se usa la vinculación en <code>peleas_evento</code> (fallback).<br>
      • Los resultados de <code>resultados_combates</code> prevalecen; la heurística por jueces/peleas se usa solo si no hay oficial para esa pelea.
    </p>
  </div>

</div>
</body>
</html>
