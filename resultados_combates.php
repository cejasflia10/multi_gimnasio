<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($conexion) || !($conexion instanceof mysqli)) {
  http_response_code(500);
  exit('❌ Sin conexión a BD.');
}
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

$DEBUG = (isset($_GET['debug']) && $_GET['debug']=='1');

/* ===== Helpers (compatibles con PHP 5.x) ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function post($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

function table_exists($db, $t){
  if (!($db instanceof mysqli)) return false;
  $t = $db->real_escape_string($t);
  if ($r=$db->query("SHOW TABLES LIKE '{$t}'")) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col($db, $t, $c){
  if (!($db instanceof mysqli)) return false;
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$t}' AND COLUMN_NAME='{$c}' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
/** Devuelve el primer nombre de columna existente en $cands o null */
function resolve_col($db, $table, $cands){
  foreach ($cands as $c) { if (has_col($db, $table, $c)) return $c; }
  return null;
}
/** Envuelve prepare con chequeo y mensaje amigable */
function safe_prepare($db, $sql, &$view, $DEBUG){
  $st = $db->prepare($sql);
  if (!$st) {
    $view['error'] = 'Error al preparar consulta SQL.';
    if ($DEBUG) {
      $view['debug_sql'] = $sql;
      $view['debug_err'] = $db->error;
    }
  }
  return $st;
}

/* =========================================================
   Descubrir nombres variables en peleas_evento (rojo/azul/evento)
   ========================================================= */
$cols_pe = array();
if ($rs = $conexion->query("SHOW COLUMNS FROM `peleas_evento`")) {
  while($r=$rs->fetch_assoc()){ $cols_pe[strtolower($r['Field'])]=$r['Field']; }
  $rs->close();
}
$C_ROJO = isset($cols_pe['competidor_rojo_id']) ? $cols_pe['competidor_rojo_id']
       : (isset($cols_pe['rojo_id']) ? $cols_pe['rojo_id']
       : (isset($cols_pe['id_rojo']) ? $cols_pe['id_rojo'] : null));
$C_AZUL = isset($cols_pe['competidor_azul_id']) ? $cols_pe['competidor_azul_id']
       : (isset($cols_pe['azul_id']) ? $cols_pe['azul_id']
       : (isset($cols_pe['id_azul']) ? $cols_pe['id_azul'] : null));
$C_EVEN = isset($cols_pe['evento_id']) ? $cols_pe['evento_id']
       : (isset($cols_pe['id_evento']) ? $cols_pe['id_evento']
       : (isset($cols_pe['evento']) ? $cols_pe['evento'] : null));

/* =========================================================
   Modo A) POST: viene desde algún form y guardamos en combates_evento
   Modo B) GET: admite ?id=... (combate_id) o ?pelea_id=...
   ========================================================= */
$modo_post = ($_SERVER['REQUEST_METHOD']==='POST');
$combate_id = null;
$view = array(); // datos para render

/* =========================================================
   A) Guardado (POST) -> combates_evento
   ========================================================= */
