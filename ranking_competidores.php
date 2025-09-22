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

/* ===== Tablas mínimas ===== */
if (!has_table($conexion,'competidores_evento')) { exit('❌ Falta la tabla requerida: competidores_evento'); }
$hasPeleas = has_table($conexion,'peleas_evento');

/* ===== Columnas dinámicas ===== */
/* peleas_evento (opcional) */
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
/* récord al registrarse */
$C_WINS   = pick_col(['wins','win','w','ganadas','ganada'], $colsCe);
$C_LOSSES = pick_col(['losses','loss','l','perdidas','perdida'], $colsCe);
$C_DRAWS  = pick_col(['draws','draw','d','empates','empate'], $colsCe);
$C_NC     = pick_col(['no_contest','nocontest','nc','no_decision','no-decision','sin_decision','sin-decision'], $colsCe);

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
      $row['g'] = $g; // null => no suma
      $peleas[]=$row;
    }
    $r->close();
  }
}

/* ===== Traer todas las fichas ===== */
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

$fichas=[]; // por ID
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

/* ===== Agrupar Global =====
   - Si hay DNI: agrupamos por DNI.
   - Si NO hay DNI: quedamos por ficha (ID).
   - Base:
       * Si existen columnas de score => base = score de la ÚLTIMA ficha del DNI.
       * Si NO existen columnas => base = 0 (se avisará en UI) y luego sumamos TODAS las peleas de TODAS las fichas del DNI.
   - Suma de peleas:
       * Si existen columnas => sumamos SOLO las peleas de la ÚLTIMA ficha (evento actual).
       * Si NO existen columnas => sumamos peleas de TODAS las fichas.
*/
$usarDNI = (bool)$C_DNI;
$global = [];        // key: dni o id
$mapIdToKey = [];    // id_competidor_evento -> key

if ($usarDNI) {
  // localizar última ficha por DNI
  $ultimaPorDni = []; // dni => ficha
  foreach ($fichas as $f) {
    $dni = trim((string)($f['dni'] ?? ''));
    if ($dni === '') {
      // sin dni, tratar por ID único
      $k = 'id:'.$f['id'];
      $global[$k] = [
        'key'=>$k, 'dni'=>null, 'id_base'=>$f['id'],
        'nombre'=>trim($f['apellido'].' '.$f['nombre']),
        'escuela'=>$f['escuela'], 'logo'=>$f['escuela_logo'], 'foto'=>$f['foto'],
        'modalidad'=>$f['modalidad'], 'peso'=>$f['peso'],
        'W'=> $scoreColsPresent ? $f['W_base'] : 0,
        'L'=> $scoreColsPresent ? $f['L_base'] : 0,
        'D'=> $scoreColsPresent ? $f['D_base'] : 0,
        'NC'=> $scoreColsPresent ? $f['NC_base'] : 0,
        'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
      ];
      $mapIdToKey[$f['id']] = $k;
      continue;
    }
    if (!isset($ultimaPorDni[$dni]) || $f['id'] > $ultimaPorDni[$dni]['id']) $ultimaPorDni[$dni] = $f;
  }

  // armar base por DNI
  foreach ($ultimaPorDni as $dni => $f) {
    $k = 'dni:'.$dni;
    $global[$k] = [
      'key'=>$k, 'dni'=>$dni, 'id_base'=>$f['id'],
      'nombre'=>trim($f['apellido'].' '.$f['nombre']),
      'escuela'=>$f['escuela'], 'logo'=>$f['escuela_logo'], 'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'], 'peso'=>$f['peso'],
      'W'=> $scoreColsPresent ? $f['W_base'] : 0,
      'L'=> $scoreColsPresent ? $f['L_base'] : 0,
      'D'=> $scoreColsPresent ? $f['D_base'] : 0,
      'NC'=> $scoreColsPresent ? $f['NC_base'] : 0,
      'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $mapIdToKey[$f['id']] = $k;
  }

  // si NO existen columnas de score, como fallback sumamos peleas de TODAS las fichas del DNI
  if (!$scoreColsPresent && $peleas){
    // mapear todos los ids por DNI
    $idsPorDni = [];
    foreach ($fichas as $f) {
      $dni = trim((string)($f['dni'] ?? ''));
      if ($dni!=='') $idsPorDni[$dni][] = (int)$f['id'];
    }
    foreach ($peleas as $p){
      $g = $p['g']; if ($g===null) continue;
      foreach ($idsPorDni as $dni => $idsList){
        $k = 'dni:'.$dni;
        if (!isset($global[$k])) continue;
        $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id'];
        // si la pelea involucra cualquiera de los ids del DNI, sumamos
        if (in_array($az,$idsList,true) || in_array($ro,$idsList,true)){
          if ($g==='empate'){ $global[$k]['D']++; }
          elseif ($g==='azul'){ $global[$k]['W'] += in_array($az,$idsList,true) ? 1 : 0; $global[$k]['L'] += in_array($ro,$idsList,true) ? 1 : 0; }
          elseif ($g==='rojo'){ $global[$k]['W'] += in_array($ro,$idsList,true) ? 1 : 0; $global[$k]['L'] += in_array($az,$idsList,true) ? 1 : 0; }
        }
      }
    }
  }

} else {
  // sin DNI: por ficha (ID)
  foreach ($fichas as $f) {
    $k = 'id:'.$f['id'];
    $global[$k] = [
      'key'=>$k, 'dni'=>null, 'id_base'=>$f['id'],
      'nombre'=>trim($f['apellido'].' '.$f['nombre']),
      'escuela'=>$f['escuela'], 'logo'=>$f['escuela_logo'], 'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'], 'peso'=>$f['peso'],
      'W'=>$f['W_base'],'L'=>$f['L_base'],'D'=>$f['D_base'],'NC'=>$f['NC_base'],
      'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $mapIdToKey[$f['id']] = $k;
  }
}

