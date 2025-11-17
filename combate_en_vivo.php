<?php
/* ============================================
   COMBATE EN VIVO — SOLO MESA / CRONÓMETRO
   - Sin overlay, sin transmisiones, sin sincronizar con otros equipos.
   - Solo cronómetro y datos de la pelea del sistema.
   - Marca ganador directo + tipo de victoria
   - Al finalizar salta a la próxima pelea (pero SIN auto iniciar).
   ============================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma', 'no-cache');
header('Expires', '0');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
$__BUILD = @filemtime(__FILE__) ?: time();

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers PHP (compatibles con PHP 5) ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }

function table_exists($db, $name) {
  if (!($db instanceof mysqli)) return false;
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) {
    $ok = (bool)$r->num_rows;
    $r->close();
    return $ok;
  }
  return false;
}

function has_col($db, $table, $col) {
  if (!($db instanceof mysqli)) return false;
  $t=$db->real_escape_string($table);
  $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function get_int_qs($src, $key, $def=null) {
  if (!isset($src[$key])) return $def;
  $v = trim((string)$src[$key]);
  if ($v === '' || !preg_match('/^-?\d+$/', $v)) return $def;
  return (int)$v;
}
function json_clean_headers(){
  while (ob_get_level()) { ob_end_clean(); }
  header_remove('Set-Cookie');
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  header('Pragma', 'no-cache');
}

/* ===== Ruta de resultados (opcional después) ===== */
$RESULTADOS_RUTA = 'resultados_combates.php';

/* ===== Sonidos ===== */
$LOCAL_SND_DIR = __DIR__ . '/assets/sounds/';   // Ruta física
$WEB_SND_BASE  = 'assets/sounds/';              // Ruta web relativa

function pickSoundFile($localDir, $webBase, $candidates) {
  foreach ($candidates as $f) {
    if (@is_file($localDir . $f)) {
      return $webBase . $f;
    }
  }
  return $webBase . $candidates[0];
}

$SND_START    = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, array('campana_inicio.mp3','ring_start_bell.mp3','inicio_round.mp3','start.mp3'));
$SND_WARN10   = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, array('segundos_afuera_10s.mp3','segundos_afuera.mp3','10s.mp3','aviso10.mp3'));
$SND_ROUNDEND = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, array('fin_round.mp3','ring_end_bell.mp3','gong_fin.mp3'));
$SND_RESTEND  = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, array('fin_descanso.mp3','inicio_round.mp3','ring_start_bell.mp3'));
$SND_FIGHTEND = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, array('fin_pelea.mp3','fight_end.mp3','ring_end_bell.mp3'));

/* ===== Existe resultados_combates? ===== */
$HAS_RESULTADOS = table_exists($conexion,'resultados_combates');

