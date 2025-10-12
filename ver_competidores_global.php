<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $q=$db->query("SHOW TABLES LIKE '$t'"); $ok=$q&&$q->num_rows>0; if($q)$q->close(); return $ok; }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $r=$db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  $ok=$r&&$r->num_rows>0; if($r)$r->close(); return $ok;
}
function pick_col(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }

/* Ruta base absoluta por carpeta (evita que los href “salten” de carpeta) */
$SELF_DIR = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$BASE = ($SELF_DIR === '' || $SELF_DIR === '.') ? '' : $SELF_DIR;

/* ===== Tablas mínimas ===== */
if (!has_table($conexion,'competidores_evento')) { exit('❌ Falta la tabla requerida: competidores_evento'); }
$hasPeleas = has_table($conexion,'peleas_evento');

/* ===== Columnas dinámicas ===== */
$colsPe=[]; if ($hasPeleas && ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`"))){ while($r=$q->fetch_assoc()) $colsPe[strtolower($r['Field'])]=$r['Field']; $q->close(); }
$C_AZUL   = $hasPeleas ? pick_col(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'], $colsPe) : null;
$C_ROJO   = $hasPeleas ? pick_col(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'], $colsPe) : null;
$C_EVENTO = $hasPeleas ? pick_col(['evento_id','id_evento','evento'], $colsPe) : null;
$C_FECHA  = $hasPeleas ? pick_col(['fecha','fecha_pelea','fpelea','created_at'], $colsPe) : null;
$C_GANADOR= $hasPeleas ? pick_col(['ganador','resultado','winner'], $colsPe) : null;

/* competidores_evento */
$colsCe=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){ while($r=$q->fetch_assoc()) $colsCe[strtolower($r['Field'])]=$r['Field']; $q->close(); }
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
$C_WINS      = pick_col(['wins','win','w','ganadas','ganada'], $colsCe);
$C_LOSSES    = pick_col(['losses','loss','l','perdidas','perdida'], $colsCe);
$C_DRAWS     = pick_col(['draws','draw','d','empates','empate'], $colsCe);
$C_NC        = pick_col(['no_contest','nocontest','nc','no_decision','no-decision','sin_decision','sin-decision'], $colsCe);

if (!$C_ID) { exit('❌ No se detectó columna ID en competidores_evento.'); }
$scoreColsPresent = (bool)($C_WINS && $C_LOSSES && $C_DRAWS && $C_NC);

/* ===== Mayoria (opcional) ===== */
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

/* ===== Peleas (para sumar) ===== */
$peleas=[];
if ($hasPeleas && $C_AZUL && $C_ROJO) {
  $cols="p.id pelea_id, p.".bt($C_AZUL)." azul_id, p.".bt($C_ROJO)." rojo_id";
  if ($C_FECHA)  $cols.=", p.".bt($C_FECHA)." f";
  if ($C_EVENTO) $cols.=", p.".bt($C_EVENTO)." evento_id";
  if ($C_GANADOR)$cols.=", p.".bt($C_GANADOR)." ganador";
  if ($r=$conexion->query("SELECT $cols FROM peleas_evento p")){
    while($row=$r->fetch_assoc()){
      $row['pelea_id']=(int)$row['pelea_id']; $row['azul_id']=(int)$row['azul_id']; $row['rojo_id']=(int)$row['rojo_id'];
      $g = $winnerByFight[$row['pelea_id']] ?? null;
      if ($g===null && isset($row['ganador'])) {
        $gg = strtolower(trim((string)$row['ganador']));
        if (in_array($gg,['azul','rojo','empate'],true)) $g = $gg;
      }
      $row['g']=$g; $peleas[]=$row;
    }
    $r->close();
  }
}

/* ===== Fichas ===== */
$sel = "c.".bt($C_ID)." id";
$sel.= $C_DNI     ? ", c.".bt($C_DNI)." dni" : ", NULL dni";
$sel.= $C_APELLIDO? ", c.".bt($C_APELLIDO)." ape" : ", '' ape";
$sel.= $C_NOMBRE  ? ", c.".bt($C_NOMBRE)." nom"   : ", '' nom";
$sel.= $C_ESC_NOM ? ", c.".bt($C_ESC_NOM)." esc"  : ", '' esc";
$sel.= $C_ESC_LOGO? ", c.".bt($C_ESC_LOGO)." logo": ", '' logo";
$sel.= $C_FOTO    ? ", c.".bt($C_FOTO)." foto"    : ", '' foto";
$sel.= $C_MODAL_ID? ", c.".bt($C_MODAL_ID)." mod" : ", NULL mod";
$sel.= $C_PESO_ID ? ", c.".bt($C_PESO_ID)." peso" : ", NULL peso";
if ($scoreColsPresent){
  $sel.= ", CAST(c.".bt($C_WINS)." AS SIGNED) w";
  $sel.= ", CAST(c.".bt($C_LOSSES)." AS SIGNED) l";
  $sel.= ", CAST(c.".bt($C_DRAWS)." AS SIGNED) d";
  $sel.= ", CAST(c.".bt($C_NC)." AS SIGNED) nc";
}
if ($C_ACTIVO) $sel.=", c.".bt($C_ACTIVO)." activo";
if ($C_ESTADO) $sel.=", c.".bt($C_ESTADO)." estado";

$joins=""; $selExtra="";
if (has_table($conexion,'modalidades_evento'))     { $joins.=" LEFT JOIN modalidades_evento mo ON mo.id = c.".bt($C_MODAL_ID);  $selExtra.=", mo.nombre modalidad"; }
else { $selExtra.=", NULL modalidad"; }
if (has_table($conexion,'categorias_peso_evento')) { $joins.=" LEFT JOIN categorias_peso_evento cp ON cp.id = c.".bt($C_PESO_ID); $selExtra.=", cp.nombre peso_txt"; }
else { $selExtra.=", NULL peso_txt"; }

$fichas=[];
if ($r=$conexion->query("SELECT $sel $selExtra FROM competidores_evento c $joins ORDER BY c.".bt($C_ID)." ASC")){
  while($row=$r->fetch_assoc()){
    $id=(int)$row['id'];
    $fichas[$id]=[
      'id'=>$id,
      'dni'=>$row['dni'] ?? null,
      'apellido'=>$row['ape'] ?? '',
      'nombre'=>$row['nom'] ?? '',
      'escuela'=>$row['esc'] ?? '',
      'escuela_logo'=>$row['logo'] ?? '',
      'foto'=>$row['foto'] ?? '',
      'modalidad'=>$row['modalidad'] ?? '',
      'peso'=>$row['peso_txt'] ?? '',
      'W_base'=> (int)($row['w'] ?? 0),
      'L_base'=> (int)($row['l'] ?? 0),
      'D_base'=> (int)($row['d'] ?? 0),
      'NC_base'=> (int)($row['nc'] ?? 0),
      'activo'=>$row['activo'] ?? null,
      'estado'=>$row['estado'] ?? null,
    ];
  }
  $r->close();
}

/* ===== Unificación ===== */
$scoreColsPresent = (bool)($C_WINS && $C_LOSSES && $C_DRAWS && $C_NC);
$usarDNI = (bool)$C_DNI;
$global = []; $mapIdToKey = [];

$normName = function($a,$n){
  $s = trim(mb_strtolower($a.' '.$n,'UTF-8'));
  $s = preg_replace('~\s+~',' ',$s);
  $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o','ü'=>'u','ñ'=>'n']);
  return $s;
};

if ($usarDNI) {
  $ultimaPorDni = [];
  $sinDniPorNombre = [];
  foreach ($fichas as $f) {
    $dni = trim((string)($f['dni'] ?? ''));
    if ($dni !== '') {
      if (!isset($ultimaPorDni[$dni]) || $f['id'] > $ultimaPorDni[$dni]['id']) $ultimaPorDni[$dni] = $f;
    } else {
      $key = $normName($f['apellido'],$f['nombre']);
      if (!isset($sinDniPorNombre[$key]) || $f['id'] > $sinDniPorNombre[$key]['id']) $sinDniPorNombre[$key] = $f;
    }
  }
  foreach ($ultimaPorDni as $dni => $f) {
    $k='dni:'.$dni;
    $global[$k]=[
      'key'=>$k,'dni'=>$dni,'id_base'=>$f['id'],
      'nombre'=>trim($f['apellido'].' '.$f['nombre']),
      'escuela'=>$f['escuela'],'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W'=>$scoreColsPresent?$f['W_base']:0,'L'=>$scoreColsPresent?$f['L_base']:0,'D'=>$scoreColsPresent?$f['D_base']:0,'NC'=>$scoreColsPresent?$f['NC_base']:0,
      'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $mapIdToKey[$f['id']]=$k;
  }
  foreach ($sinDniPorNombre as $key => $f) {
    $k='name:'.$key;
    $global[$k]=[
      'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
      'nombre'=>trim($f['apellido'].' '.$f['nombre']),
      'escuela'=>$f['escuela'],'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W'=>$scoreColsPresent?$f['W_base']:0,'L'=>$scoreColsPresent?$f['L_base']:0,'D'=>$scoreColsPresent?$f['D_base']:0,'NC'=>$scoreColsPresent?$f['NC_base']:0,
      'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $mapIdToKey[$f['id']]=$k;
  }
} else {
  $porNombre=[];
  foreach ($fichas as $f) {
    $key=$normName($f['apellido'],$f['nombre']);
    if (!isset($porNombre[$key]) || $f['id']>$porNombre[$key]['id']) $porNombre[$key]=$f;
  }
  foreach ($porNombre as $key=>$f) {
    $k='name:'.$key;
    $global[$k]=[
      'key'=>$k,'dni'=>null,'id_base'=>$f['id'],
      'nombre'=>trim($f['apellido'].' '.$f['nombre']),
      'escuela'=>$f['escuela'],'logo'=>$f['escuela_logo'],'foto'=>$f['foto'],
      'modalidad'=>$f['modalidad'],'peso'=>$f['peso'],
      'W'=>$f['W_base'],'L'=>$f['L_base'],'D'=>$f['D_base'],'NC'=>$f['NC_base'],
      'badge'=> (isset($f['activo']) && $f['activo']!=='' && (int)$f['activo']===0) ? ' (archivado)' : ((isset($f['estado']) && $f['estado'])? (' ('.$f['estado'].')') : '')
    ];
    $mapIdToKey[$f['id']]=$k;
  }
}

/* ===== Sumar peleas (si hay columnas) ===== */
if ($scoreColsPresent && $peleas){
  foreach($peleas as $p){
    $g=$p['g']; if ($g===null) continue;
    $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id'];
    foreach([$az,$ro] as $cid){
      if (!isset($mapIdToKey[$cid])) continue;
      $key=$mapIdToKey[$cid]; if(!isset($global[$key])) continue;
      if     ($g==='azul' && $cid===$az) $global[$key]['W']++;
      elseif ($g==='azul' && $cid===$ro) $global[$key]['L']++;
      elseif ($g==='rojo' && $cid===$ro) $global[$key]['W']++;
      elseif ($g==='rojo' && $cid===$az) $global[$key]['L']++;
      elseif ($g==='empate')            $global[$key]['D']++;
    }
  }
}

/* ===== Filtros/Orden ===== */
$busca = trim((string)($_GET['q'] ?? ''));
$orden = (string)($_GET['sort'] ?? 'wins');
$lista = array_values($global);

if ($busca!==''){
  $q = mb_strtolower($busca,'UTF-8');
  $lista = array_values(array_filter($lista,function($c) use($q){
    $s = mb_strtolower(($c['nombre'].' '.$c['escuela']), 'UTF-8');
    return strpos($s,$q)!==false;
  }));
}
usort($lista,function($a,$b) use($orden){
  if ($orden==='name') return strnatcasecmp($a['nombre'],$b['nombre']);
  if ($orden==='gym')  return strnatcasecmp($a['escuela'] ?? '', $b['escuela'] ?? '');
  $da = $b['W'] <=> $a['W']; if ($da) return $da;
  $db = ($a['L'] <=> $b['L']); if ($db) return $db;
  return strnatcasecmp($a['nombre'],$b['nombre']);
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
  <link rel="stylesheet" href="<?= h($BASE) ?>/estilo_unificado.css">
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <div class="page-card">
    <div class="encabezado" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <h2 style="margin:0">📊 Competidores (global unificado)</h2>
      <a class="btn" href="<?= h($BASE) ?>/index.php">Volver</a>
    </div>

    <form method="get" class="toolbar" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px">
      <input class="input" type="text" name="q" placeholder="Buscar por nombre o academia…" value="<?= h($busca) ?>" style="min-width:220px">
      <select class="input" name="sort">
        <option value="wins" <?= $orden==='wins'?'selected':''; ?>>Más ganadas</option>
        <option value="name" <?= $orden==='name'?'selected':''; ?>>Nombre</option>
        <option value="gym"  <?= $orden==='gym'?'selected':'';  ?>>Academia</option>
      </select>
      <button class="btn">Aplicar</button>
    </form>

    <div class="table-wrap">
      <table aria-label="Listado global de competidores">
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
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$lista): ?>
          <tr><td colspan="9" class="muted" style="text-align:center">Sin registros.</td></tr>
        <?php else: foreach($lista as $c):
          $nombre = trim($c['nombre']) ?: '—';
          $foto = $c['foto'] ?: $phUser;
          $logo = $c['logo'] ?: $phGym;

          /* Armado de URL DETALLE robusto */
          $perfilUrl = '';
          if (!empty($c['dni']) && preg_match('/^\d{7,10}$/', (string)$c['dni'])) {
            $perfilUrl = $BASE.'/ver_competidor_ranking.php?dni='.urlencode($c['dni']);
          } elseif (!empty($c['id_base']) && (int)$c['id_base']>0) {
            $perfilUrl = $BASE.'/ver_competidor_ranking.php?id='.(int)$c['id_base'];
          }

          $editUrl   = $BASE.'/editar_competidor_evento.php?id='.(int)$c['id_base'];
          $deleteUrl = $BASE.'/eliminar_competidor_evento.php?id='.(int)$c['id_base'];
          $publicUrl = !empty($c['dni']) && preg_match('/^\d{7,10}$/',(string)$c['dni'])
            ? $BASE.'/ver_competidor_publico.php?dni='.urlencode($c['dni'])
            : $BASE.'/ver_competidor_publico.php?id='.(int)$c['id_base'];
        ?>
          <tr>
            <td style="text-align:left">
              <?php if ($perfilUrl): ?>
                <a class="rowlink" href="<?= h($perfilUrl) ?>" style="display:flex;gap:10px;align-items:center;color:inherit;text-decoration:none">
                  <img class="pfp" src="<?= h($foto) ?>" alt="foto" style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid var(--stroke);background:#0b0d12">
                  <div>
                    <div style="font-weight:800"><?= h($nombre) ?><?= h($c['badge'] ?? '') ?></div>
                    <?php if (!empty($c['dni'])): ?>
                      <div class="muted" style="font-size:12px">DNI: <?= h($c['dni']) ?> • Ficha base: <?= (int)$c['id_base'] ?></div>
                    <?php else: ?>
                      <div class="muted" style="font-size:12px">Ficha ID: <?= (int)$c['id_base'] ?></div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php else: ?>
                <div style="display:flex;gap:10px;align-items:center">
                  <img class="pfp" src="<?= h($foto) ?>" alt="foto" style="width:52px;height:52px;object-fit:cover;border-radius:10px;border:1px solid var(--stroke);background:#0b0d12">
                  <div>
                    <div style="font-weight:800"><?= h($nombre) ?><?= h($c['badge'] ?? '') ?></div>
                    <div class="muted" style="font-size:12px">Sin identificador válido</div>
                  </div>
                </div>
              <?php endif; ?>
            </td>
            <td style="text-align:left">
              <div style="display:flex;gap:8px;align-items:center">
                <img class="logo" src="<?= h($logo) ?>" alt="logo" style="width:40px;height:40px;object-fit:contain;border-radius:8px;border:1px solid var(--stroke);background:#0b0d12">
                <div><?= h($c['escuela'] ?: '—') ?></div>
              </div>
            </td>
            <td><?= h($c['modalidad'] ?: '—') ?></td>
            <td><?= h($c['peso'] ?: '—') ?></td>
            <td><span class="pill"><?= (int)$c['W'] ?></span></td>
            <td><span class="pill"><?= (int)$c['L'] ?></span></td>
            <td><span class="pill"><?= (int)$c['D'] ?></span></td>
            <td><span class="pill"><?= (int)$c['NC'] ?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <a class="btn" href="<?= h($editUrl) ?>">✏️ Editar</a>
                <a class="btn btn-danger" href="<?= h($deleteUrl) ?>" onclick="return confirm('¿Eliminar esta ficha del evento?');">🗑️</a>
                <a class="btn" href="<?= h($publicUrl) ?>" target="_blank" title="Vista pública solo lectura">🔗</a>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <p class="muted" style="margin-top:8px">
      • Sin DNI válido: se usa <b>Nombre+Apellido</b> para unificar, pero el detalle siempre requerirá <b>DNI</b> o <b>ID</b> base.  
      • Los links usan la carpeta base “<?= h($BASE?:'/') ?>” para evitar redirecciones indeseadas.
    </p>
  </div>
</div>
</body>
</html>
