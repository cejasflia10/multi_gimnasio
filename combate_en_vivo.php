<?php
/* ============================================
   COMBATE EN VIVO — Mesa (sin jueces)
   - Botones de ganador por round (Azul/Rojo/Empate)
   - Empate global => agrega round extra automático
   - Publica estado en combate_estado para transmisión
   - Endpoints internos:
       • ajax=estado         (JSON para overlay)
       • ajax=set_estado     (persistir cronómetro/pelea)
       • ajax=marcar_actual  (activar pelea/evento)
       • ajax=pausar_actual  (pausar transmisión)
       • ajax=finalizar      (guardar resultado)
   ============================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
$__BUILD = @filemtime(__FILE__) ?: time();

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers PHP ===== */
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
function get_int_qs(array $src, string $key, ?int $def=null): ?int {
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
  header('Pragma: no-cache');
}

/* ====== Tabla combate_estado: crear y asegurar columnas ====== */
function ensure_combate_estado(mysqli $db){
  $db->query("
    CREATE TABLE IF NOT EXISTS combate_estado (
      id INT AUTO_INCREMENT PRIMARY KEY,
      evento_id INT NOT NULL UNIQUE,
      pelea_actual_id INT DEFAULT NULL,
      activo TINYINT(1) NOT NULL DEFAULT 0,
      actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  ");

  // Esquema NUEVO (B): sincronización con running/paused/remaining
  $adds = [];
  if (!has_col($db,'combate_estado','ronda'))     $adds[]="ADD ronda INT NOT NULL DEFAULT 1";
  if (!has_col($db,'combate_estado','running'))   $adds[]="ADD running TINYINT(1) NOT NULL DEFAULT 0";
  if (!has_col($db,'combate_estado','paused'))    $adds[]="ADD paused  TINYINT(1) NOT NULL DEFAULT 1";
  if (!has_col($db,'combate_estado','duracion'))  $adds[]="ADD duracion INT NOT NULL DEFAULT 180";
  if (!has_col($db,'combate_estado','descanso'))  $adds[]="ADD descanso INT NOT NULL DEFAULT 60";
  if (!has_col($db,'combate_estado','remaining')) $adds[]="ADD remaining INT NOT NULL DEFAULT 180";
  if ($adds) { $db->query("ALTER TABLE combate_estado ".implode(', ',$adds)); }

  // Esquema ANTERIOR (compatibilidad con overlay por epoch)
  $adds2 = [];
  if (!has_col($db,'combate_estado','ronda_actual'))  $adds2[]="ADD ronda_actual INT DEFAULT 1";
  if (!has_col($db,'combate_estado','en_descanso'))   $adds2[]="ADD en_descanso TINYINT(1) NOT NULL DEFAULT 0";
  if (!has_col($db,'combate_estado','epoch_inicio'))  $adds2[]="ADD epoch_inicio INT DEFAULT NULL";
  if (!has_col($db,'combate_estado','dur_round'))     $adds2[]="ADD dur_round INT NOT NULL DEFAULT 180";
  if (!has_col($db,'combate_estado','dur_descanso'))  $adds2[]="ADD dur_descanso INT NOT NULL DEFAULT 60";
  if ($adds2) { $db->query("ALTER TABLE combate_estado ".implode(', ',$adds2)); }

  // Índice útil
  $idx = $db->query("SHOW INDEX FROM combate_estado WHERE Key_name='idx_evento_activo'");
  if (!$idx || $idx->num_rows===0) { $db->query("ALTER TABLE combate_estado ADD INDEX idx_evento_activo (evento_id, activo)"); }
  if ($idx) $idx->close();
}
ensure_combate_estado($conexion);

/* ===== Ruta de resultados (PLURAL) ===== */
$RESULTADOS_RUTA = 'resultados_combates.php';

/* ===== Sonidos ===== */
$WEB_SND_BASE  = '/multi_gimnasio/assets/sounds/'; // ajustá si tu raíz es otra
$DOC_ROOT      = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$LOCAL_SND_DIR = $DOC_ROOT . $WEB_SND_BASE;
function pickSoundFile(string $localDir, string $webBase, array $candidates): string {
  foreach ($candidates as $f) { if (@is_file($localDir.$f)) return $webBase.$f; }
  return $webBase.$candidates[0];
}
$SND_START    = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, ['campana_inicio.mp3','ring_start_bell.mp3','inicio_round.mp3','start.mp3']);
$SND_WARN10   = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, ['segundos_afuera_10s.mp3','segundos_afuera.mp3','10s.mp3','aviso10.mp3']);
$SND_ROUNDEND = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, ['fin_round.mp3','ring_end_bell.mp3','gong_fin.mp3']);
$SND_RESTEND  = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, ['fin_descanso.mp3','inicio_round.mp3','ring_start_bell.mp3']);
$SND_FIGHTEND = pickSoundFile($LOCAL_SND_DIR, $WEB_SND_BASE, ['fin_pelea.mp3','fight_end.mp3','ring_end_bell.mp3']);

/* ====================================================
   ENDPOINTS AJAX
   ==================================================== */