/* ====================================================
   ÚNICO ENDPOINT AJAX: FINALIZAR (guardar resultado)
   - Recibe ganador directo + tipo de victoria.
   - Calcula próxima pelea del mismo evento (si existe).
   - Guarda en resultados_combates (si existe la tabla).
   ==================================================== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'finalizar') {
  ini_set('display_errors', '0');
  json_clean_headers();

  $pelea_id = get_int_qs($_POST, 'pelea_id', 0);
  if ($pelea_id === null || $pelea_id <= 0) {
    echo json_encode(array('ok'=>false,'error'=>'pelea_id_invalido'));
    exit;
  }
  $_SESSION['pelea_id_actual'] = (int)$pelea_id;

  // Ganador directo + tipo de victoria
  $ganador = strtolower(trim((string)(isset($_POST['ganador']) ? $_POST['ganador'] : (isset($_POST['manual_win']) ? $_POST['manual_win'] : ''))));
  $tipoRaw = (string)(isset($_POST['tipo']) ? $_POST['tipo'] : (isset($_POST['manual_tipo']) ? $_POST['manual_tipo'] : ''));
  $tipo    = strtoupper(trim($tipoRaw));

  $round_fin = get_int_qs($_POST, 'round_fin', null);
  if ($round_fin === null) {
    $round_fin = get_int_qs($_POST, 'manual_round_fin', 0);
    if ($round_fin === null) $round_fin = 0;
  }
  $time_fin = trim((string)(isset($_POST['time']) ? $_POST['time'] : (isset($_POST['manual_time']) ? $_POST['manual_time'] : '')));

  // Permitir SIN DECISIÓN (NC) sin ganador explícito
  if (!in_array($ganador, array('azul','rojo','empate'), true)) {
    if ($tipo === 'NC') {
      // Internamente lo tratamos como "empate técnico" para que no rompa,
      // pero visualmente se mostrará "SIN DECISIÓN".
      $ganador = 'empate';
    } else {
      echo json_encode(array(
        'ok'=>false,
        'error'=>'ganador_invalido',
        'msg'=>'Tenés que elegir el ganador del combate (Azul, Rojo o Empate).'
      ));
      exit;
    }
  }

  if ($tipo === '') {
    $tipo = 'PTS'; // por defecto puntos
  }

  // Texto detalle según tipo
  $detalleParts = array();
  switch ($tipo) {
    case 'PTS': $detalleParts[] = 'por puntos'; break;
    case 'KO':  $detalleParts[] = 'por KO'; break;
    case 'KOT':
    case 'TKO': $detalleParts[] = 'por KOT'; break;
    case 'AB':
    case 'ABANDONO': $detalleParts[] = 'por abandono'; break;
    case 'DM':
    case 'DEC_MEDICA': $detalleParts[] = 'por decisión médica'; break;
    case 'SUB':
    case 'SUM':
    case 'SURRENDER': $detalleParts[] = 'por sumisión'; break;
    case 'DQ': $detalleParts[] = 'por descalificación'; break;
    case 'NC': $detalleParts[] = 'sin decisión'; break;
    default:   $detalleParts[] = 'por '.$tipo; break;
  }
  if ($round_fin > 0) {
    $detalleParts[] = 'en R'.$round_fin;
  }
  if ($time_fin !== '') {
    $detalleParts[] = 'tiempo '.$time_fin;
  }
  $detalle = implode(' ', $detalleParts);
  $metodo  = $tipo;

  // Descubrir columnas de peleas_evento
  $C_EVENTO    = has_col($conexion,'peleas_evento','evento_id') ? 'evento_id' : null;
  $C_GAN_COLOR = has_col($conexion,'peleas_evento','ganador_color') ? 'ganador_color'
                : (has_col($conexion,'peleas_evento','ganador') ? 'ganador' : null);
  $C_ESTADO    = has_col($conexion,'peleas_evento','estado') ? 'estado' : null;
  $C_DETALLE   = has_col($conexion,'peleas_evento','detalle_resultado') ? 'detalle_resultado'
                : (has_col($conexion,'peleas_evento','resolucion') ? 'resolucion' : null);
  $C_AZUL_ID   = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id'
                : (has_col($conexion,'peleas_evento','azul_id') ? 'azul_id' : null);
  $C_ROJO_ID   = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id'
                : (has_col($conexion,'peleas_evento','rojo_id') ? 'rojo_id' : null);
  $C_GAN_ID    = has_col($conexion,'peleas_evento','ganador_id') ? 'ganador_id' : null;

  // Columna de número de pelea (para buscar la próxima)
  $C_NUMERO = null;
  foreach(array('numero','nro','orden','n_orden','num') as $cand){
    if (has_col($conexion,'peleas_evento',$cand)){ $C_NUMERO=$cand; break; }
  }

  $azul_id = null;
  $rojo_id = null;
  $evento_id_fin = null;
  $numero_actual = null;

  // Cargar datos básicos de la pelea actual
  $cols = array();
  $cols[] = $C_EVENTO ? bt($C_EVENTO).' AS ev' : 'NULL AS ev';
  $cols[] = $C_AZUL_ID ? bt($C_AZUL_ID).' AS az' : 'NULL AS az';
  $cols[] = $C_ROJO_ID ? bt($C_ROJO_ID).' AS ro' : 'NULL AS ro';
  $cols[] = $C_NUMERO  ? bt($C_NUMERO).'  AS pnum' : 'NULL AS pnum';

  $sqlSel = "SELECT ".implode(', ', $cols)." FROM peleas_evento WHERE id=? LIMIT 1";
  if ($stmt = $conexion->prepare($sqlSel)) {
    $stmt->bind_param('i',$pelea_id);
    $stmt->execute();
    $stmt->bind_result($evento_id_fin,$azul_id,$rojo_id,$numero_actual);
    $stmt->fetch();
    $stmt->close();
  }
  $numero_actual = is_null($numero_actual) ? null : (int)$numero_actual;

  // Marcar pelea como finalizada + ganador + detalle
  if ($C_ESTADO && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_ESTADO)."='finalizada' WHERE id=? LIMIT 1"))){
    $st->bind_param('i',$pelea_id); $st->execute(); $st->close();
  }
  if ($C_GAN_COLOR && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_COLOR)."=? WHERE id=? LIMIT 1"))){
    $val = ($ganador==='empate'?'empate':$ganador);
    $st->bind_param('si',$val,$pelea_id); $st->execute(); $st->close();
  }
  if ($C_GAN_ID){
    if ($ganador==='azul' && $azul_id) {
      if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=? WHERE id=? LIMIT 1")){
        $st->bind_param('ii', $azul_id, $pelea_id); $st->execute(); $st->close();
      }
    } elseif ($ganador==='rojo' && $rojo_id) {
      if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=? WHERE id=? LIMIT 1")){
        $st->bind_param('ii', $rojo_id, $pelea_id); $st->execute(); $st->close();
      }
    } else {
      $conexion->query("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=NULL WHERE id=".(int)$pelea_id." LIMIT 1");
    }
  }
  if ($C_DETALLE && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_DETALLE)."=? WHERE id=? LIMIT 1"))){
    $txt = strtoupper($metodo).' — '.$detalle;
    $st->bind_param('si',$txt,$pelea_id); $st->execute(); $st->close();
  }

  /* === GUARDAR EN resultados_combates (ranking) === */
  if ($HAS_RESULTADOS) {
    // evento_id: si no vino en la pelea, usamos el de sesión si existe
    if (is_null($evento_id_fin) || (int)$evento_id_fin <= 0) {
      $evento_id_fin = isset($_SESSION['evento_id_actual']) ? (int)$_SESSION['evento_id_actual'] : 0;
    }
    $evento_id_rc = (int)$evento_id_fin;

    if ($evento_id_rc > 0) {
      $ganador_color = $ganador; // 'azul', 'rojo' o 'empate'
      $ganador_id    = null;
      if ($ganador_color === 'azul' && $azul_id) {
        $ganador_id = (int)$azul_id;
      } elseif ($ganador_color === 'rojo' && $rojo_id) {
        $ganador_id = (int)$rojo_id;
      }

      // Por ahora no manejamos puntos en este layout → 0
      $puntos_rojo = 0;
      $puntos_azul = 0;

      $sqlUp = "INSERT INTO resultados_combates
                (pelea_id, evento_id, ganador_color, ganador_id, metodo, detalle, puntos_rojo, puntos_azul)
                VALUES (?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                  ganador_color = VALUES(ganador_color),
                  ganador_id    = VALUES(ganador_id),
                  metodo        = VALUES(metodo),
                  detalle       = VALUES(detalle),
                  puntos_rojo   = VALUES(puntos_rojo),
                  puntos_azul   = VALUES(puntos_azul)";
      if ($st = $conexion->prepare($sqlUp)) {
        $st->bind_param(
          'iissssii',
          $pelea_id,
          $evento_id_rc,
          $ganador_color,
          $ganador_id,
          $metodo,
          $detalle,
          $puntos_rojo,
          $puntos_azul
        );
        $st->execute();
        $st->close();
      }
    }
  }

  // Calcular próxima pelea del mismo evento (si existe)
  $next_id = null;
  if (!is_null($evento_id_fin) && (int)$evento_id_fin > 0 && $C_EVENTO) {
    if ($C_NUMERO && !is_null($numero_actual)) {
      // Buscar por número de pelea > al actual
      $sqlNext = "SELECT id FROM peleas_evento
                  WHERE ".bt($C_EVENTO)." = ? AND ".bt($C_NUMERO)." > ? ";
      if ($C_ESTADO) {
        $sqlNext .= "AND (".bt($C_ESTADO)." IS NULL OR ".bt($C_ESTADO)." <> 'finalizada') ";
      }
      $sqlNext .= "ORDER BY ".bt($C_NUMERO)." ASC LIMIT 1";
      if ($st = $conexion->prepare($sqlNext)) {
        $st->bind_param('ii', $evento_id_fin, $numero_actual);
        $st->execute(); $st->bind_result($nid);
        if ($st->fetch()) { $next_id = (int)$nid; }
        $st->close();
      }
    }
    // Respaldo por id si no se encontró por número
    if (is_null($next_id)) {
      $sqlNext = "SELECT id FROM peleas_evento
                  WHERE ".bt($C_EVENTO)." = ? AND id > ? ";
      if ($C_ESTADO) {
        $sqlNext .= "AND (".bt($C_ESTADO)." IS NULL OR ".bt($C_ESTADO)." <> 'finalizada') ";
      }
      $sqlNext .= "ORDER BY id ASC LIMIT 1";
      if ($st = $conexion->prepare($sqlNext)) {
        $st->bind_param('ii', $evento_id_fin, $pelea_id);
        $st->execute(); $st->bind_result($nid);
        if ($st->fetch()) { $next_id = (int)$nid; }
        $st->close();
      }
    }
  } else {
    // Sin evento_id, buscar siguiente por id en general
    $sqlNext = "SELECT id FROM peleas_evento WHERE id > ? ";
    if ($C_ESTADO) {
      $sqlNext .= "AND (".bt($C_ESTADO)." IS NULL OR ".bt($C_ESTADO)." <> 'finalizada') ";
    }
    $sqlNext .= "ORDER BY id ASC LIMIT 1";
    if ($st = $conexion->prepare($sqlNext)) {
      $st->bind_param('i', $pelea_id);
      $st->execute(); $st->bind_result($nid);
      if ($st->fetch()) { $next_id = (int)$nid; }
      $st->close();
    }
  }

  $redir    = $RESULTADOS_RUTA.'?pelea_id='.$pelea_id;
  if (!is_null($evento_id_fin) && (int)$evento_id_fin>0) {
    $redir .= '&evento_id='.(int)$evento_id_fin;
  }
  // NO mandamos &start=1 → la próxima pelea no arranca sola
  $next_url = $next_id ? ('combate_en_vivo.php?pelea_id='.$next_id) : null;

  echo json_encode(array(
    'ok'       => true,
    'ganador'  => $ganador,
    'metodo'   => $metodo,
    'detalle'  => $detalle,
    'redirect' => $redir,
    'next_id'  => $next_id,
    'next_url' => $next_url
  ));
  exit;
}