/* ===== Sumar peleas del EVENTO ACTUAL (solo para la última ficha) cuando SÍ hay columnas de score ===== */
if ($scoreColsPresent && $peleas){
  foreach($peleas as $p){
    $g=$p['g']; if ($g===null) continue;
    $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id'];

    foreach ([$az,$ro] as $cid) {
      if (!isset($mapIdToKey[$cid])) continue; // solo la última ficha mapeada
      $key = $mapIdToKey[$cid];
      if (!isset($global[$key])) continue;

      if ($g==='azul' && $cid===$az) $global[$key]['W']++;
      elseif ($g==='azul' && $cid===$ro) $global[$key]['L']++;
      elseif ($g==='rojo' && $cid===$ro) $global[$key]['W']++;
      elseif ($g==='rojo' && $cid===$az) $global[$key]['L']++;
      elseif ($g==='empate') $global[$key]['D']++;
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

/* ===== Render ===== */
$phUser='assets/placeholder-user.png';
$phGym ='assets/placeholder-gym.png';
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>📊 Competidores — Global</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  body{background:#0b1115;color:#e6eef4}
  .wrap{max-width:1100px;margin:20px auto;padding:12px}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:14px}
  .row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
  input,select{padding:10px;border-radius:10px;border:1px solid #263341;background:#111a24;color:#e6eef4}
  .table-wrap{overflow-x:auto;border:1px solid #1f2a33;border-radius:12px;margin-top:12px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #1c2a36}
  th{color:#9ecbff;background:#0f1a26;position:sticky;top:0}
  .pfp{width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid #2b3c4f}
  .logo{width:40px;height:40px;object-fit:contain;background:#0b131c;border-radius:8px;border:1px solid #263341}
  .pill{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #27455c;font-size:12px;margin-right:4px}
  .muted{color:#bcd8ff}
  a.rowlink{display:flex;gap:8px;align-items:center;text-decoration:none;color:inherit}
  a.rowlink:hover{text-decoration:underline}
</style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">📊 Competidores (global por DNI)</h2>

    <?php if (!$scoreColsPresent): ?>
      <div style="margin:8px 0;padding:10px;border-radius:10px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4">
        Aviso: no se detectaron las columnas <b>wins / losses / draws / no_contest</b> en <code>competidores_evento</code>.
        Se muestran totales solo con las peleas cargadas. Si querés ver el score “cargado al registrarse”, agregá esas columnas (ver SQL en comentarios del archivo).
      </div>
    <?php endif; ?>

    <form method="get" class="row" style="margin-top:6px">
      <input type="text" name="q" placeholder="Buscar por nombre o academia…" value="<?= h($busca) ?>" style="min-width:220px">
      <label>Orden:
        <select name="sort">
          <option value="wins" <?= $orden==='wins'?'selected':''; ?>>Más ganadas</option>
          <option value="name" <?= $orden==='name'?'selected':''; ?>>Nombre</option>
          <option value="gym"  <?= $orden==='gym'?'selected':'';  ?>>Academia</option>
        </select>
      </label>
      <button style="padding:10px 14px;border-radius:10px;border:1px solid #27455c;background:#0e7ad1;color:#fff;cursor:pointer">Aplicar</button>
    </form>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Competidor</th>
            <th>Academia</th>
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
          <tr><td colspan="8" class="muted">Sin registros.</td></tr>
        <?php else: foreach($lista as $c):
          $nombre = trim($c['nombre']) ?: '—';
          $foto = $c['foto'] ?: $phUser;
          $logo = $c['logo'] ?: $phGym;

          // URL al perfil individual (prioriza DNI si existe)
          $perfilUrl = !empty($c['dni'])
            ? 'ver_competidor_ranking.php?dni='.urlencode($c['dni'])
            : 'ver_competidor_ranking.php?id='.(int)$c['id_base'];
        ?>
          <tr>
            <td>
              <a class="rowlink" href="<?= h($perfilUrl) ?>">
                <img class="pfp" src="<?= h($foto) ?>" alt="foto">
                <div>
                  <div style="font-weight:800"><?= h($nombre) ?><?= h($c['badge'] ?? '') ?></div>
                  <?php if (!empty($c['dni'])): ?>
                    <div class="muted" style="font-size:12px">DNI: <?= h($c['dni']) ?> • Ficha ID base: <?= (int)$c['id_base'] ?></div>
                  <?php else: ?>
                    <div class="muted" style="font-size:12px">Ficha ID: <?= (int)$c['id_base'] ?></div>
                  <?php endif; ?>
                </div>
              </a>
            </td>
            <td>
              <div class="row" style="gap:8px;align-items:center">
                <img class="logo" src="<?= h($logo) ?>" alt="logo">
                <div><?= h($c['escuela'] ?: '—') ?></div>
              </div>
            </td>
            <td><?= h($c['modalidad'] ?: '—') ?></td>
            <td><?= h($c['peso'] ?: '—') ?></td>
            <td><span class="pill" style="border-color:#1d6f3a"><?= (int)$c['W'] ?></span></td>
            <td><span class="pill" style="border-color:#6f1d1d"><?= (int)$c['L'] ?></span></td>
            <td><span class="pill" style="border-color:#6f5a1d"><?= (int)$c['D'] ?></span></td>
            <td><span class="pill" style="border-color:#3a3f50"><?= (int)$c['NC'] ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="muted" style="margin-top:8px">
      • Con columnas de score: Base = score cargado en la <b>última ficha</b> de cada DNI + peleas con resultado de ese evento.<br>
      • Sin columnas de score: se muestran totales sólo según peleas cargadas (todas las fichas del DNI).
    </div>
  </div>
</div>
</body>
</html>
