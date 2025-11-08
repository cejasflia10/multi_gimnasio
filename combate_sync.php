<?php
/* ==========================================================
   combate_sync.php — Long-poll para OVERLAY (estado único)
   Endpoints:
     - ?ajax=poll&evento_id=...&since=UNIX
       Espera hasta 25s un cambio en combate_estado.actualizado_en.
       Devuelve {ok, changed, version, data:{timer, pelea, azul, rojo}}
   Requiere: combate_en_vivo.php publique con ?ajax=set_estado (ya lo tenés).
   ========================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

ini_set('display_errors','0');
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }

function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r=$db->query("SHOW TABLES LIKE '$name'")){ $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function get_int(array $src, string $k, int $def=0): int {
  if (!isset($src[$k])) return $def; $v=(string)$src[$k];
  return (preg_match('/^-?\d+$/',$v)? (int)$v : $def);
}

function ensure_combate_estado(mysqli $db){
  $db->query("CREATE TABLE IF NOT EXISTS combate_estado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    evento_id INT NOT NULL UNIQUE,
    pelea_actual_id INT DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 0,
    ronda INT NOT NULL DEFAULT 1,
    running TINYINT(1) NOT NULL DEFAULT 0,
    paused  TINYINT(1) NOT NULL DEFAULT 1,
    duracion INT NOT NULL DEFAULT 180,
    descanso INT NOT NULL DEFAULT 60,
    remaining INT NOT NULL DEFAULT 180,
    ronda_actual INT DEFAULT 1,
    en_descanso TINYINT(1) NOT NULL DEFAULT 0,
    epoch_inicio INT DEFAULT NULL,
    dur_round INT NOT NULL DEFAULT 180,
    dur_descanso INT NOT NULL DEFAULT 60,
    actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_evento_activo (evento_id, activo)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
}
ensure_combate_estado($conexion);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

$ajax = $_GET['ajax'] ?? '';
if ($ajax !== 'poll'){ echo json_encode(['ok'=>false,'error'=>'uso: ?ajax=poll']); exit; }

$evento_id = get_int($_GET,'evento_id',0);
$since     = get_int($_GET,'since',0);

if ($evento_id<=0){ echo json_encode(['ok'=>false,'error'=>'evento_id requerido']); exit; }

/* ----- Long-poll loop (hasta 25s) ----- */
$timeout_ms = 25000;
$step_ms    = 500;
$loops      = (int)ceil($timeout_ms/$step_ms);
$version    = 0;
$payload    = null;