/* ====================================================
   VISTA HTML — MESA (CRONÓMETRO + RESULTADO DIRECTO)
   ==================================================== */

$pelea_id = get_int_qs($_GET, 'pelea_id', 0);
if ($pelea_id === null || $pelea_id <= 0) {
  if (!empty($_SESSION['pelea_id_actual'])) {
    $pelea_id = (int)$_SESSION['pelea_id_actual'];
  }
}
if ($pelea_id > 0) {
  $_SESSION['pelea_id_actual'] = (int)$pelea_id;
}
if ($pelea_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>';
  exit;
}

/* QS (solo vista) */
$nroQS    = get_int_qs($_GET,'nro',null);
$rondasQS = get_int_qs($_GET,'rondas',null);
$durQS    = get_int_qs($_GET,'dur',null);
$restQS   = get_int_qs($_GET,'rest',null);
// autoQS ya no se usa para autostart, lo dejamos por compatibilidad
$autoQS   = isset($_GET['start']) && (string)$_GET['start']==='1';

$timerDur  = ($durQS  && $durQS  > 0) ? $durQS  : 120;
$timerRest = ($restQS && $restQS > 0) ? $restQS : 60;

/* Datos de pelea + competidores */
$evento_id = null;
$rondasEsperadas = 3;
$pelea_numero = null;