/* a) Estado completo para overlay / vistas externas */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'estado') {
  ini_set('display_errors', '0');
  json_clean_headers();
  $pelea_id = get_int_qs($_GET, 'pelea_id', 0);
  if ($pelea_id <= 0) { echo json_encode(['ok'=>false,'error'=>'pelea_id_invalido']); exit; }

  $data = [
    'pelea_id'=>$pelea_id,
    'evento_id'=>null,
    'ganador_color'=>null,
    'estado'=>null,
    'resultado'=>null,
    'azul'=>['id'=>null,'nombre'=>null,'escuela'=>null,'logo'=>null],
    'rojo'=>['id'=>null,'nombre'=>null,'escuela'=>null,'logo'=>null],
    'timer'=>[
      // compat: ambos esquemas
      'activo'=>0, 'ronda'=>1, 'ronda_actual'=>1,
      'running'=>0, 'paused'=>1,
      'en_descanso'=>0, 'remaining'=>180,
      'epoch_inicio'=>null, 'duracion'=>180, 'dur_round'=>180,
      'descanso'=>60, 'dur_descanso'=>60
    ]
  ];

  if (table_exists($conexion,'peleas_evento')) {
    // columnas básicas
    $cols = ['id','evento_id'];
    $C_GAN = null;
    foreach (['ganador_color','ganador'] as $c){ if (has_col($conexion,'peleas_evento',$c)){ $cols[]=$c; $C_GAN=$c; break; } }
    foreach (['estado','resultado'] as $c){ if (has_col($conexion,'peleas_evento',$c)) $cols[]=$c; }
    $C_AZ = (has_col($conexion,'peleas_evento','competidor_azul_id')?'competidor_azul_id':(has_col($conexion,'peleas_evento','azul_id')?'azul_id':null));
    $C_RO = (has_col($conexion,'peleas_evento','competidor_rojo_id')?'competidor_rojo_id':(has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null));
    if ($C_AZ) $cols[]=$C_AZ; if ($C_RO) $cols[]=$C_RO;

    $sql = "SELECT ".implode(',',array_map('bt',$cols))." FROM peleas_evento WHERE id=? LIMIT 1";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('i',$pelea_id);
      $st->execute(); $r=$st->get_result(); $row=$r->fetch_assoc() ?: [];
      $st->close();
      if ($row){
        $data['evento_id'] = (int)($row['evento_id'] ?? 0) ?: null;
        $data['ganador_color'] = isset($row[$C_GAN??'ganador_color']) ? (string)$row[$C_GAN??'ganador_color'] : null;
        $data['estado'] = isset($row['estado']) ? (string)$row['estado'] : null;
        $data['resultado'] = isset($row['resultado']) ? (string)$row['resultado'] : null;
        $az_id = !empty($row[$C_AZ??'']) ? (int)$row[$C_AZ] : null;
        $ro_id = !empty($row[$C_RO??'']) ? (int)$row[$C_RO] : null;

        // timer en combate_estado (por evento)
        if (!empty($data['evento_id'])) {
          ensure_combate_estado($conexion);
          $st2=$conexion->prepare("SELECT activo, pelea_actual_id,
                                          ronda, running, paused, duracion, descanso, remaining,
                                          ronda_actual, en_descanso, epoch_inicio, dur_round, dur_descanso
                                   FROM combate_estado WHERE evento_id=? LIMIT 1");
          if ($st2){
            $st2->bind_param('i', $data['evento_id']);
            $st2->execute();
            $st2->bind_result($act,$pid,$r,$run,$pau,$dur,$des,$rem,$rA,$enD,$epoch,$dR,$dD);
            if ($st2->fetch()){
              $data['timer']['activo'] = (int)$act;
              $data['timer']['ronda'] = (int)($r ?: 1);
              $data['timer']['running']  = (int)($run ?: 0);
              $data['timer']['paused']   = (int)($pau ?: 1);
              $data['timer']['duracion'] = (int)($dur ?: 180);
              $data['timer']['descanso'] = (int)($des ?: 60);
              $data['timer']['remaining']= (int)($rem ?: 180);
              // compat
              $data['timer']['ronda_actual'] = (int)($rA ?: $data['timer']['ronda']);
              $data['timer']['en_descanso']  = (int)($enD ?: 0);
              $data['timer']['epoch_inicio'] = $epoch ? (int)$epoch : null;
              $data['timer']['dur_round']    = (int)($dR ?: $data['timer']['duracion']);
              $data['timer']['dur_descanso'] = (int)($dD ?: $data['timer']['descanso']);
            }
            $st2->close();
          }
        }

        // nombres/escuelas/logos
        if ($az_id || $ro_id){
          if (table_exists($conexion,'competidores_evento')){
            $C_ESC = has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre'
                    : (has_col($conexion,'competidores_evento','escuela') ? 'escuela'
                    : (has_col($conexion,'competidores_evento','gimnasio') ? 'gimnasio' : null));
            $LOGO_CANDS = ['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela'];
            $C_LOGO=null; foreach($LOGO_CANDS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }

            $colsC = "id, TRIM(CONCAT(COALESCE(apellido,''),' ',COALESCE(nombre,''))) AS nom";
            $colsC .= $C_ESC ? (", ".bt($C_ESC)." AS esc") : ", NULL AS esc";
            $colsC .= $C_LOGO? (", ".bt($C_LOGO)." AS logo") : ", NULL AS logo";

            $ids=[]; if ($az_id) $ids[]=$az_id; if ($ro_id) $ids[]=$ro_id;
            $ph=implode(',', array_fill(0,count($ids),'?')); $typ=str_repeat('i', count($ids));
            $sqlC = "SELECT $colsC FROM competidores_evento WHERE id IN ($ph)";
            if ($st3=$conexion->prepare($sqlC)){
              $st3->bind_param($typ, ...$ids);
              $st3->execute(); $st3->bind_result($cid,$nom,$esc,$logo);
              while($st3->fetch()){
                $arr = ['id'=>(int)$cid,'nombre'=>$nom?:null,'escuela'=>$esc?:null,'logo'=>$logo?:null];
                if ($az_id && (int)$cid===$az_id) $data['azul']=$arr;
                if ($ro_id && (int)$cid===$ro_id) $data['rojo']=$arr;
              }
              $st3->close();
            }
          }
        }
      }
    }
  }

  echo json_encode(['ok'=>true,'data'=>$data,'timestamp'=>time()], JSON_UNESCAPED_UNICODE);
  exit;
}