for($i=0;$i<$loops;$i++){
  $sql = "SELECT pelea_actual_id, activo, ronda, running, paused, duracion, descanso, remaining,
                 ronda_actual, en_descanso, epoch_inicio, dur_round, dur_descanso,
                 UNIX_TIMESTAMP(actualizado_en) AS ver
          FROM combate_estado WHERE evento_id=? LIMIT 1";
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('i',$evento_id);
    $st->execute(); $st->store_result();
    $st->bind_result($pelea_actual_id,$activo,$r,$run,$pau,$dur,$des,$rem,$rA,$enD,$epoch,$dR,$dD,$ver);
    if ($st->fetch()){
      $version = (int)$ver;
      if ($version > $since){
        // Armar data base (timer + pelea)
        $data = [
          'evento_id' => $evento_id,
          'pelea_actual_id' => (int)$pelea_actual_id,
          'timer' => [
            'activo'=>(int)$activo,
            'ronda'=>(int)($r ?: $rA ?: 1),
            'running'=>(int)$run,
            'paused'=>(int)$pau,
            'duracion'=>(int)$dur,
            'descanso'=>(int)$des,
            'remaining'=>(int)$rem,
            'ronda_actual'=>(int)($rA ?: $r ?: 1),
            'en_descanso'=>(int)$enD,
            'epoch_inicio'=> $epoch ? (int)$epoch : null,
            'dur_round'=>(int)$dR,
            'dur_descanso'=>(int)$dD
          ],
          'azul'=>null,'rojo'=>null,'pelea'=>null
        ];

        // Cargar info de pelea y competidores (si existe)
        if ($pelea_actual_id && table_exists($conexion,'peleas_evento')){
          $C_AZ = has_col($conexion,'peleas_evento','competidor_azul_id')?'competidor_azul_id':(has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
          $C_RO = has_col($conexion,'peleas_evento','competidor_rojo_id')?'competidor_rojo_id':(has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
          $C_R  = null; foreach(['rondas','rounds','cantidad_rondas','rondas_total'] as $c){ if (has_col($conexion,'peleas_evento',$c)){ $C_R=$c; break; } }
          $C_NUM= null; foreach(['numero','nro','orden','num'] as $c){ if (has_col($conexion,'peleas_evento',$c)){ $C_NUM=$c; break; } }
          $C_GAN = has_col($conexion,'peleas_evento','ganador_color')?'ganador_color':(has_col($conexion,'peleas_evento','ganador')?'ganador':null);
          $C_EST = has_col($conexion,'peleas_evento','estado')?'estado':null;
          $cols="id".($C_NUM?(','.bt($C_NUM).' AS pnum'):',NULL AS pnum')
                 .($C_R?(','.bt($C_R).' AS rds'):',NULL AS rds')
                 .($C_AZ?(','.bt($C_AZ).' AS az'):'')
                 .($C_RO?(','.bt($C_RO).' AS ro'):'')
                 .($C_GAN?(','.bt($C_GAN).' AS gan'):',NULL AS gan')
                 .($C_EST?(','.bt($C_EST).' AS est'):',NULL AS est');
          if ($st2=$conexion->prepare("SELECT $cols FROM peleas_evento WHERE id=? LIMIT 1")){
            $st2->bind_param('i',$pelea_actual_id);
            $st2->execute(); $st2->store_result();
            if ($C_AZ && $C_RO){ $st2->bind_result($pid,$pnum,$rds,$az,$ro,$gan,$est); }
            elseif ($C_AZ){ $st2->bind_result($pid,$pnum,$rds,$az,$gan,$est); $ro=null; }
            elseif ($C_RO){ $st2->bind_result($pid,$pnum,$rds,$ro,$gan,$est); $az=null; }
            else { $st2->bind_result($pid,$pnum,$rds,$gan,$est); $az=$ro=null; }

            if ($st2->fetch()){
              $data['pelea'] = ['id'=>(int)$pid,'numero'=> is_null($pnum)?null:(int)$pnum,'rondas'=> is_null($rds)?null:(int)$rds,'ganador_color'=>$gan?:null,'estado'=>$est?:null];
              // competidores
              $ids=[]; if (!empty($az)) $ids[]=(int)$az; if (!empty($ro)) $ids[]=(int)$ro;
              if ($ids && table_exists($conexion,'competidores_evento')){
                $C_ESC = has_col($conexion,'competidores_evento','escuela_nombre')?'escuela_nombre':(has_col($conexion,'competidores_evento','escuela')?'escuela':(has_col($conexion,'competidores_evento','gimnasio')?'gimnasio':null));
                $LOGOS=['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela']; $C_LOGO=null;
                foreach($LOGOS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }
                $colsC="id, TRIM(CONCAT(COALESCE(apellido,''),' ',COALESCE(nombre,''))) AS nom";
                $colsC .= $C_ESC? (", ".bt($C_ESC)." AS esc") : ", NULL AS esc";
                $colsC .= $C_LOGO?(", ".bt($C_LOGO)." AS logo") : ", NULL AS logo";
                $ph=implode(',', array_fill(0,count($ids),'?')); $typ=str_repeat('i', count($ids));
                $sqlC="SELECT $colsC FROM competidores_evento WHERE id IN ($ph)";
                if ($st3=$conexion->prepare($sqlC)){
                  $st3->bind_param($typ, ...$ids);
                  $st3->execute(); $st3->bind_result($cid,$nom,$esc,$logo);
                  while($st3->fetch()){
                    $arr=['id'=>(int)$cid,'nombre'=>$nom?:null,'escuela'=>$esc?:null,'logo'=>$logo?:null];
                    if (!empty($az) && (int)$cid===(int)$az) $data['azul']=$arr;
                    if (!empty($ro) && (int)$cid===(int)$ro) $data['rojo']=$arr;
                  }
                  $st3->close();
                }
              }
            }
            $st2->close();
          }
        }
        $payload = $data;
        break; // hay cambio -> salgo
      }
    }
    $st->close();
  }
  if ($payload) break;
  usleep($step_ms*1000);
}

/* Respuesta */
if ($payload){
  echo json_encode(['ok'=>true,'changed'=>1,'version'=>$version,'data'=>$payload], JSON_UNESCAPED_UNICODE);
} else {
  echo json_encode(['ok'=>true,'changed'=>0,'version'=>$since,'data'=>null], JSON_UNESCAPED_UNICODE);
}
