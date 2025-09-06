<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/conexion.php';

if (!isset($_SESSION['user_rol']) || $_SESSION['user_rol']!=='admin') {
  http_response_code(403); exit('Solo admin');
}
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('BD'); }
@$conexion->set_charset('utf8mb4');

$pelea_id = isset($_POST['pelea_id'])?(int)$_POST['pelea_id']:0;
$evento_id = isset($_POST['evento_id'])?(int)$_POST['evento_id']:0;
if ($pelea_id<=0){ exit('pelea_id'); }

/* Detectar columnas dinamicas */
$cols=[]; $r=$conexion->query("SHOW COLUMNS FROM peleas_evento");
while($c=$r->fetch_assoc()){ $cols[strtolower($c['Field'])]=$c['Field']; }
$pick=function($arr)use($cols){ foreach($arr as $x){$lx=strtolower($x); if(isset($cols[$lx])) return $cols[$lx];} return null; };
$C_EVENTO=$pick(['evento_id','id_evento','evento']);
$C_ROJO=$pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL=$pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_GANADOR=$pick(['ganador_id','id_ganador','ganador']);
$C_RESULT=$pick(['resultado','result','detalle_resultado']);
$C_ESTADO=$pick(['estado','status']);

if(!$C_EVENTO||!$C_ROJO||!$C_AZUL){ exit('Columnas peleas_evento faltantes'); }

/* traer ids rojo/azul + evento */
$st=$conexion->prepare("SELECT $C_EVENTO AS evento_id, $C_ROJO AS rojo_id, $C_AZUL AS azul_id FROM peleas_evento WHERE id=?");
$st->bind_param('i',$pelea_id); $st->execute();
$P=$st->get_result()->fetch_assoc(); $st->close();
if(!$P){ exit('Pelea no existe'); }
$evento_id = $evento_id ?: (int)$P['evento_id'];
$rojo_id=(int)$P['rojo_id']; $azul_id=(int)$P['azul_id'];

/* totales por juez */
$st=$conexion->prepare("SELECT j.id, j.nombre,
  COALESCE(SUM(CASE WHEN pr.juez_id=j.id THEN pr.rojo_puntos END),0) AS r_sum,
  COALESCE(SUM(CASE WHEN pr.juez_id=j.id THEN pr.azul_puntos END),0) AS a_sum
  FROM peleas_jueces pj
  JOIN jueces_evento j ON j.id=pj.juez_id
  LEFT JOIN puntajes_round pr ON pr.pelea_id = pj.pelea_id AND pr.juez_id = j.id
  WHERE pj.pelea_id=?
  GROUP BY j.id, j.nombre
  ORDER BY j.id");
$st->bind_param('i',$pelea_id); $st->execute();
$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC); $st->close();

$votes=['rojo'=>0,'azul'=>0,'empate'=>0];
foreach($rows as $r){
  if ($r['r_sum']>$r['a_sum']) $votes['rojo']++;
  elseif ($r['a_sum']>$r['r_sum']) $votes['azul']++;
  else $votes['empate']++;
}
function decisionLabel($v){
  if ($v['rojo']===3) return 'Decisión unánime (3-0) ROJO';
  if ($v['azul']===3) return 'Decisión unánime (3-0) AZUL';
  if ($v['rojo']===2 && $v['azul']===1) return 'Decisión dividida (2-1) ROJO';
  if ($v['azul']===2 && $v['rojo']===1) return 'Decisión dividida (2-1) AZUL';
  if ($v['rojo']===2 && $v['empate']===1) return 'Decisión mayoritaria (2-0-1) ROJO';
  if ($v['azul']===2 && $v['empate']===1) return 'Decisión mayoritaria (2-0-1) AZUL';
  if ($v['empate']===3) return 'Empate (0-0-3)';
  if ($v['rojo']===1 && $v['azul']===1 && $v['empate']===1) return 'Empate (1-1-1)';
  return 'Indefinido';
}
$label = decisionLabel($votes);
$ganador_id = null;
if (strpos($label,'ROJO')!==false) $ganador_id = $rojo_id;
elseif (strpos($label,'AZUL')!==false) $ganador_id = $azul_id;

/* actualizar pelea */
$sets=[]; $args=[]; $types='';
if($C_GANADOR){ $sets[]="`$C_GANADOR`=?"; $args[]=$ganador_id; $types.='i'; }
if($C_RESULT){ $sets[]="`$C_RESULT`=?"; $args[]=$label; $types.='s'; }
if($C_ESTADO){ $sets[]="`$C_ESTADO`=?"; $args[]='finalizada'; $types.='s'; }

if($sets){
  $sql="UPDATE peleas_evento SET ".implode(',',$sets)." WHERE id=?";
  $st=$conexion->prepare($sql);
  $types.='i'; $args[]=$pelea_id;
  $bind=[]; $bind[]=$types; foreach($args as $k=>&$v){ $bind[]=&$v; }
  call_user_func_array([$st,'bind_param'],$bind);
  $st->execute(); $st->close();
}

header('Location: ver_peleas_evento.php?evento_id='.(int)$evento_id);
exit;