/* b) Persistir estado (cronómetro/pelea) directamente en este archivo */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'set_estado') {
  ini_set('display_errors','0');
  json_clean_headers();

  $evento_id = get_int_qs($_POST, 'evento_id', 0) ?? 0;
  $pelea_id  = get_int_qs($_POST, 'pelea_id', 0) ?? 0;
  $ronda     = get_int_qs($_POST, 'ronda', 1) ?? 1;
  $running   = get_int_qs($_POST, 'running', 0) ?? 0;
  $paused    = get_int_qs($_POST, 'paused', 1) ?? 1;
  $duracion  = get_int_qs($_POST, 'duracion', 180) ?? 180;
  $descanso  = get_int_qs($_POST, 'descanso', 60) ?? 60;
  $remaining = get_int_qs($_POST, 'remaining', $duracion) ?? $duracion;
  $activo    = get_int_qs($_POST, 'activo', 1) ?? 1;

  // Compat opcional (si el front manda epoch/en_descanso)
  $en_descanso  = get_int_qs($_POST, 'en_descanso', 0) ?? 0;
  $epoch_inicio = get_int_qs($_POST, 'epoch_inicio', null);

  if ($evento_id<=0 || $pelea_id<=0){ echo json_encode(['ok'=>false,'error'=>'evento_id/pelea_id requeridos']); exit; }

  ensure_combate_estado($conexion);

  // UPSERT por evento_id
  $sql = "INSERT INTO combate_estado
            (evento_id, pelea_actual_id, activo, ronda, running, paused, duracion, descanso, remaining,
             ronda_actual, en_descanso, epoch_inicio, dur_round, dur_descanso, actualizado_en)
          VALUES (?,?,?,?,?,?,?,?,?, ?,?,?,?, ?, CURRENT_TIMESTAMP)
          ON DUPLICATE KEY UPDATE
            pelea_actual_id=VALUES(pelea_actual_id),
            activo=VALUES(activo),
            ronda=VALUES(ronda),
            running=VALUES(running),
            paused=VALUES(paused),
            duracion=VALUES(duracion),
            descanso=VALUES(descanso),
            remaining=VALUES(remaining),
            ronda_actual=VALUES(ronda_actual),
            en_descanso=VALUES(en_descanso),
            epoch_inicio=VALUES(epoch_inicio),
            dur_round=VALUES(dur_round),
            dur_descanso=VALUES(dur_descanso),
            actualizado_en=CURRENT_TIMESTAMP";

  $st=$conexion->prepare($sql);
  $st->bind_param(
    'iiiiiiiii iiiii',
    $evento_id,$pelea_id,$activo,$ronda,$running,$paused,$duracion,$descanso,$remaining,
    $ronda,$en_descanso,$epoch_inicio,$duracion,$descanso
  );
  $ok=$st->execute(); $st->close();

  echo json_encode(['ok'=>$ok?true:false]); exit;
}

/* c) Marcar/pausar pelea actual (combate_estado) */
if (isset($_GET['ajax']) && in_array($_GET['ajax'], ['marcar_actual','pausar_actual'], true)) {
  ini_set('display_errors', '0');
  json_clean_headers();
  $pelea_id = get_int_qs($_POST, 'pelea_id', 0) ?? 0;
  if ($pelea_id <= 0) { echo json_encode(['ok'=>false,'error'=>'pelea_id_invalido']); exit; }

  // Buscar evento_id
  $evento_id_fin = null;
  if (table_exists($conexion,'peleas_evento') && has_col($conexion,'peleas_evento','evento_id')) {
    if ($st=$conexion->prepare("SELECT evento_id FROM peleas_evento WHERE id=? LIMIT 1")){
      $st->bind_param('i',$pelea_id); $st->execute(); $st->bind_result($evento_id_fin); $st->fetch(); $st->close();
    }
  }
  if (empty($evento_id_fin)) { echo json_encode(['ok'=>false,'error'=>'sin_evento_id']); exit; }

  ensure_combate_estado($conexion);

  if ($_GET['ajax']==='marcar_actual'){
    $sql = "INSERT INTO combate_estado (evento_id, pelea_actual_id, activo, running, paused)
            VALUES (?, ?, 1, 0, 1)
            ON DUPLICATE KEY UPDATE pelea_actual_id=VALUES(pelea_actual_id), activo=1, actualizado_en=CURRENT_TIMESTAMP";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param('ii',$evento_id_fin,$pelea_id); $st->execute(); $st->close();
      echo json_encode(['ok'=>true,'evento_id'=>$evento_id_fin,'pelea_actual_id'=>$pelea_id,'activo'=>1]); exit;
    }
  } else { // pausar_actual
    if ($st=$conexion->prepare("UPDATE combate_estado SET activo=0, running=0, paused=1, actualizado_en=CURRENT_TIMESTAMP WHERE evento_id=? LIMIT 1")){
      $st->bind_param('i',$evento_id_fin); $st->execute(); $st->close();
      echo json_encode(['ok'=>true,'evento_id'=>$evento_id_fin,'activo'=>0]); exit;
    }
  }
  echo json_encode(['ok'=>false,'error'=>'db']); exit;
}

