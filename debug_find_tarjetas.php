<?php
// debug_find_tarjetas.php
// Diagnóstico del esquema de tarjetas por round.
// Úsalo con ?pelea_id=123 (opcional). NO requiere endpoints: solo SQL.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
header('Content-Type: text/html; charset=utf-8');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

if (!isset($conexion) || !($conexion instanceof mysqli)) { exit('❌ Sin conexión BD'); }
@$conexion->set_charset('utf8mb4');

$pelea_id = (int)($_GET['pelea_id'] ?? 0);

$table_candidates = [
  'tarjetas_rounds','tarjetas_por_round','tarjetas_jueces','tarjetas',
  'puntajes_round','scores_round','puntuaciones',
  'tarjetas_pelea_round','judges_scores','scores','resultado_rounds'
];

$c_pelea = ['pelea_id','id_pelea','pelea','fight_id','id_fight'];
$c_juez  = ['juez_id','id_juez','juez','judge_id','id_judge'];
$c_round = ['round','rnd','round_num','n_round'];
$c_azul  = ['azul_puntos','puntos_azul','azul','pts_azul','blue','blue_points'];
$c_rojo  = ['rojo_puntos','puntos_rojo','rojo','pts_rojo','red','red_points'];
$c_gana  = ['ganador','winner','resultado','win','who','who_won'];

$pick = function(array $cols, array $cands){
  foreach($cands as $c){ $lc=strtolower($c); if(isset($cols[$lc])) return $cols[$lc]; }
  return null;
};

echo "<h2>🔎 Diagnóstico de tabla/columnas de tarjetas</h2>";

$tables = [];
if ($rs = $conexion->query("SHOW TABLES")) {
  while($r=$rs->fetch_row()){ $tables[]=$r[0]; }
  $rs->close();
}
echo "<p><b>Tablas en BD:</b> ".h(implode(', ',$tables))."</p>";

$T = null;
foreach ($tables as $t){
  if (in_array(strtolower($t), array_map('strtolower',$table_candidates), true)) { $T=$t; break; }
}
if (!$T) {
  // heurística por LIKEs
  foreach ($tables as $t){ if (stripos($t,'round')!==false) { $T=$t; break; } }
  if (!$T) foreach ($tables as $t){ if (stripos($t,'tarjet')!==false) { $T=$t; break; } }
  if (!$T) foreach ($tables as $t){ if (stripos($t,'score')!==false) { $T=$t; break; } }
}
if (!$T) { echo "<p style='color:#c00'><b>No se encontró tabla candidata de tarjetas.</b></p>"; exit; }

echo "<p><b>Tabla candidata:</b> ".h($T)."</p>";

$cols = [];
if ($rs = $conexion->query("SHOW COLUMNS FROM ".bt($T))) {
  while($r=$rs->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
  $rs->close();
}
echo "<pre style='background:#111;color:#0f0;padding:10px;border-radius:8px'>".h(print_r($cols,true))."</pre>";

$C = [];
$C['pelea'] = $pick($cols, $c_pelea);
$C['juez']  = $pick($cols, $c_juez);
$C['round'] = $pick($cols, $c_round);
$C['azul']  = $pick($cols, $c_azul);
$C['rojo']  = $pick($cols, $c_rojo);
$C['ganador'] = $pick($cols, $c_gana);

echo "<p><b>Mapeo detectado:</b></p><ul>";
foreach ($C as $k=>$v){ echo "<li>".h($k)." → <code>".h($v?:'(no encontrado)')."</code></li>"; }
echo "</ul>";

if (!$C['pelea'] || !$C['juez'] || !$C['round']){
  echo "<p style='color:#c00'><b>Faltan columnas mínimas (pelea/juez/round). Ajustá nombres o define constantes en get_tablero_tarjetas.php.</b></p>";
  exit;
}

$colP = bt($C['pelea']); $colJ=bt($C['juez']); $colR=bt($C['round']);
$colA = $C['azul'] ? bt($C['azul']) : "NULL";
$colRo= $C['rojo'] ? bt($C['rojo']) : "NULL";
$colG = $C['ganador'] ? (", ".bt($C['ganador'])." AS ganador") : "";

$where = $pelea_id>0 ? "WHERE $colP = $pelea_id" : "";
$sql = "SELECT $colP AS pelea_id, $colR AS round, $colJ AS juez_id, $colA AS azul_puntos, $colRo AS rojo_puntos $colG
        FROM ".bt($T)." $where
        ORDER BY $colP DESC, $colR ASC, $colJ ASC
        LIMIT 10";
echo "<p><b>SQL ejemplo:</b> <code>".h($sql)."</code></p>";

echo "<h3>Muestra (10 filas):</h3>";
if ($rs = $conexion->query($sql)) {
  echo "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;background:#111;color:#eee'>";
  echo "<tr><th>pelea_id</th><th>round</th><th>juez_id</th><th>azul_puntos</th><th>rojo_puntos</th><th>ganador</th></tr>";
  while($r=$rs->fetch_assoc()){
    echo "<tr>";
    echo "<td>".h($r['pelea_id'])."</td>";
    echo "<td>".h($r['round'])."</td>";
    echo "<td>".h($r['juez_id'])."</td>";
    echo "<td>".h($r['azul_puntos'])."</td>";
    echo "<td>".h($r['rojo_puntos'])."</td>";
    echo "<td>".h($r['ganador'] ?? '')."</td>";
    echo "</tr>";
  }
  echo "</table>";
  $rs->close();
} else {
  echo "<p style='color:#c00'>Error ejecutando muestra: ".h($conexion->error)."</p>";
}