$azul_nom='Azul'; $rojo_nom='Rojo';
$azul_logo=''; $rojo_logo='';
$azul_escuela=''; $rojo_escuela='';
$azul_div=''; $rojo_div='';
$azul_peso=''; $rojo_peso='';
$azul_mod='';  $rojo_mod='';

if (table_exists($conexion,'peleas_evento')) {
  $C_EVENTO  = has_col($conexion,'peleas_evento','evento_id') ? 'evento_id' : null;
  $C_AZUL_ID = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id'
              : (has_col($conexion,'peleas_evento','azul_id') ? 'azul_id' : null);
  $C_ROJO_ID = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id'
              : (has_col($conexion,'peleas_evento','rojo_id') ? 'rojo_id' : null);
  $C_DUR  = null;
  foreach (array('duracion_round','duracion','round_duracion','tiempo_round') as $cand) {
    if (has_col($conexion,'peleas_evento',$cand)) { $C_DUR=$cand; break; }
  }
  $C_DESC = null;
  foreach (array('descanso','tiempo_descanso','descanso_seg') as $cand) {
    if (has_col($conexion,'peleas_evento',$cand)) { $C_DESC=$cand; break; }
  }
  $C_RONDAS = null;
  foreach(array('rondas','rounds','total_rounds','rondas_total','rondas_totales',
           'cantidad_rondas','cant_rondas','n_rondas','numero_rondas',
           'rounds_total','rounds_totales','rondas_pelea','rondas_conf',
           'rondas_configuradas') as $cand){
    if (has_col($conexion,'peleas_evento',$cand)){ $C_RONDAS=$cand; break; }
  }
  $C_NUMERO = null;
  foreach(array('numero','nro','orden','n_orden','num') as $cand){
    if(has_col($conexion,'peleas_evento',$cand)){ $C_NUMERO=$cand; break; }
  }

  $sel = array();
  $sel[] = $C_EVENTO ? bt($C_EVENTO).' AS ev' : 'NULL AS ev';
  $sel[] = $C_RONDAS ? bt($C_RONDAS).' AS rds' : 'NULL AS rds';
  $sel[] = $C_NUMERO ? bt($C_NUMERO).' AS pnum' : 'NULL AS pnum';
  $sel[] = $C_DUR  ? bt($C_DUR).'  AS dur'  : 'NULL AS dur';
  $sel[] = $C_DESC ? bt($C_DESC).' AS dsc'  : 'NULL AS dsc';
  if ($C_AZUL_ID) $sel[] = bt($C_AZUL_ID).' AS az';
  if ($C_ROJO_ID) $sel[] = bt($C_ROJO_ID).' AS ro';

  $sqlSel = "SELECT ".implode(', ', $sel)." FROM peleas_evento WHERE id=? LIMIT 1";
  if ($st=$conexion->prepare($sqlSel)){
    $st->bind_param('i',$pelea_id);
    $st->execute();
    $st->store_result();
    $ev=$rds=$pnum=$dur=$dsc=null;
    $az_id=$ro_id=null;
    if ($C_AZUL_ID && $C_ROJO_ID) {
      $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$az_id,$ro_id);
    } elseif ($C_AZUL_ID) {
      $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$az_id);
    } elseif ($C_ROJO_ID) {
      $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$ro_id);
    } else {
      $st->bind_result($ev,$rds,$pnum,$dur,$dsc);
    }
    if($st->fetch()){
      $evento_id = is_null($ev)?null:(int)$ev;
      $rds = (int)$rds;
      if ($rds>0) $rondasEsperadas = $rds;
      if (!is_null($pnum) && $pnum!=='') $pelea_numero = (int)$pnum;
      if (isset($dur) && (int)$dur > 0 && !($durQS  && $durQS  > 0))  $timerDur  = (int)$dur;
      if (isset($dsc) && (int)$dsc > 0 && !($restQS && $restQS > 0)) $timerRest = (int)$dsc;
    }
    $st->close();
  }

  $ids = array();
  if (!empty($az_id) && (int)$az_id>0) $ids[] = (int)$az_id;
  if (!empty($ro_id) && (int)$ro_id>0) $ids[] = (int)$ro_id;

  if (table_exists($conexion,'competidores_evento') && $ids) {
    $C_ESCUELA = has_col($conexion,'competidores_evento','escuela') ? 'escuela'
                  : (has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre'
                  : (has_col($conexion,'competidores_evento','gimnasio') ? 'gimnasio' : null));
    $LOGO_CANDS = array('escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela');
    $C_LOGO = null;
    foreach($LOGO_CANDS as $c){
      if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; }
    }

    $haveDV  = table_exists($conexion,'divisiones_evento');
    $haveCP  = table_exists($conexion,'categorias_peso_evento');
    $haveMD  = table_exists($conexion,'modalidades_evento');

    $C_DIV_ID  = has_col($conexion,'competidores_evento','division_id') ? 'division_id'
                  : (has_col($conexion,'competidores_evento','id_division')?'id_division':null);
    $C_DIV_TXT = has_col($conexion,'competidores_evento','division') ? 'division' : null;

    $C_PESO_ID  = has_col($conexion,'competidores_evento','categoria_peso_id') ? 'categoria_peso_id'
                  : (has_col($conexion,'competidores_evento','id_categoria_peso')?'id_categoria_peso':null);
    $C_PESO_TXT = has_col($conexion,'competidores_evento','peso_kg') ? 'peso_kg'
                   : (has_col($conexion,'competidores_evento','peso') ? 'peso'
                   : (has_col($conexion,'competidores_evento','categoria_peso')?'categoria_peso':null));

    $C_MOD_ID  = has_col($conexion,'competidores_evento','modalidad_id') ? 'modalidad_id' : null;
    $C_MOD_TXT = has_col($conexion,'competidores_evento','modalidad') ? 'modalidad' : null;

    $cols = "ce.id, TRIM(CONCAT(COALESCE(ce.apellido,''),' ',COALESCE(ce.nombre,''))) AS nom";
    $cols .= $C_ESCUELA?(", ce.".bt($C_ESCUELA)." AS esc") : ", NULL AS esc";
    $cols .= $C_LOGO?(", ce.".bt($C_LOGO)." AS logo") : ", NULL AS logo";

    if ($haveDV && $C_DIV_ID)  { $cols .= ", dv.nombre AS division"; }
    elseif ($C_DIV_TXT)        { $cols .= ", ce.".bt($C_DIV_TXT)." AS division"; }
    else                       { $cols .= ", NULL AS division"; }

    if ($haveCP && $C_PESO_ID) { $cols .= ", cp.nombre AS peso"; }
    elseif ($C_PESO_TXT)       { $cols .= ", ce.".bt($C_PESO_TXT)." AS peso"; }
    else                       { $cols .= ", NULL AS peso"; }

    if ($haveMD && $C_MOD_ID)  { $cols .= ", md.nombre AS modalidad"; }
    elseif ($C_MOD_TXT)        { $cols .= ", ce.".bt($C_MOD_TXT)." AS modalidad"; }
    else                       { $cols .= ", NULL AS modalidad"; }

    $joins = "";
    if ($haveDV && $C_DIV_ID)  $joins .= " LEFT JOIN divisiones_evento dv ON dv.id = ce.".bt($C_DIV_ID);
    if ($haveCP && $C_PESO_ID) $joins .= " LEFT JOIN categorias_peso_evento cp ON cp.id = ce.".bt($C_PESO_ID);
    if ($haveMD && $C_MOD_ID)  $joins .= " LEFT JOIN modalidades_evento md ON md.id = ce.".bt($C_MOD_ID);

    $sql = "SELECT $cols FROM competidores_evento ce $joins WHERE ";
    $countIds = count($ids);
    if ($countIds === 1) {
      $sql .= "ce.id = ?";
    } else {
      $sql .= "ce.id IN (?,?)";
    }

    if ($st=$conexion->prepare($sql)){
      if ($countIds === 1) {
        $st->bind_param('i', $ids[0]);
      } else {
        $st->bind_param('ii', $ids[0], $ids[1]);
      }
      $st->execute();
      $st->store_result();
      $st->bind_result($cid,$nom,$esc,$logo,$division,$peso,$modalidad);
      while($st->fetch()){
        if ((int)$cid === (int)($az_id ?? 0)){
          if ($nom) $azul_nom = $nom;
          $azul_escuela = (string)($esc??''); $azul_logo = (string)($logo??'');
          $azul_div = (string)($division??''); $azul_peso = (string)($peso??''); $azul_mod = (string)($modalidad??'');
        } elseif ((int)$cid === (int)($ro_id ?? 0)){
          if ($nom) $rojo_nom = $nom;
          $rojo_escuela = (string)($esc??''); $rojo_logo = (string)($logo??'');
          $rojo_div = (string)($division??''); $rojo_peso = (string)($peso??''); $rojo_mod = (string)($modalidad??'');
        }
      }
      $st->close();
    }
  }
}