if ($modo_post) {
  $pelea_id   = toIntOrNull(post('pelea_id')); if ($pelea_id===null) $pelea_id=0;
  $evento_id  = toIntOrNull(post('evento_id')); if ($evento_id===null) $evento_id=0;

  $mayoria        = post('mayoria'); // 'rojo'|'azul'|'empate'|''
  $votos_azul     = toIntOrNull(post('votos_azul'));     if ($votos_azul===null) $votos_azul=0;
  $votos_rojo     = toIntOrNull(post('votos_rojo'));     if ($votos_rojo===null) $votos_rojo=0;
  $votos_empate   = toIntOrNull(post('votos_empate'));   if ($votos_empate===null) $votos_empate=0;
  $sum_total_azul = toIntOrNull(post('sum_total_azul')); if ($sum_total_azul===null) $sum_total_azul=0;
  $sum_total_rojo = toIntOrNull(post('sum_total_rojo')); if ($sum_total_rojo===null) $sum_total_rojo=0;

  $pts_total_azul = toIntOrNull(post('pts_total_azul')); if ($pts_total_azul===null) $pts_total_azul=0;
  $pts_total_rojo = toIntOrNull(post('pts_total_rojo')); if ($pts_total_rojo===null) $pts_total_rojo=0;
  $ganador_pts    = post('ganador_por_puntos'); // 'rojo'|'azul'|'empate'

  $cierre_forzado = (post('cierre_forzado') === '1');
  $update_ranking = (post('update_ranking') !== '0');

  if ($pelea_id<=0 || $evento_id<=0) {
    $_SESSION['flash_error']='Faltan pelea_id o evento_id.';
    $modo_post = false;
    $view['error'] = 'No se pudo registrar: faltan parámetros.';
  } else {
    if (!$C_ROJO || !$C_AZUL) {
      $view['error'] = 'No se detectaron columnas de rojo/azul en peleas_evento.';
      $modo_post = false;
    } else {
      $sql = "SELECT ".
             ($C_EVEN? bt($C_EVEN)." AS evento_id,":"NULL AS evento_id,").
             bt($C_ROJO)." AS rojo_id, ".bt($C_AZUL)." AS azul_id ".
             "FROM `peleas_evento` WHERE id=? LIMIT 1";
      $st = safe_prepare($conexion, $sql, $view, $DEBUG);
      if (!$st) { $modo_post=false; }
      else {
        $st->bind_param('i',$pelea_id);
        $st->execute();
        $st->bind_result($X_evento,$rojo_id,$azul_id);
        $ok = $st->fetch();
        $st->close();

        if (!$ok) {
          $view['error'] = 'No se encontró la pelea para registrar resultados.';
          $modo_post = false;
        } else {
          $evento_id = (int)($X_evento ? $X_evento : $evento_id);

          // Ganador final
          $ganador_final = 'empate';
          if ($ganador_pts==='rojo' || $mayoria==='rojo') $ganador_final = 'rojo';
          if ($ganador_pts==='azul' || $mayoria==='azul') $ganador_final = 'azul';

          // Detalle
          $detalle = "Puntos totales — Rojo {$pts_total_rojo} / Azul {$pts_total_azul}";
          if ($mayoria!=='') {
            $detalle .= " · Mayoría tarjetas: R{$votos_rojo}-A{$votos_azul}-E{$votos_empate} ".
                        "(Σ Rojo {$sum_total_rojo} / Azul {$sum_total_azul})";
          }
          if ($cierre_forzado) $detalle .= " · *Cierre anticipado*";

          // UPSERT en combates_evento
          $t = 'combates_evento';
          $colsC=array(); $valsC=array(); $typesC='';

          $add=function($c,$v,$tp) use(&$colsC,&$valsC,&$typesC){ $colsC[]="`$c`"; $valsC[]=$v; $typesC.=$tp; };
          $has=function($c) use($conexion,$t){ return has_col($conexion,$t,$c); };

          if (table_exists($conexion,$t)) {
            if ($has('evento_id'))      $add('evento_id',$evento_id,'i');
            if ($has('pelea_id'))       $add('pelea_id',$pelea_id,'i');
            if ($has('rojo_id'))        $add('rojo_id',$rojo_id,'i');
            if ($has('azul_id'))        $add('azul_id',$azul_id,'i');
            if ($has('ganador'))        $add('ganador',$ganador_final,'s');
            if ($has('resultado'))      $add('resultado',$detalle,'s');
            if ($has('pts_total_rojo')) $add('pts_total_rojo',$pts_total_rojo,'i');
            if ($has('pts_total_azul')) $add('pts_total_azul',$pts_total_azul,'i');
            if ($has('votos_rojo'))     $add('votos_rojo',$votos_rojo,'i');
            if ($has('votos_azul'))     $add('votos_azul',$votos_azul,'i');
            if ($has('votos_empate'))   $add('votos_empate',$votos_empate,'i');
            if ($has('mayoria'))        $add('mayoria',$mayoria,'s');
            if ($has('fecha'))          $add('fecha', date('Y-m-d H:i:s'),'s');

            // ¿existe ya por pelea_id?
            $existe_id=null;
            if ($has('pelea_id')) {
              $sqlChk = "SELECT id FROM `{$t}` WHERE ".bt('pelea_id')."=? ORDER BY id DESC LIMIT 1";
              $chk = safe_prepare($conexion, $sqlChk, $view, $DEBUG);
              if ($chk) {
                $chk->bind_param('i',$pelea_id); $chk->execute();
                $r=$chk->get_result();
                if ($r && $r->num_rows) { $row=$r->fetch_assoc(); $existe_id=(int)$row['id']; }
                $chk->close();
              } else {
                $modo_post=false;
              }
            }

            if ($modo_post) {
              if ($existe_id) {
                $set=array(); foreach($colsC as $c){ $set[]="$c=?"; }
                $sqlU="UPDATE `{$t}` SET ".implode(',', $set)." WHERE id=?";
                $stU=safe_prepare($conexion,$sqlU,$view,$DEBUG);
                if ($stU){
                  $types2=$typesC.'i'; $vals2=$valsC; $vals2[]=$existe_id;
                  $bind=array($types2); foreach($vals2 as $k=>$v){ $bind[]=&$vals2[$k]; }
                  call_user_func_array(array($stU,'bind_param'),$bind);
                  $stU->execute(); $stU->close();
                  $combate_id=$existe_id;
                }
              } else {
                $ph=rtrim(str_repeat('?,',count($colsC)),',');
                $sqlI="INSERT INTO `{$t}` (".implode(',',$colsC).") VALUES ($ph)";
                $stI=safe_prepare($conexion,$sqlI,$view,$DEBUG);
                if ($stI){
                  $bind=array($typesC); foreach($valsC as $k=>$v){ $bind[]=&$valsC[$k]; }
                  call_user_func_array(array($stI,'bind_param'),$bind);
                  $stI->execute(); $stI->close();
                  $combate_id=(int)$conexion->insert_id;
                }
              }
            }
          } // table_exists combates_evento

          // Ranking (opcional)
          if ($modo_post && $update_ranking && table_exists($conexion,'competidores_evento')) {
            $tC='competidores_evento';
            $hasW=has_col($conexion,$tC,'wins');
            $hasL=has_col($conexion,$tC,'losses');
            $hasD=has_col($conexion,$tC,'draws');
            if ($hasW && $hasL && $hasD) {
              if ($ganador_final==='rojo') {
                $conexion->query("UPDATE `$tC` SET wins = wins + 1 WHERE id=".(int)$rojo_id);
                $conexion->query("UPDATE `$tC` SET losses = losses + 1 WHERE id=".(int)$azul_id);
              } elseif ($ganador_final==='azul') {
                $conexion->query("UPDATE `$tC` SET wins = wins + 1 WHERE id=".(int)$azul_id);
                $conexion->query("UPDATE `$tC` SET losses = losses + 1 WHERE id=".(int)$rojo_id);
              } else {
                $conexion->query("UPDATE `$tC` SET draws = draws + 1 WHERE id=".(int)$rojo_id);
                $conexion->query("UPDATE `$tC` SET draws = draws + 1 WHERE id=".(int)$azul_id);
              }
            }
          }

          // Preparar datos vista
          $view = array_merge($view, array(
            'evento_id'=>$evento_id,'pelea_id'=>$pelea_id,'combate_id'=>$combate_id,
            'rojo_id'=>$rojo_id,'azul_id'=>$azul_id,'ganador_final'=>$ganador_final,
            'pts_rojo'=>$pts_total_rojo,'pts_azul'=>$pts_total_azul,
            'mayoria'=>$mayoria,'votos_rojo'=>$votos_rojo,'votos_azul'=>$votos_azul,'votos_empate'=>$votos_empate,
            'sum_rojo'=>$sum_total_rojo,'sum_azul'=>$sum_total_azul,'cierre_forzado'=>$cierre_forzado
          ));

          // Nombres
          if (table_exists($conexion,'competidores_evento')) {
            $qN=safe_prepare($conexion,"SELECT id, apellido, nombre FROM competidores_evento WHERE id IN (?,?)",$view,$DEBUG);
            if ($qN){
              $qN->bind_param('ii',$rojo_id,$azul_id); $qN->execute();
              $resN=$qN->get_result(); $noms=array();
              while($resN && $row=$resN->fetch_assoc()){ $noms[(int)$row['id']] = trim(($row['apellido']? $row['apellido']:'').' '.($row['nombre']? $row['nombre']:'')); }
              $qN->close();
              $view['rojo_name'] = isset($noms[$rojo_id]) && $noms[$rojo_id]!=='' ? $noms[$rojo_id] : '#'.$rojo_id;
              $view['azul_name'] = isset($noms[$azul_id]) && $noms[$azul_id]!=='' ? $noms[$azul_id] : '#'.$azul_id;
            }
          }
        }
      }
    }
  }
}