/* d) FINALIZAR (mesa/manual) */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'finalizar') {
  ini_set('display_errors', '0');
  json_clean_headers();

  $pelea_id     = get_int_qs($_POST, 'pelea_id', 0) ?? 0;
  if ($pelea_id <= 0) { echo json_encode(['ok'=>false,'error'=>'pelea_id_invalido']); exit; }
  $_SESSION['pelea_id_actual'] = (int)$pelea_id;

  $manual_mode  = (isset($_POST['manual_mode']) && $_POST['manual_mode'] === '1');
  $manual_scores = [];
  if (!empty($_POST['manual_scores_json'])) {
    $tmp = json_decode((string)$_POST['manual_scores_json'], true);
    if (is_array($tmp)) $manual_scores = $tmp; // [{round:1, azul:10, rojo:9}, ...]
  }
  $manual_win  = strtolower(trim((string)($_POST['manual_win']  ?? ''))); // azul|rojo|empate|''
  $manual_tipo = strtoupper(trim((string)($_POST['manual_tipo'] ?? ''))); // KO|TKO|SUM|PTS|DQ|NC|''
  $manual_rf   = get_int_qs($_POST, 'manual_round_fin', 0) ?? 0;
  $manual_time = trim((string)($_POST['manual_time'] ?? '')); // mm:ss

  if (!$manual_mode || empty($manual_scores)) {
    echo json_encode(['ok'=>false,'error'=>'sin_datos','msg'=>'No hay rounds cargados. Marcá el ganador de cada round.'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Sumar puntos
  $sumA = 0; $sumR = 0;
  foreach ($manual_scores as $r) { $sumA += (int)($r['azul'] ?? 0); $sumR += (int)($r['rojo'] ?? 0); }

  // Resolver ganador
  $gan=''; $metodo=''; $detalle='';
  if (in_array($manual_win, ['azul','rojo','empate'], true)) {
    $gan    = $manual_win;
    $metodo = $manual_tipo ?: 'PTS';
    $detalle= 'por ' . $metodo . ($manual_rf ? (' en R' . (int)$manual_rf) : '');
  }
  if ($gan === '') {
    if     ($sumA > $sumR) { $gan='azul';  $metodo='PTS'; $detalle='por puntos (mesa)'; }
    elseif ($sumR > $sumA) { $gan='rojo';  $metodo='PTS'; $detalle='por puntos (mesa)'; }
    else                   { $gan='empate';$metodo='DRAW';$detalle='empate en puntos (mesa)'; }
  }

  // Traer IDs/col
  $C_EVENTO    = has_col($conexion,'peleas_evento','evento_id') ? 'evento_id' : null;
  $C_GAN_COLOR = has_col($conexion,'peleas_evento','ganador_color') ? 'ganador_color' : (has_col($conexion,'peleas_evento','ganador')?'ganador':null);
  $C_ESTADO    = has_col($conexion,'peleas_evento','estado') ? 'estado' : null;
  $C_DETALLE   = has_col($conexion,'peleas_evento','detalle_resultado') ? 'detalle_resultado' : (has_col($conexion,'peleas_evento','resolucion')?'resolucion':null);
  $C_AZUL_ID   = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_ROJO_ID   = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_GAN_ID    = has_col($conexion,'peleas_evento','ganador_id') ? 'ganador_id' : null;

  $azul_id = $rojo_id = $evento_id_fin = null;

  $parts = [];
  $parts[] = $C_EVENTO ? bt($C_EVENTO).' AS ev' : 'NULL AS ev';
  $parts[] = $C_AZUL_ID ? bt($C_AZUL_ID).' AS az' : 'NULL AS az';
  $parts[] = $C_ROJO_ID ? bt($C_ROJO_ID).' AS ro' : 'NULL AS ro';
  if ($stmt = $conexion->prepare("SELECT ".implode(', ',$parts)." FROM peleas_evento WHERE id=? LIMIT 1")) {
    $stmt->bind_param('i',$pelea_id);
    $stmt->execute();
    $stmt->bind_result($evento_id_fin,$azul_id,$rojo_id);
    $stmt->fetch(); $stmt->close();
  }

  // estado / ganador / detalle
  if ($C_ESTADO && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_ESTADO)."='finalizada' WHERE id=? LIMIT 1"))){
    $st->bind_param('i',$pelea_id); $st->execute(); $st->close();
  }
  if ($C_GAN_COLOR && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_COLOR)."=? WHERE id=? LIMIT 1"))){
    $val = ($gan==='empate'?'empate':$gan);
    $st->bind_param('si',$val,$pelea_id); $st->execute(); $st->close();
  }
  if ($C_GAN_ID){
    if ($gan==='azul' && $azul_id) {
      if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=? WHERE id=? LIMIT 1")){
        $st->bind_param('ii', $azul_id, $pelea_id); $st->execute(); $st->close();
      }
    } elseif ($gan==='rojo' && $rojo_id) {
      if ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=? WHERE id=? LIMIT 1")){
        $st->bind_param('ii', $rojo_id, $pelea_id); $st->execute(); $st->close();
      }
    } else {
      $conexion->query("UPDATE peleas_evento SET ".bt($C_GAN_ID)."=NULL WHERE id=".(int)$pelea_id." LIMIT 1");
    }
  }
  if ($C_DETALLE && ($st=$conexion->prepare("UPDATE peleas_evento SET ".bt($C_DETALLE)."=? WHERE id=? LIMIT 1"))){
    $txt = strtoupper($metodo).' — '.$detalle.' · Mesa: AZ '.$sumA.' / RO '.$sumR;
    $st->bind_param('si',$txt,$pelea_id); $st->execute(); $st->close();
  }

  // Pausar transmisión si está activa
  if (!empty($evento_id_fin)) {
    ensure_combate_estado($conexion);
    if ($st=$conexion->prepare("UPDATE combate_estado SET activo=0, running=0, paused=1, actualizado_en=CURRENT_TIMESTAMP WHERE evento_id=? LIMIT 1")){
      $st->bind_param('i',$evento_id_fin); $st->execute(); $st->close();
    }
  }

  $redir = $RESULTADOS_RUTA.'?pelea_id='.$pelea_id;
  if (!is_null($evento_id_fin) && (int)$evento_id_fin>0) { $redir .= '&evento_id='.(int)$evento_id_fin; }

  echo json_encode(['ok'=>true,'ganador'=>$gan,'metodo'=>$metodo,'detalle'=>$detalle,'redirect'=>$redir], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ====================================================
   VISTA HTML
   ==================================================== */

// Intentar tomar pelea_id del QS; si no viene, usar el de sesión
$pelea_id = get_int_qs($_GET, 'pelea_id', 0) ?? 0;
if ($pelea_id <= 0 && !empty($_SESSION['pelea_id_actual'])) $pelea_id = (int)$_SESSION['pelea_id_actual'];
if ($pelea_id > 0) $_SESSION['pelea_id_actual'] = (int)$pelea_id;
if ($pelea_id <= 0) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>'; exit; }

/* QS (solo vista) */
$nroQS    = get_int_qs($_GET,'nro',null);
$rondasQS = get_int_qs($_GET,'rondas',null);
$durQS    = get_int_qs($_GET,'dur',null);
$restQS   = get_int_qs($_GET,'rest',null);
$autoQS   = isset($_GET['start']) && (string)$_GET['start']==='1';

$timerDur  = ($durQS  && $durQS  > 0) ? $durQS  : 120;
$timerRest = ($restQS && $restQS > 0) ? $restQS : 60;

/* Datos de pelea + competidores */
$evento_id = null; $rondasEsperadas = 3; $pelea_numero = null;

$azul_nom='Azul'; $rojo_nom='Rojo';
$azul_logo=''; $rojo_logo='';
$azul_escuela=''; $rojo_escuela='';
$azul_div=''; $rojo_div='';
$azul_peso=''; $rojo_peso='';
$azul_mod='';  $rojo_mod='';

if (table_exists($conexion,'peleas_evento')) {
  $C_EVENTO  = has_col($conexion,'peleas_evento','evento_id') ? 'evento_id' : null;
  $C_AZUL_ID = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_ROJO_ID = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_DUR  = null; foreach (['duracion_round','duracion','round_duracion','tiempo_round'] as $cand) { if (has_col($conexion,'peleas_evento',$cand)) { $C_DUR=$cand; break; } }
  $C_DESC = null; foreach (['descanso','tiempo_descanso','descanso_seg'] as $cand) { if (has_col($conexion,'peleas_evento',$cand)) { $C_DESC=$cand; break; } }
  $C_RONDAS = null; foreach(['rondas','rounds','total_rounds','rondas_total','rondas_totales','cantidad_rondas','cant_rondas','n_rondas','numero_rondas','rounds_total','rounds_totales','rondas_pelea','rondas_conf','rondas_configuradas'] as $cand){ if (has_col($conexion,'peleas_evento',$cand)){ $C_RONDAS=$cand; break; } }
  $C_NUMERO = null; foreach(['numero','nro','orden','n_orden','num'] as $cand){ if(has_col($conexion,'peleas_evento',$cand)){ $C_NUMERO=$cand; break; } }

  $sel=[]; $sel[] = $C_EVENTO ? bt($C_EVENTO).' AS ev' : 'NULL AS ev';
  $sel[] = $C_RONDAS ? bt($C_RONDAS).' AS rds' : 'NULL AS rds';
  $sel[] = $C_NUMERO ? bt($C_NUMERO).' AS pnum' : 'NULL AS pnum';
  $sel[] = $C_DUR  ? bt($C_DUR).'  AS dur'  : 'NULL AS dur';
  $sel[] = $C_DESC ? bt($C_DESC).' AS dsc'  : 'NULL AS dsc';
  if ($C_AZUL_ID) $sel[] = bt($C_AZUL_ID).' AS az';
  if ($C_ROJO_ID) $sel[] = bt($C_ROJO_ID).' AS ro';

  $sqlSel = "SELECT ".implode(', ', $sel)." FROM peleas_evento WHERE id=? LIMIT 1";
  if ($st=$conexion->prepare($sqlSel)){
    $st->bind_param('i',$pelea_id);
    $st->execute(); $st->store_result();
    $ev=$rds=$pnum=$dur=$dsc=null; $az_id=$ro_id=null;
    if ($C_AZUL_ID && $C_ROJO_ID) { $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$az_id,$ro_id);
    } elseif ($C_AZUL_ID)         { $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$az_id);
    } elseif ($C_ROJO_ID)         { $st->bind_result($ev,$rds,$pnum,$dur,$dsc,$ro_id);
    } else                        { $st->bind_result($ev,$rds,$pnum,$dur,$dsc); }
    if($st->fetch()){
      $evento_id = is_null($ev)?null:(int)$ev;
      $rds = (int)$rds; if ($rds>0) $rondasEsperadas = $rds;
      if (!is_null($pnum) && $pnum!=='') $pelea_numero = (int)$pnum;
      if (isset($dur) && (int)$dur > 0 && !($durQS  && $durQS  > 0)) $timerDur  = (int)$dur;
      if (isset($dsc) && (int)$dsc > 0 && !($restQS && $restQS > 0)) $timerRest = (int)$dsc;
    }
    $st->close();
  }

  // Datos extendidos de competidores_evento
  $ids = [];
  if (!empty($az_id) && (int)$az_id>0) $ids[] = (int)$az_id;
  if (!empty($ro_id) && (int)$ro_id>0) $ids[] = (int)$ro_id;

  if (table_exists($conexion,'competidores_evento') && $ids) {
    $C_ESCUELA = has_col($conexion,'competidores_evento','escuela') ? 'escuela'
                  : (has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre'
                  : (has_col($conexion,'competidores_evento','gimnasio') ? 'gimnasio' : null));
    $LOGO_CANDS = ['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela'];
    $C_LOGO = null; foreach($LOGO_CANDS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }

    $haveDV  = table_exists($conexion,'divisiones_evento');
    $haveCP  = table_exists($conexion,'categorias_peso_evento');
    $haveMD  = table_exists($conexion,'modalidades_evento');

    $C_DIV_ID  = has_col($conexion,'competidores_evento','division_id') ? 'division_id' : (has_col($conexion,'competidores_evento','id_division')?'id_division':null);
    $C_DIV_TXT = has_col($conexion,'competidores_evento','division') ? 'division' : null;

    $C_PESO_ID  = has_col($conexion,'competidores_evento','categoria_peso_id') ? 'categoria_peso_id' : (has_col($conexion,'competidores_evento','id_categoria_peso')?'id_categoria_peso':null);
    $C_PESO_TXT = has_col($conexion,'competidores_evento','peso_kg') ? 'peso_kg' : (has_col($conexion,'competidores_evento','peso') ? 'peso' : (has_col($conexion,'competidores_evento','categoria_peso')?'categoria_peso':null));

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

    $ph  = implode(',', array_fill(0,count($ids),'?'));
    $typ = str_repeat('i', count($ids));
    $sql = "SELECT $cols FROM competidores_evento ce $joins WHERE ce.id IN ($ph)";
    if ($st=$conexion->prepare($sql)){
      $st->bind_param($typ, ...$ids);
      $st->execute(); $st->store_result();
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
$__s_init = str_pad((string)$__t_init%60, 2, '0', STR_PAD_LEFT);

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🥊 Combate en vivo — <?php if (!is_null($pelea_numero)) { echo 'Pelea N° '.(int)$pelea_numero.' (ID '.(int)$pelea_id.')'; } else { echo 'Pelea #'.(int)$pelea_id; } ?></title>
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
  .btn-primary{background:#00b894;color:#fff}.btn-warn{background:#ffb300}.btn-danger{background:#e53935}.btn-gray{background:#2a2f35;color:#fff}
  .btn-ghost{background:transparent;border:1px solid #334250}
  .timer{font-size:110px;font-weight:900;line-height:1;letter-spacing:1px}
  .round{font-size:18px;font-weight:700;margin-bottom:6px}
  .badge{padding:6px 10px;border-radius:999px;background:#121a24;border:1px solid #2a3a4a;margin-right:6px}
  .muted{color:var(--muted)}
  .switch{display:inline-flex;align-items:center;gap:8px}
  .tbl-rounds{width:100%;border-collapse:collapse;margin-top:8px}
  .tbl-rounds th,.tbl-rounds td{border:1px solid #26313a;padding:6px;text-align:center}
  .chip{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border-radius:10px;border:1px solid #2a3a4a;background:#141a20;color:#e5eef7;font-weight:700;cursor:pointer;user-select:none}
  .chip[data-v="azul"]{border-color:#0b5aa6}
  .chip[data-v="rojo"]{border-color:#a60b1f}
  .chip[data-v="empate"]{border-color:#606b7a}
  .chip.active{outline:2px solid #fff2; box-shadow:0 0 0 2px #fff2 inset}
  .chip:hover{filter:brightness(1.1)}
  .r-extra{background:#151b22}
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
  <div class="sub" style="margin-bottom:10px">
    <?= $evento_id!==null ? ('Evento #'.(int)$evento_id) : '(sin evento_id)' ?>
    · Rondas configuradas: <b id="lblRondas"><?= (int)$rondasEsperadas ?></b>
    · Rondas en juego: <b id="lblJugadas"><?= (int)$rondasEsperadas ?></b>
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

      <h4 style="margin:14px 0 4px">📝 Ganador por round (rápido)</h4>
      <div class="sub" style="margin-bottom:6px">Tocá el ganador de cada round. Si al finalizar hay empate global, se agrega un round extra automáticamente.</div>

      <table class="tbl-rounds" id="tblRounds">
        <thead><tr><th style="width:90px">Round</th><th>Ganador</th><th style="width:180px">Marcado</th></tr></thead>
        <tbody id="tbRoundRows"></tbody>
        <tfoot>
          <tr>
            <th>Σ</th>
            <th style="text-align:center">
              <span class="badge">Rounds Azul: <b id="rAz">0</b></span>
              <span class="badge">Rounds Rojo: <b id="rRo">0</b></span>
              <span class="badge">Empates: <b id="rEq">0</b></span>
            </th>
            <th><span class="badge">Sugerido: <b id="bSug">—</b></span></th>
          </tr>
        </tfoot>
      </table>

      <div class="sub" style="margin:10px 0 4px">Fallo manual (opcional)</div>
      <div class="controls">
        <select id="selWin" class="btn btn-gray">
          <option value="">— Ganador —</option>
          <option value="azul">🔵 Azul</option>
          <option value="rojo">🔴 Rojo</option>
          <option value="empate">⚖️ Empate</option>
        </select>
        <select id="selTipo" class="btn btn-gray">
          <option value="">— Tipo —</option>
          <option>KO</option><option>TKO</option><option>SUM</option>
          <option>PTS</option><option>DQ</option><option>NC</option>
        </select>
        <input id="inRoundFin" class="btn btn-gray" type="number" min="0" step="1" placeholder="Round fin">
        <input id="inTime" class="btn btn-gray" type="text" placeholder="Tiempo mm:ss">
      </div>

      <div class="controls">
        <a class="btn btn-gray" href="<?= h($return_to) ?>">↩️ Volver</a>
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
  /* ====== Timer ====== */
  let duration=<?= (int)$timerDur ?>, rest=<?= (int)$timerRest ?>, remain=<?= (int)$timerDur ?>, round=1, t=null, inRest=false;
  const timer=document.getElementById('timer'), rnd=document.getElementById('round');
  const selDur=document.getElementById('selDur'), selRest=document.getElementById('selRest');
  const btnStart=document.getElementById('btnStart'), btnPause=document.getElementById('btnPause'),
        btnReset=document.getElementById('btnReset'), btnNext=document.getElementById('btnNext');

  // ======= PUBLICACIÓN DE TIMER AL VIVO (sin API externo) =======
  const eventoId = <?= $evento_id ? (int)$evento_id : 0 ?>; // si no hay evento_id, no publica
  const peleaId  = <?= (int)$pelea_id ?>;

  let lastPublish = 0;

  async function publishTimer(force=false){
    if (!eventoId) return;
    const now = Math.floor(Date.now()/1000);
    if (!force && (now - lastPublish) < 1) return; // 1s máx
    lastPublish = now;

    // epoch_inicio (compat) = inicio de la fase actual (round/descanso)
    const elapsed = (inRest ? (rest - remain) : (duration - remain));
    const epoch_inicio = Math.max(0, Math.floor(Date.now()/1000) - Math.max(0, elapsed));

    const fd = new FormData();
    fd.append('evento_id', String(eventoId));
    fd.append('pelea_id',  String(peleaId));
    fd.append('activo',    '1');

    // Esquema B
    fd.append('ronda',     String(round));
    fd.append('running',   t ? '1' : '0');
    fd.append('paused',    t ? '0' : '1');
    fd.append('duracion',  String(duration));
    fd.append('descanso',  String(rest));
    fd.append('remaining', String(Math.max(0,remain)));

    // Compat epoch
    fd.append('ronda_actual', String(round));
    fd.append('en_descanso',  inRest ? '1' : '0');
    fd.append('epoch_inicio', String(epoch_inicio));
    fd.append('dur_round',    String(duration));
    fd.append('dur_descanso', String(rest));

    try{ await fetch('combate_en_vivo.php?ajax=set_estado', {method:'POST', body:fd, cache:'no-store'}); }catch(e){}
  }

  async function marcarActual(){
    if (!eventoId) return;
    const fd = new FormData(); fd.append('pelea_id', String(peleaId));
    try{ await fetch('combate_en_vivo.php?ajax=marcar_actual', {method:'POST', body:fd, cache:'no-store'}); }catch(e){}
  }
  async function pausarActual(){
    if (!eventoId) return;
    const fd = new FormData(); fd.append('pelea_id', String(peleaId));
    try{ await fetch('combate_en_vivo.php?ajax=pausar_actual', {method:'POST', body:fd, cache:'no-store'}); }catch(e){}
  }

  function clamp(n,min,max){n=parseInt(n,10);if(isNaN(n))return min;return Math.max(min,Math.min(max,n));}
  function fmt(s){const m=Math.floor(s/60), ss=String(s%60).padStart(2,'0'); return `${m}:${ss}`;}
  function paint(){ timer.textContent=fmt(remain); rnd.textContent=round; }
  function tick(){
    if(remain>0){
      remain--;
      if(remain===10){ try{document.getElementById('snd-10').play().catch(()=>{});}catch(e){} }
      paint();
      publishTimer(false);
      return;
    }
    if(!inRest){
      try{document.getElementById('snd-roundend').play().catch(()=>{});}catch(e){}
      inRest=true; remain=rest;
    } else {
      try{document.getElementById('snd-restend').play().catch(()=>{});}catch(e){}
      inRest=false; round++; remain=duration;
    }
    paint();
    publishTimer(true); // fase nueva => forzar
  }
  btnStart.onclick=async ()=>{
    if(!t){
      t=setInterval(tick,1000);
      try{document.getElementById('snd-start').play().catch(()=>{});}catch(e){}
      await marcarActual();
      publishTimer(true);
    }
  };
  btnPause.onclick=async ()=>{
    if(t){clearInterval(t); t=null;}
    await pausarActual();
    publishTimer(true);
  };
  btnReset.onclick=async ()=>{
    if(t){clearInterval(t); t=null;}
    inRest=false; round=1; remain=duration; paint();
    await pausarActual();
    publishTimer(true);
  };
  btnNext.onclick =async ()=>{
    if(t){clearInterval(t); t=null;}
    inRest=false; round++; remain=duration; paint();
    await marcarActual();
    publishTimer(true);
  };

  selDur.onchange =()=>{ duration=clamp(selDur.value,30,900); selDur.value=duration; if(!inRest&&remain>duration) remain=duration; paint(); publishTimer(true); };
  selRest.onchange=()=>{ rest=clamp(selRest.value,10,600); selRest.value=rest; if(inRest&&remain>rest) remain=rest; paint(); publishTimer(true); };

  /* ====== Rondas con botones ====== */
  const MAX_ROUNDS = 12; // límite de seguridad
  let rondasConfig = parseInt(document.getElementById('lblRondas').textContent, 10);
  if (!Number.isFinite(rondasConfig) || rondasConfig < 1) rondasConfig = 3;
  let rondasEnJuego = rondasConfig;

  const tblBody = document.getElementById('tbRoundRows');
  const rAz = document.getElementById('rAz'), rRo = document.getElementById('rRo'), rEq = document.getElementById('rEq');
  const bSug = document.getElementById('bSug');
  const lblJugadas = document.getElementById('lblJugadas');

  // roundResults: { r:1..n, v:'azul'|'rojo'|'empate'|null }
  let roundResults = [];

  function renderRows(){
    let html = '';
    for(let r=1; r<=rondasEnJuego; r++){
      const val = (roundResults[r-1]?.v) || null;
      const isExtra = r > rondasConfig;
      html += `<tr ${isExtra?'class="r-extra"':''}>
        <td>R${r}${isExtra?' <small>(extra)</small>':''}</td>
        <td>
          <div class="controls" style="justify-content:center">
            <div class="chip ${val==='azul'?'active':''}" data-r="${r}" data-v="azul">🔵 Azul</div>
            <div class="chip ${val==='rojo'?'active':''}" data-r="${r}" data-v="rojo">🔴 Rojo</div>
            <div class="chip ${val==='empate'?'active':''}" data-r="${r}" data-v="empate">⚖️ Empate</div>
          </div>
        </td>
        <td id="mark-${r}" class="sub">${val? (val==='azul'?'🔵 Azul':(val==='rojo'?'🔴 Rojo':'⚖️ Empate')) : '—'}</td>
      </tr>`;
    }
    tblBody.innerHTML = html;
    tblBody.querySelectorAll('.chip').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const r = parseInt(btn.dataset.r,10);
        const v = btn.dataset.v;
        setRound(r, v);
      });
    });
    lblJugadas.textContent = String(rondasEnJuego);
    recalcTotals();
  }

  function setRound(r, v){
    while (roundResults.length < r) roundResults.push({r: roundResults.length+1, v: null});
    roundResults[r-1].v = v;
    // refrescar UI del row r
    const rowChips = tblBody.querySelectorAll(`.chip[data-r="${r}"]`);
    rowChips.forEach(c => c.classList.toggle('active', c.dataset.v===v));
    const mark = document.getElementById(`mark-${r}`);
    if (mark) mark.textContent = (v==='azul'?'🔵 Azul':(v==='rojo'?'🔴 Rojo':'⚖️ Empate'));

    recalcTotals();

    // Si ya están todos los rounds base cargados y hay empate global, agregar uno extra
    if (allBaseFilled() && isGlobalTie() && rondasEnJuego < MAX_ROUNDS){
      rondasEnJuego++;
      renderRows();
    }
  }

  function allBaseFilled(){
    for(let i=0;i<rondasConfig;i++){
      if (!roundResults[i] || !roundResults[i].v) return false;
    }
    return true;
  }

  function isGlobalTie(){
    let az=0, ro=0;
    roundResults.forEach(rr=>{
      if (rr?.v==='azul') az++;
      else if (rr?.v==='rojo') ro++;
    });
    return az===ro;
  }

  function recalcTotals(){
    let az=0, ro=0, eq=0;
    roundResults.forEach(rr=>{
      if (!rr || !rr.v) return;
      if (rr.v==='azul') az++;
      else if (rr.v==='rojo') ro++;
      else eq++;
    });
    rAz.textContent = az; rRo.textContent = ro; rEq.textContent = eq;
    bSug.textContent = (az>ro?'🔵 Azul':(ro>az?'🔴 Rojo':'⚖️ Empate'));
  }

  function collectManualScoresAsPoints(){
    // Convertimos a tarjetas 10-9 / 9-10 / 10-10 para backend compatible
    const out = [];
    for (let r=1; r<=roundResults.length; r++){
      const v = roundResults[r-1]?.v || null;
      if (!v){ // si no está marcado, empate 10-10
        out.push({round:r, azul:10, rojo:10});
      } else if (v==='azul'){
        out.push({round:r, azul:10, rojo:9});
      } else if (v==='rojo'){
        out.push({round:r, azul:9, rojo:10});
      } else { // empate
        out.push({round:r, azul:10, rojo:10});
      }
    }
    return out;
  }

  function autoWinnerFromResults(){
    let az=0, ro=0;
    roundResults.forEach(rr=>{
      if (rr?.v==='azul') az++;
      else if (rr?.v==='rojo') ro++;
    });
    if (az>ro) return 'azul';
    if (ro>az) return 'rojo';
    return 'empate';
  }

  // Init rows
  roundResults = Array.from({length: rondasConfig}, (_,i)=>({r:i+1, v:null}));
  renderRows();

  async function fetchJSON(url, opt={}){
    const ctrl = new AbortController(); const to = setTimeout(()=>ctrl.abort(), opt.timeout||15000);
    try{
      const r = await fetch(url, { cache: 'no-store', signal: ctrl.signal, method: opt.method || 'GET',
        headers: {'Accept':'application/json'}, body: opt.body || null });
      const txt = await r.text(); if (!r.ok) { console.error('HTTP', r.status, txt); return null; }
      try { return JSON.parse(txt); } catch(e){ console.error('JSON parse error:', txt); return null; }
    }catch(e){ console.warn('AJAX error', e); return null; } finally{ clearTimeout(to); }
  }

  const selWin = document.getElementById('selWin');
  const selTipo = document.getElementById('selTipo');
  const inRoundFin = document.getElementById('inRoundFin');
  const inTime = document.getElementById('inTime');
  const banner = document.getElementById('banner');

  document.getElementById('btnFinish').onclick = async ()=>{
    // auto-add extra si está todo cargado y sigue empate
    if (allBaseFilled() && isGlobalTie() && rondasEnJuego < MAX_ROUNDS){
      alert('Empate global: se agregó un round extra. Marcá el ganador del round extra antes de finalizar.');
      rondasEnJuego++;
      renderRows();
      return;
    }

    if(!confirm('¿Finalizar el combate y enviar a resultados?')) return;
    if(t){ clearInterval(t); t=null; } // detener timer local
    try{document.getElementById('snd-fightend').play().catch(()=>{});}catch(e){}

    // Autocompletar ganador si no lo eligieron en el selector manual
    if (!selWin.value){
      const autoGan = autoWinnerFromResults();
      selWin.value = autoGan; // azul/rojo/empate
      if (!selTipo.value) selTipo.value = (autoGan==='empate' ? '' : 'PTS');
    }

    const form = new FormData();
    form.append('pelea_id', String(peleaId));
    form.append('manual_mode', '1');
    form.append('manual_scores_json', JSON.stringify(collectManualScoresAsPoints()));
    form.append('manual_win', selWin.value || '');
    form.append('manual_tipo', selTipo.value || '');
    form.append('manual_round_fin', inRoundFin.value || String(rondasEnJuego));
    form.append('manual_time', inTime.value || '');

    const j = await fetchJSON('combate_en_vivo.php?ajax=finalizar', {method:'POST', body:form, timeout:20000});
    if (!j || !j.ok){
      alert((j && j.msg) ? j.msg : 'No se pudo finalizar. Marcá los rounds.');
      return;
    }
    const lbl = (j.ganador==='azul'?'🔵 AZUL':(j.ganador==='rojo'?'🔴 ROJO':'⚖️ EMPATE'));
    banner.innerHTML = `<b>Resultado:</b> ${lbl} — ${j.metodo} · <span class="muted">${j.detalle||''}</span>`;
    setTimeout(()=>{ window.location.href = j.redirect || '<?= h($RESULTADOS_RUTA) ?>?pelea_id='+peleaId; }, 800);
  };

  // ===== Autostart con QS start=1 =====
  const autoStart = <?= $autoQS ? 'true' : 'false' ?>;
  function paintInit(){ remain=duration; paint(); }
  paintInit();
  if (autoStart){
    setTimeout(()=>{ btnStart.click(); }, 150);
  }

  publishTimer(true);
})();
</script>
</body>
</html>