/* Overrides QS */
if (!is_null($rondasQS) && $rondasQS > 0) $rondasEsperadas = $rondasQS;
if (!is_null($nroQS)     && $nroQS     > 0) $pelea_numero   = $nroQS;

$return_to = 'ver_peleas_evento.php'.($evento_id?('?evento_id='.(int)$evento_id):'');

$__t_init = isset($timerDur) ? (int)$timerDur : 120;
$__m_init = (int)floor($__t_init/60);
$__s_init = str_pad((string)($__t_init % 60), 2, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🥊 Combate en vivo — <?php
  if (!is_null($pelea_numero)) {
    echo 'Pelea N° '.(int)$pelea_numero.' (ID '.(int)$pelea_id.')';
  } else {
    echo 'Pelea #'.(int)$pelea_id;
  }
?></title>
<style>
  :root{ --panel-bg:#121416; --panel-br:#26313a; --muted:#b6c7d8; }
  body{background:#0c0f12;color:#fff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0}
  .stage{max-width:1200px;margin:0 auto;padding:16px;text-align:center}
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
  .btn-primary{background:#00b894;color:#fff}
  .btn-warn{background:#ffb300}
  .btn-danger{background:#e53935}
  .btn-gray{background:#2a2f35;color:#fff}
  .btn-ghost{background:transparent;border:1px solid #334250}
  .timer{font-size:110px;font-weight:900;line-height:1;letter-spacing:1px}
  .round{font-size:18px;font-weight:700;margin-bottom:6px}
  .muted{color:var(--muted)}
  .winner-chip{padding:10px 14px;border-radius:999px;border:1px solid #2a3a4a;background:#141a20;color:#e5eef7;font-weight:700;cursor:pointer;user-select:none}
  .winner-chip[data-v="azul"]{border-color:#0b5aa6}
  .winner-chip[data-v="rojo"]{border-color:#a60b1f}
  .winner-chip[data-v="empate"]{border-color:#606b7a}
  .winner-chip.active{outline:2px solid #fff2; box-shadow:0 0 0 2px #fff2 inset}
  .winner-chip:hover{filter:brightness(1.1)}
</style>
</head>
<body>
<div class="stage">
  <h2 style="margin:0 0 6px">
    🥊 Combate en vivo —
    <?php if (!is_null($pelea_numero)) : ?>
      Pelea N° <?= (int)$pelea_numero ?> <span class="sub" style="font-weight:400">(ID <?= (int)$pelea_id ?>)</span>
    <?php else: ?>
      Pelea #<?= (int)$pelea_id ?>
    <?php endif; ?>
  </h2>
  <div class="sub" style="margin-bottom:4px">
    <?= $evento_id!==null ? ('Evento #'.(int)$evento_id) : '(sin evento_id)' ?>
    · Rondas configuradas: <b id="lblRondas"><?= (int)$rondasEsperadas ?></b>
  </div>
  <div class="sub" style="margin-bottom:10px;font-size:12px;opacity:0.85">
    Si no se escuchan las campanas, hacé clic en cualquier parte o en <b>Iniciar</b> para habilitar el audio del navegador.
  </div>

  <div class="grid">
    <!-- ROJO -->
    <section class="panel red">
      <div class="corner-title">🔴 RINCÓN ROJO</div>
      <div class="name"><?= h($rojo_nom) ?></div>
      <?php if ($rojo_logo || $rojo_escuela): ?>
      <div class="esc">
        <?php if ($rojo_logo): ?><img src="<?= h($rojo_logo) ?>" alt="Logo escuela roja" loading="lazy"><?php endif; ?>
        <?php if ($rojo_escuela): ?><div class="esc-name"><?= h($rojo_escuela) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($rojo_div): ?><span class="tag">División: <?= h($rojo_div) ?></span><?php endif; ?>
        <?php if ($rojo_peso!==''): ?><span class="tag">Peso: <?= h(is_numeric($rojo_peso)?(0+$rojo_peso).' kg':$rojo_peso) ?></span><?php endif; ?>
        <?php if ($rojo_mod):  ?><span class="tag">Modalidad: <?= h($rojo_mod) ?></span><?php endif; ?>
      </div>
    </section>

    <!-- CENTRO -->
    <section class="panel">
      <div class="round">Round <span id="round">1</span></div>
      <div id="timer" class="timer"><?= $__m_init . ':' . $__s_init ?></div>

      <div class="controls">
        <button id="btnStart" class="btn btn-primary" type="button">▶️ Iniciar</button>
        <button id="btnPause" class="btn btn-warn" type="button">⏸️ Pausar</button>
        <button id="btnReset" class="btn btn-danger" type="button">⟲ Reiniciar</button>
        <button id="btnNext"  class="btn btn-ghost" type="button">⏭️ Siguiente round</button>
      </div>
      <div class="controls">
        <span class="sub">Duración (seg):</span>
        <input id="selDur"  type="number" class="btn btn-gray" style="width:110px" min="30" max="900" step="5" value="<?= (int)$timerDur ?>">
        <span class="sub">Descanso (seg):</span>
        <input id="selRest" type="number" class="btn btn-gray" style="width:110px" min="10" max="600" step="5" value="<?= (int)$timerRest ?>">
      </div>

      <h4 style="margin:14px 0 4px">🏁 Resultado del combate</h4>
      <div class="sub" style="margin-bottom:6px">
        Marcá directamente quién ganó y la forma en que ganó.
      </div>

      <div class="controls">
        <div class="winner-chip" data-v="azul"   id="chipAzul">🔵 Gana Azul</div>
        <div class="winner-chip" data-v="rojo"   id="chipRojo">🔴 Gana Rojo</div>
        <div class="winner-chip" data-v="empate" id="chipEmpate">⚖️ Empate</div>
      </div>

      <div class="controls">
        <select id="selWin" class="btn btn-gray" style="display:none">
          <option value="">— Ganador —</option>
          <option value="azul">🔵 Azul</option>
          <option value="rojo">🔴 Rojo</option>
          <option value="empate">⚖️ Empate</option>
        </select>
        <select id="selTipo" class="btn btn-gray">
          <option value="">— Forma de victoria —</option>
          <option value="PTS">Puntos (PTS)</option>
          <option value="KO">KO</option>
          <option value="KOT">KOT / TKO</option>
          <option value="AB">Abandono</option>
          <option value="DM">Decisión médica</option>
          <option value="SUB">Surrender / Sumisión</option>
          <option value="DQ">Descalificación (DQ)</option>
          <option value="NC">Sin decisión (NC)</option>
        </select>
        <input id="inRoundFin" class="btn btn-gray" type="number" min="0" step="1" placeholder="Round fin (opcional)">
        <input id="inTime" class="btn btn-gray" type="text" placeholder="Tiempo mm:ss (opcional)">
      </div>

      <div class="controls">
        <a class="btn btn-gray" href="<?= h($return_to) ?>">↩️ Volver al listado</a>
        <button id="btnFinish" class="btn btn-danger" type="button">🏁 Finalizar combate</button>
      </div>

      <div id="banner" class="sub" style="margin-top:8px;opacity:.9"></div>
    </section>

    <!-- AZUL -->
    <section class="panel blue">
      <div class="corner-title">🔵 RINCÓN AZUL</div>
      <div class="name"><?= h($azul_nom) ?></div>
      <?php if ($azul_logo || $azul_escuela): ?>
      <div class="esc">
        <?php if ($azul_logo): ?><img src="<?= h($azul_logo) ?>" alt="Logo escuela azul" loading="lazy"><?php endif; ?>
        <?php if ($azul_escuela): ?><div class="esc-name"><?= h($azul_escuela) ?></div><?php endif; ?>
      </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($azul_div): ?><span class="tag">División: <?= h($azul_div) ?></span><?php endif; ?>
        <?php if ($azul_peso!==''): ?><span class="tag">Peso: <?= h(is_numeric($azul_peso)?(0+$azul_peso).' kg':$azul_peso) ?></span><?php endif; ?>
        <?php if ($azul_mod):  ?><span class="tag">Modalidad: <?= h($azul_mod) ?></span><?php endif; ?>
      </div>
    </section>
  </div>
</div>

<!-- Sonidos -->
<audio id="snd-start"    src="<?= h($SND_START) ?>"    preload="auto"></audio>
<audio id="snd-10"       src="<?= h($SND_WARN10) ?>"   preload="auto"></audio>
<audio id="snd-roundend" src="<?= h($SND_ROUNDEND) ?>" preload="auto"></audio>
<audio id="snd-restend"  src="<?= h($SND_RESTEND) ?>"  preload="auto"></audio>
<audio id="snd-fightend" src="<?= h($SND_FIGHTEND) ?>" preload="auto"></audio>

<script>
(function(){
  const peleaId  = <?= (int)$pelea_id ?>;

  // ====== DESBLOQUEO DE AUDIO (MUY IMPORTANTE) ======
  let audioUnlocked = false;
  const soundIds = ['snd-start','snd-10','snd-roundend','snd-restend','snd-fightend'];

  function unlockAudio() {
    if (audioUnlocked) return;
    audioUnlocked = true;
    soundIds.forEach(function(id){
      try {
        const a = document.getElementById(id);
        if (!a) return;
        // Reproducir en silencio una vez para que el navegador lo permita luego
        a.muted = true;
        const p = a.play();
        if (p && typeof p.then === 'function') {
          p.then(function(){
            a.pause();
            a.currentTime = 0;
            a.muted = false;
          }).catch(function(){
            // Si falla, lo intentaremos de nuevo en otro click si hace falta
          });
        } else {
          // Navegadores viejos
          a.pause();
          a.currentTime = 0;
          a.muted = false;
        }
      } catch(e){}
    });
  }

  // Primer click en cualquier lado desbloquea audio
  document.addEventListener('click', function firstClick(){
    unlockAudio();
    document.removeEventListener('click', firstClick);
  }, { once:true });

  function playSound(id){
    try{
      const a = document.getElementById(id);
      if (!a) return;
      a.currentTime = 0;
      a.play().catch(function(err){
        // Si por alguna razón vuelve a bloquear, intentamos desbloquear otra vez
        audioUnlocked = false;
        unlockAudio();
      });
    }catch(e){}
  }

  /* ====== Timer local con corrección de deriva ====== */
  let duration = <?= (int)$timerDur ?>;
  let rest     = <?= (int)$timerRest ?>;
  let remain   = duration;
  let round    = 1;
  let inRest   = false;
  let running  = false;
  let nextTs   = 0;
  let rafId    = 0;

  // Total de rounds configurados desde la vista
  const totalRoundsLabel = document.getElementById('lblRondas');
  let totalRounds = parseInt(totalRoundsLabel ? totalRoundsLabel.textContent : '3', 10);
  if (!Number.isFinite(totalRounds) || totalRounds <= 0) totalRounds = 3;

  const timer   = document.getElementById('timer');
  const rnd     = document.getElementById('round');
  const selDur  = document.getElementById('selDur');
  const selRest = document.getElementById('selRest');
  const btnStart= document.getElementById('btnStart');
  const btnPause= document.getElementById('btnPause');
  const btnReset= document.getElementById('btnReset');
  const btnNext = document.getElementById('btnNext');

  function fmt(s){
    s = Math.max(0, s|0);
    const m  = Math.floor(s/60);
    const ss = String(s%60).padStart(2,'0');
    return m + ':' + ss;
  }
  function paint(){
    timer.textContent = fmt(remain);
    rnd.textContent   = round;
  }

  function step(nowMs){
    if (nowMs >= nextTs){
      if (remain > 0){
        remain--;
        if (remain === 10) {
          playSound('snd-10');
        }
      } else {
        if (!inRest){
          // ✅ Fin de TODOS los rounds configurados: se termina la pelea por tiempo
          if (round >= totalRounds){
            playSound('snd-fightend');
            remain = 0;
            paint();
            running = false;
            return; // NO pasa a descanso ni a otro round
          }
          // Fin de round normal → pasa a descanso
          playSound('snd-roundend');
          inRest = true;
          remain = rest;
        } else {
          // Fin descanso → siguiente round (todavía no llegamos al último)
          playSound('snd-restend');
          inRest = false;
          round++;
          remain = duration;
        }
      }
      paint();
      nextTs += 1000;
    }
    if (running){
      rafId = requestAnimationFrame(step);
    }
  }

  // Sin campana al iniciar / reanudar, pero desbloqueamos audio al iniciar
  btnStart.onclick = function(){
    unlockAudio();
    if (running) return;
    running = true;
    nextTs  = performance.now() + 1000;
    rafId   = requestAnimationFrame(step);
  };

  btnPause.onclick = function(){
    if (!running) return;
    running = false;
    cancelAnimationFrame(rafId);
  };

  btnReset.onclick = function(){
    running = false;
    cancelAnimationFrame(rafId);
    inRest  = false;
    round   = 1;
    remain  = duration;
    paint();
  };

  btnNext.onclick = function(){
    // Botón manual para forzar un round extra si lo necesitás
    running = false;
    cancelAnimationFrame(rafId);
    inRest  = false;
    round++;
    remain  = duration;
    paint();
  };

  selDur.onchange = function(){
    var v = parseInt(selDur.value || duration, 10);
    if (isNaN(v)) v = duration;
    v = Math.max(30, Math.min(900, v));
    duration  = v;
    selDur.value = v;
    if (!inRest && remain > duration) remain = duration;
    paint();
  };
  selRest.onchange = function(){
    var v = parseInt(selRest.value || rest, 10);
    if (isNaN(v)) v = rest;
    v = Math.max(10, Math.min(600, v));
    rest  = v;
    selRest.value = v;
    if (inRest && remain > rest) remain = rest;
    paint();
  };

  // ===== Selección directa de ganador =====
  var selWin     = document.getElementById('selWin');
  var selTipo    = document.getElementById('selTipo');
  var inRoundFin = document.getElementById('inRoundFin');
  var inTime     = document.getElementById('inTime');
  var banner     = document.getElementById('banner');

  var chipAzul   = document.getElementById('chipAzul');
  var chipRojo   = document.getElementById('chipRojo');
  var chipEmpate = document.getElementById('chipEmpate');

  function setWinner(v){
    selWin.value = v || '';
    [chipAzul, chipRojo, chipEmpate].forEach(function(ch){
      ch.classList.toggle('active', ch.getAttribute('data-v') === v);
    });
  }

  chipAzul.addEventListener('click', function(){ setWinner('azul'); });
  chipRojo.addEventListener('click', function(){ setWinner('rojo'); });
  chipEmpate.addEventListener('click', function(){ setWinner('empate'); });

  // Botón finalizar combate
  document.getElementById('btnFinish').onclick = async function(){
    var tipoVal = selTipo.value || '';

    // 🎯 Si NO es "Sin decisión", seguimos obligando a elegir ganador
    if (tipoVal !== 'NC' && !selWin.value){
      alert('Elegí el ganador del combate (Azul, Rojo o Empate).');
      return;
    }

    // Si no se eligió tipo, lo dejamos por defecto en PTS (como antes)
    if (!tipoVal){
      if (!confirm('No elegiste la forma de victoria. ¿Guardar como "PTS" (puntos)?')) {
        return;
      }
      selTipo.value = 'PTS';
      tipoVal = 'PTS';
    }

    // 🟥 Caso especial: SIN DECISIÓN (NC)
    // No hubo ganador ni perdedor ni empate "real".
    // No pedimos ganador y, si viene vacío, lo marcamos internamente como empate
    // solo para que el backend no rompa, pero el texto dirá "SIN DECISIÓN".
    if (tipoVal === 'NC' && !selWin.value){
      setWinner('empate'); // técnico, pero el resultado visible será "Sin decisión"
    }

    if(!confirm('¿Finalizar el combate y guardar el resultado?\nLuego se abrirá la próxima pelea (si existe).')) return;

    running = false;
    cancelAnimationFrame(rafId);
    playSound('snd-fightend');

    var form = new FormData();
    form.append('pelea_id', String(peleaId));
    form.append('ganador',  selWin.value || '');
    form.append('tipo',     selTipo.value || '');
    form.append('round_fin', inRoundFin.value || '');
    form.append('time',      inTime.value || '');

    var ctrl = new AbortController();
    var to   = setTimeout(function(){ ctrl.abort(); }, 20000);

    try{
      var r = await fetch('combate_en_vivo.php?ajax=finalizar', {
        method:'POST',
        body:form,
        cache:'no-store',
        signal:ctrl.signal
      });
      var j = await r.json();
      if (!j || !j.ok){
        alert((j && j.msg) ? j.msg : 'No se pudo finalizar el combate.');
        return;
      }

      // 🟢 Si es NC, mostramos "SIN DECISIÓN", no empate
      var lbl;
      if (j.metodo === 'NC') {
        lbl = '🚫 SIN DECISIÓN';
      } else {
        lbl = (j.ganador==='azul'?'🔵 AZUL':(j.ganador==='rojo'?'🔴 ROJO':'⚖️ EMPATE'));
      }

      banner.innerHTML = '<b>Resultado:</b> '+lbl+' — '+j.metodo+' · <span class="muted">'+(j.detalle||'')+'</span>';

      // Si hay próxima pelea, ir a esa página, pero el reloj quedará parado hasta que vos pongas Iniciar
      setTimeout(function(){
        if (j.next_url) {
          window.location.href = j.next_url;
        } else if (j.redirect) {
          window.location.href = j.redirect;
        }
      }, 800);
    }catch(e){
      alert('Error de red al finalizar el combate.');
    } finally {
      clearTimeout(to);
    }
  };

  // Estado inicial (sin autoStart)
  remain = duration;
  paint();
})();
</script>
</body>
</html>