/* =========================================================
   B) Vista por GET (acepta ?id=... o ?pelea_id=...)
   ========================================================= */
if (!$modo_post && empty($view['error'])) {
  $combate_id = toIntOrNull(isset($_GET['id']) ? $_GET['id'] : ''); 
  $pelea_id_q = toIntOrNull(isset($_GET['pelea_id']) ? $_GET['pelea_id'] : '');

  if ($combate_id) {
    if (table_exists($conexion,'combates_evento')) {
      $sql = "SELECT ce.*,
                     cr.apellido AS r_apellido, cr.nombre AS r_nombre,
                     ca.apellido AS a_apellido, ca.nombre AS a_nombre
              FROM `combates_evento` ce
              LEFT JOIN `competidores_evento` cr ON ce.rojo_id = cr.id
              LEFT JOIN `competidores_evento` ca ON ce.azul_id = ca.id
              WHERE ce.id = ? LIMIT 1";
      $st = safe_prepare($conexion,$sql,$view,$DEBUG);
      if ($st){
        $st->bind_param('i',$combate_id);
        $st->execute();
        $r = $st->get_result(); $row = $r ? $r->fetch_assoc() : null;
        $st->close();

        if ($row) {
          $view['evento_id']     = (int)(isset($row['evento_id'])?$row['evento_id']:0);
          $view['pelea_id']      = (int)(isset($row['pelea_id'])?$row['pelea_id']:0);
          $view['combate_id']    = (int)$combate_id;
          $view['rojo_id']       = (int)(isset($row['rojo_id'])?$row['rojo_id']:0);
          $view['azul_id']       = (int)(isset($row['azul_id'])?$row['azul_id']:0);
          $rnom = trim((isset($row['r_apellido'])?$row['r_apellido']:'').' '.(isset($row['r_nombre'])?$row['r_nombre']:''));
          $anom = trim((isset($row['a_apellido'])?$row['a_apellido']:'').' '.(isset($row['a_nombre'])?$row['a_nombre']:''));
          $view['rojo_name']     = $rnom!=='' ? $rnom : ('#'.(isset($view['rojo_id'])?$view['rojo_id']:0));
          $view['azul_name']     = $anom!=='' ? $anom : ('#'.(isset($view['azul_id'])?$view['azul_id']:0));
          $view['ganador_final'] = (string)(isset($row['ganador'])?$row['ganador']:'empate');
          $view['detalle']       = (string)(isset($row['resultado'])?$row['resultado']:'');
          $view['pts_rojo']      = (int)(isset($row['pts_total_rojo'])?$row['pts_total_rojo']:0);
          $view['pts_azul']      = (int)(isset($row['pts_total_azul'])?$row['pts_total_azul']:0);
          $view['fecha']         = (string)(isset($row['fecha'])?$row['fecha']:'');
        } else {
          $view['error'] = 'No se encontró el combate solicitado.';
        }
      }
    } else {
      $view['error'] = 'No existe la tabla combates_evento.';
    }
  }
  elseif ($pelea_id_q) {
    $encontro = false;

    // 1) Buscar en combates_evento por pelea_id (con alias)
    $tCE = 'combates_evento';
    if (table_exists($conexion,$tCE)) {
      $colPelea = resolve_col($conexion,$tCE,array('pelea_id','id_pelea','pelea','fight_id'));
      if ($colPelea) {
        $sql = "SELECT id FROM `{$tCE}` WHERE ".bt($colPelea)."=? ORDER BY id DESC LIMIT 1";
        $st  = safe_prepare($conexion,$sql,$view,$DEBUG);
        if ($st){
          $st->bind_param('i',$pelea_id_q); $st->execute();
          $r=$st->get_result(); $row=$r?$r->fetch_assoc():null; $st->close();
          if ($row) { header("Location: resultados_combates.php?id=".$row['id']); exit; }
        }
      }
    }

    // 2) Fallback: resultados_combates
    if (!$encontro && table_exists($conexion,'resultados_combates')) {
      $colRC = resolve_col($conexion,'resultados_combates',array('pelea_id','id_pelea','pelea','fight_id'));
      if ($colRC) {
        $q = "SELECT * FROM `resultados_combates` WHERE ".bt($colRC)."=? ORDER BY id DESC LIMIT 1";
        $st=safe_prepare($conexion,$q,$view,$DEBUG);
        if ($st){
          $st->bind_param('i',$pelea_id_q); $st->execute();
          $r=$st->get_result(); $res=$r?$r->fetch_assoc():null; $st->close();
          if ($res) {
            // Traer rojo/azul desde peleas_evento
            $rojo_id = 0; $azul_id = 0; $evento_id = (int)(isset($res['evento_id'])?$res['evento_id']:0);
            if ($C_ROJO && $C_AZUL) {
              $sqlPE = "SELECT ".bt($C_ROJO)." AS ro,".bt($C_AZUL)." AS az,".($C_EVEN? bt($C_EVEN)." AS ev":"NULL AS ev").
                       " FROM `peleas_evento` WHERE id=? LIMIT 1";
              $st2=safe_prepare($conexion,$sqlPE,$view,$DEBUG);
              if ($st2){
                $st2->bind_param('i',$pelea_id_q); $st2->execute();
                $st2->bind_result($rojo_id,$azul_id,$evtmp); $st2->fetch(); $st2->close();
                if ($evtmp) $evento_id = (int)$evtmp;
              }
            }
            $ro_name = '#'.$rojo_id; $az_name = '#'.$azul_id;
            if (table_exists($conexion,'competidores_evento') && $rojo_id && $azul_id) {
              $qN=safe_prepare($conexion,"SELECT id, apellido, nombre FROM `competidores_evento` WHERE id IN (?,?)",$view,$DEBUG);
              if ($qN){
                $qN->bind_param('ii',$rojo_id,$azul_id); $qN->execute();
                $resN=$qN->get_result(); 
                while($resN && $row=$resN->fetch_assoc()){
                  $nm = trim((isset($row['apellido'])?$row['apellido']:'').' '.(isset($row['nombre'])?$row['nombre']:''));
                  if ((int)$row['id']===$rojo_id) $ro_name=$nm? $nm : ('#'.$rojo_id);
                  if ((int)$row['id']===$azul_id) $az_name=$nm? $nm : ('#'.$azul_id);
                }
                $qN->close();
              }
            }

            $gan_color = strtolower((string)(isset($res['ganador_color'])?$res['ganador_color']:''));
            $ganador_final = ($gan_color==='rojo' || $gan_color==='azul') ? $gan_color : 'empate';

            $view = array(
              'evento_id'=>$evento_id,
              'pelea_id'=>$pelea_id_q,
              'combate_id'=>null,
              'rojo_id'=>$rojo_id, 'azul_id'=>$azul_id,
              'rojo_name'=>$ro_name, 'azul_name'=>$az_name,
              'ganador_final'=>$ganador_final,
              'pts_rojo'=>(int)(isset($res['puntos_rojo'])?$res['puntos_rojo']:0),
              'pts_azul'=>(int)(isset($res['puntos_azul'])?$res['puntos_azul']:0),
              'detalle'=>(string)(isset($res['detalle'])?$res['detalle']:''),
              'fecha'=> (string)(isset($res['creado_en'])?$res['creado_en']:'')
            );
            $encontro = true;
          }
        }
      }
    }

    // 3) Fallback final: leer directo desde peleas_evento
    if (!$encontro && table_exists($conexion,'peleas_evento')) {
      $col_gan_color = resolve_col($conexion,'peleas_evento',array('ganador_color','ganador'));
      $col_gan_id    = resolve_col($conexion,'peleas_evento',array('ganador_id'));
      $col_detalle   = resolve_col($conexion,'peleas_evento',array('detalle_resultado','resolucion'));
      $col_estado    = resolve_col($conexion,'peleas_evento',array('estado'));
      $col_evento    = resolve_col($conexion,'peleas_evento',array('evento_id','id_evento','evento'));
      $col_azul_id   = resolve_col($conexion,'peleas_evento',array('competidor_azul_id','azul_id','id_azul'));
      $col_rojo_id   = resolve_col($conexion,'peleas_evento',array('competidor_rojo_id','rojo_id','id_rojo'));

      $sel = array();
      $sel[] = $col_evento ? bt($col_evento).' AS evento_id' : 'NULL AS evento_id';
      $sel[] = $col_rojo_id ? bt($col_rojo_id).' AS rojo_id' : 'NULL AS rojo_id';
      $sel[] = $col_azul_id ? bt($col_azul_id).' AS azul_id' : 'NULL AS azul_id';
      $sel[] = $col_gan_color ? bt($col_gan_color).' AS ganador_color' : "NULL AS ganador_color";
      $sel[] = $col_gan_id ? bt($col_gan_id).' AS ganador_id' : "NULL AS ganador_id";
      $sel[] = $col_detalle ? bt($col_detalle).' AS detalle' : "NULL AS detalle";
      $sel[] = $col_estado ? bt($col_estado).' AS estado' : "NULL AS estado";

      $sqlPE = "SELECT ".implode(',',$sel)." FROM `peleas_evento` WHERE id=? LIMIT 1";
      $stPE  = safe_prepare($conexion,$sqlPE,$view,$DEBUG);
      if ($stPE){
        $stPE->bind_param('i',$pelea_id_q);
        $stPE->execute();
        $rowPE = $stPE->get_result()->fetch_assoc();
        $stPE->close();

        if ($rowPE) {
          $rojo_id = (int)(isset($rowPE['rojo_id'])?$rowPE['rojo_id']:0);
          $azul_id = (int)(isset($rowPE['azul_id'])?$rowPE['azul_id']:0);
          $ro_name = '#'.$rojo_id; $az_name = '#'.$azul_id;

          if (table_exists($conexion,'competidores_evento') && $rojo_id && $azul_id) {
            $qN=safe_prepare($conexion,"SELECT id, apellido, nombre FROM `competidores_evento` WHERE id IN (?,?)",$view,$DEBUG);
            if ($qN){
              $qN->bind_param('ii',$rojo_id,$azul_id); $qN->execute();
              $resN=$qN->get_result();
              while($resN && $r=$resN->fetch_assoc()){
                $nm = trim((isset($r['apellido'])?$r['apellido']:'').' '.(isset($r['nombre'])?$r['nombre']:''));
                if ((int)$r['id']===$rojo_id) $ro_name = $nm ? $nm : ('#'.$rojo_id);
                if ((int)$r['id']===$azul_id) $az_name = $nm ? $nm : ('#'.$azul_id);
              }
              $qN->close();
            }
          }

          $gan_color = strtolower((string)(isset($rowPE['ganador_color'])?$rowPE['ganador_color']:''));
          $ganador_final = ($gan_color==='rojo' || $gan_color==='azul') ? $gan_color : 'empate';

          $view = array(
            'evento_id'     => (int)(isset($rowPE['evento_id'])?$rowPE['evento_id']:0),
            'pelea_id'      => (int)$pelea_id_q,
            'combate_id'    => null,
            'rojo_id'       => $rojo_id,
            'azul_id'       => $azul_id,
            'rojo_name'     => $ro_name,
            'azul_name'     => $az_name,
            'ganador_final' => $ganador_final,
            'pts_rojo'      => 0,
            'pts_azul'      => 0,
            'detalle'       => (string)(isset($rowPE['detalle'])?$rowPE['detalle']:''),
            'fecha'         => ''
          );
          $encontro = true;
        }
      }
    }

    if (!$encontro && empty($view['error'])) {
      $view['error'] = 'No hay registro de combates. (Falta combates_evento y resultados_combates)';
    }
  }
  else {
    $view['error'] = 'Falta parámetro: indique id de combate (?id=) o pelea_id (?pelea_id=).';
  }
}

