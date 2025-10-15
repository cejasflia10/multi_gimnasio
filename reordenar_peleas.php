<?php
// reordenar_peleas.php — endpoint liviano para numeración (auto / manual)
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__.'/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); echo json_encode(['ok'=>false,'err'=>'BD']); exit; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

$evento_id = (int)($_POST['evento_id'] ?? 0);
$modo      = (string)($_POST['modo'] ?? ''); // 'auto' | 'manual'
$editsJson = (string)($_POST['edits'] ?? '{}'); // {"pelea_id": numero_editado, ...}

if ($evento_id<=0 || ($modo!=='auto' && $modo!=='manual')) {
  http_response_code(400); echo json_encode(['ok'=>false,'err'=>'params']); exit;
}

/* ——— Descubrimos columnas clave ——— */
$cols = [];
if ($r = $conexion->query("SHOW COLUMNS FROM peleas_evento")) {
  while($c = $r->fetch_assoc()){ $cols[strtolower($c['Field'])] = $c['Field']; }
  $r->close();
}
$pick=function(array $cands)use($cols){ foreach($cands as $c){ $lc=strtolower($c); if(isset($cols[$lc])) return $cols[$lc]; } return null; };
$C_ID     = $pick(['id','pelea_id','id_pelea']);
$C_EVENTO = $pick(['evento_id','id_evento','evento']);
$C_ORDEN  = $pick(['orden','orden_manual','nro','nro_orden','posicion','position','sequence','rank','numero','nro_pelea','sort']);
$C_FECHA  = $pick(['fecha','creado_en','created_at','created','fh_creacion']);

if(!$C_ID || !$C_EVENTO || !$C_ORDEN){
  http_response_code(500); echo json_encode(['ok'=>false,'err'=>'cols']); exit;
}

/* ——— Obtenemos el orden visual actual (estable) ——— */
$orderBy = [];
$orderBy[] = "p.".bt($C_ORDEN)." IS NULL";
$orderBy[] = "p.".bt($C_ORDEN);
if ($C_FECHA) $orderBy[] = "p.".bt($C_FECHA);
$orderBy[] = "p.".bt($C_ID);

$sql = "SELECT p.".bt($C_ID)." AS id, p.".bt($C_ORDEN)." AS ord
        FROM peleas_evento p
        WHERE p.".bt($C_EVENTO)."=?
        ORDER BY ".implode(', ',$orderBy);
$ids=[]; $ords=[];
if ($st=$conexion->prepare($sql)){
  $st->bind_param('i',$evento_id); $st->execute();
  $rs=$st->get_result();
  while($row=$rs->fetch_assoc()){ $ids[]=(int)$row['id']; $ords[(int)$row['id']] = is_null($row['ord']) ? null : (int)$row['ord']; }
  $st->close();
}
$N = count($ids);
if ($N===0){ echo json_encode(['ok'=>true,'msg'=>'sin_peleas']); exit; }

try{
  $conexion->begin_transaction();

  if ($modo==='auto') {
    // AUTO: 1..N según el orden visual actual
    $sqlUp = "UPDATE peleas_evento SET ".bt($C_ORDEN)."=? WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID)."=? LIMIT 1";
    $st = $conexion->prepare($sqlUp);
    if (!$st) throw new RuntimeException($conexion->error);
    $n=1;
    foreach ($ids as $pid){
      $st->bind_param('iii',$n,$evento_id,$pid);
      $st->execute();
      $n++;
    }
    $st->close();
    $conexion->commit();
    echo json_encode(['ok'=>true,'modo'=>'auto','asignados'=>$N]); exit;
  }

  // MANUAL: en cascada
  $edits = json_decode($editsJson,true);
  if (!is_array($edits)) $edits = [];

  // Mapa: id => valor_ingresado (>=1) o null
  $anchor = [];
  foreach ($edits as $pid=>$v){
    if (!is_numeric($pid)) continue;
    $pid=(int)$pid; $v=(int)$v;
    if ($v>=1) $anchor[$pid]=$v; // guardamos solo válidos
  }

  // Recorremos en orden visual y aplicamos cascada
  $final = [];
  $next = 1;
  foreach ($ids as $pid){
    if (isset($anchor[$pid]) && $anchor[$pid] >= $next){
      $final[$pid] = $anchor[$pid];
      $next = $anchor[$pid] + 1;
    } else {
      $final[$pid] = $next;
      $next++;
    }
  }

  // Persistimos SOLO si cambia
  $sqlUp = "UPDATE peleas_evento SET ".bt($C_ORDEN)."=? WHERE ".bt($C_EVENTO)."=? AND ".bt($C_ID)."=? LIMIT 1";
  $st = $conexion->prepare($sqlUp);
  if (!$st) throw new RuntimeException($conexion->error);
  $cambios=0;
  foreach ($final as $pid=>$val){
    if ($ords[$pid] !== $val){
      $st->bind_param('iii',$val,$evento_id,$pid);
      $st->execute(); $cambios += max(0,$st->affected_rows);
    }
  }
  $st->close();
  $conexion->commit();
  echo json_encode(['ok'=>true,'modo'=>'manual','cambios'=>$cambios,'n'=>$N]);
} catch (Throwable $e){
  $conexion->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false,'err'=>$e->getMessage()]);
}