/* =========================================================
   Render
   ========================================================= */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Resultados del Combate<?= isset($view['combate_id']) ? ' #'.(int)$view['combate_id'] : '' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    body{background:#0c0c0c;color:#fff;font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Helvetica,Arial,sans-serif}
    .wrap{max-width:900px;margin:0 auto;padding:16px}
    .card{background:#121212;border:1px solid #2a2a2a;border-radius:14px;padding:14px;margin-top:12px}
    h2{margin:.2rem 0 .8rem}
    .row{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#1f1f1f;font-size:12px}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;border:0;text-decoration:none;cursor:pointer}
    .btn-gold{background:#d4af37;color:#111}
    .btn-gray{background:#2a2a2a;color:#fff}
    .btn-green{background:#00c853;color:#111}
    .winR{color:#ff6b6b;font-weight:800}
    .winA{color:#6bb6ff;font-weight:800}
    .draw{color:#ffd54f;font-weight:800}
    table{width:100%;border-collapse:collapse;margin-top:8px}
    th,td{border:1px solid #2b2b2b;padding:8px 10px;text-align:left}
    th{background:#171717}
    .ok{padding:10px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1;margin-top:10px}
    .bad{padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4;margin-top:10px}
    .debug{padding:10px;border-radius:10px;background:#14192a;border:1px solid #2a3c6b;color:#c9d7ff;margin-top:10px;white-space:pre-wrap}
  </style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <h2>🥊 Resultados del Combate</h2>

  <?php if (!empty($view['error'])): ?>
    <div class="bad"><?= h($view['error']) ?></div>
    <?php if ($DEBUG && !empty($view['debug_sql'])): ?>
      <div class="debug"><b>SQL:</b> <?= h($view['debug_sql']) . "\n" ?><b>Error:</b> <?= h($view['debug_err'] ?? '') ?></div>
    <?php endif; ?>
  <?php else: ?>

    <div class="card">
      <div class="row" style="justify-content:space-between">
        <div>
          <div class="badge">Evento #<?= (int)($view['evento_id'] ?? 0) ?></div>
          <?php if (!empty($view['pelea_id'])): ?>
            <div class="badge" style="margin-left:6px">Pelea #<?= (int)$view['pelea_id'] ?></div>
          <?php endif; ?>
          <?php if (!empty($view['combate_id'])): ?>
            <div class="badge" style="margin-left:6px">Combate #<?= (int)$view['combate_id'] ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($view['fecha'])): ?>
          <div class="badge">Fecha: <?= h($view['fecha']) ?></div>
        <?php endif; ?>
      </div>

      <table>
        <tbody>
          <tr>
            <th style="width:40%">Rincón Rojo</th>
            <td class="winR"><?= h(isset($view['rojo_name']) ? $view['rojo_name'] : ('#'.(isset($view['rojo_id'])?$view['rojo_id']:0))) ?></td>
          </tr>
          <tr>
            <th>Rincón Azul</th>
            <td class="winA"><?= h(isset($view['azul_name']) ? $view['azul_name'] : ('#'.(isset($view['azul_id'])?$view['azul_id']:0))) ?></td>
          </tr>
          <tr>
            <th>Ganador</th>
            <td>
              <?php
                $g = isset($view['ganador_final']) ? $view['ganador_final'] : 'empate';
                if ($g==='rojo')   echo '<span class="winR">🔴 ROJO</span>';
                elseif ($g==='azul') echo '<span class="winA">🔵 AZUL</span>';
                else                 echo '<span class="draw">⚖️ EMPATE</span>';
              ?>
            </td>
          </tr>
          <tr>
            <th>Totales por puntos</th>
            <td>Rojo <?= (int)(isset($view['pts_rojo'])?$view['pts_rojo']:0) ?> · Azul <?= (int)(isset($view['pts_azul'])?$view['pts_azul']:0) ?></td>
          </tr>
          <?php if (!empty($view['mayoria'])): ?>
          <tr>
            <th>Mayoría tarjetas</th>
            <td>
              <?= 'R'.(int)(isset($view['votos_rojo'])?$view['votos_rojo']:0).' - A'.(int)(isset($view['votos_azul'])?$view['votos_azul']:0).' - E'.(int)(isset($view['votos_empate'])?$view['votos_empate']:0) ?>
              <?php if (isset($view['sum_rojo']) || isset($view['sum_azul'])): ?>
                <span class="badge" style="margin-left:6px">Σ Rojo <?= (int)(isset($view['sum_rojo'])?$view['sum_rojo']:0) ?> / Azul <?= (int)(isset($view['sum_azul'])?$view['sum_azul']:0) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endif; ?>
          <?php if (!empty($view['cierre_forzado'])): ?>
          <tr>
            <th>Observación</th>
            <td>Se registró <i>cierre anticipado</i> del combate.</td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($view['detalle'])): ?>
        <div class="ok"><b>Detalle:</b> <?= h($view['detalle']) ?></div>
      <?php endif; ?>

      <div class="row" style="margin-top:12px">
        <?php if (!empty($view['evento_id'])): ?>
          <a class="btn btn-gray" href="ver_peleas_evento.php?evento_id=<?= (int)$view['evento_id'] ?>">↩️ Volver al evento</a>
          <a class="btn btn-gold" href="ranking_competidores.php?evento_id=<?= (int)$view['evento_id'] ?>">📈 Ver ranking actualizado</a>
        <?php endif; ?>
        <?php if (!empty($view['combate_id'])): ?>
          <a class="btn btn-green" href="ver_combate.php?id=<?= (int)$view['combate_id'] ?>">👁 Ver ficha del combate</a>
        <?php endif; ?>
      </div>
    </div>

  <?php endif; ?>
</div>
</body>
</html>
