<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function pick_col(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }
function has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $q=$db->query("SHOW TABLES LIKE '$t'"); $ok=$q&&$q->num_rows>0; if($q)$q->close(); return $ok; }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $r=$db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  $ok=$r&&$r->num_rows>0; if($r)$r->close(); return $ok;
}

/* ===== Parámetros ===== */
$comp_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$dni_in  = isset($_GET['dni']) ? trim((string)$_GET['dni']) : null;
if (!$comp_id && !$dni_in) { http_response_code(400); exit('❌ Falta id o dni'); }

/* ===== Detectar columnas ===== */
/* competidores_evento */
$colsCe=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){ while($r=$q->fetch_assoc()) $colsCe[strtolower($r['Field'])]=$r['Field']; $q->close(); }
$CE_ID       = pick_col(['id','competidor_id'],$colsCe);
$CE_DNI      = pick_col(['dni','documento','doc'],$colsCe);
$CE_NOMBRE   = pick_col(['nombre'],$colsCe);
$CE_APELLIDO = pick_col(['apellido'],$colsCe);
$CE_ESC_NOM  = pick_col(['escuela_nombre','academia','gimnasio','equipo'],$colsCe);
$CE_ESC_LOGO = pick_col(['escuela_logo','logo_escuela','logo_academia'],$colsCe);
$CE_FOTO     = pick_col(['foto_competidor','foto','avatar'],$colsCe);
$CE_MODAL_ID = pick_col(['modalidad_id'],$colsCe);
$CE_PESO_ID  = pick_col(['categoria_peso_id','peso_id'],$colsCe);
$CE_WINS     = pick_col(['wins','ganadas','w'],$colsCe);
$CE_LOSSES   = pick_col(['losses','perdidas','l'],$colsCe);
$CE_DRAWS    = pick_col(['draws','empates','d'],$colsCe);
$CE_NC       = pick_col(['no_contest','nc','sin_decision'],$colsCe);
if (!$CE_ID) { http_response_code(500); exit('❌ No se detectó columna ID en competidores_evento'); }

/* catálogos (opcionales) */
$hasMod = has_table($conexion,'modalidades_evento');
$hasPeso= has_table($conexion,'categorias_peso_evento');

/* peleas_evento */
$colsPe=[]; if ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`")){ while($r=$q->fetch_assoc()) $colsPe[strtolower($r['Field'])]=$r['Field']; $q->close(); }
$PE_ID    = pick_col(['id','pelea_id','id_pelea'],$colsPe) ?: 'id';
$PE_ROJO  = pick_col(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'],$colsPe);
$PE_AZUL  = pick_col(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'],$colsPe);
$PE_EVENTO= pick_col(['evento_id','id_evento','evento'],$colsPe);
$PE_FECHA = pick_col(['fecha','fecha_pelea','fpelea','created_at','creado_en'],$colsPe);
$PE_GAN   = pick_col(['ganador','resultado','winner'],$colsPe);
if (!$PE_ROJO || !$PE_AZUL) { $PE_ROJO=null; $PE_AZUL=null; } // historial solo si existen

/* resultados_jueces (opcional) */
$useRJ = has_table($conexion,'resultados_jueces') && has_col($conexion,'resultados_jueces','pelea_id') && has_col($conexion,'resultados_jueces','ganador');

/* ===== Resolver conjunto de IDs (todas las fichas del DNI) ===== */
$ids = [];
$perfil = [
  'dni'=>null,'id_base'=>null,'nombre'=>'','escuela'=>'','logo'=>'','foto'=>'',
  'modalidad'=>null,'peso'=>null,'W'=>0,'L'=>0,'D'=>0,'NC'=>0
];

if ($dni_in) {
  $dni = $dni_in;
  $sql = "SELECT c.".bt($CE_ID)." id,
                 ".($CE_DNI     ? "c.".bt($CE_DNI)." dni" : "NULL dni").",
                 ".($CE_APELLIDO? "c.".bt($CE_APELLIDO)." ape" : "'' ape").",
                 ".($CE_NOMBRE  ? "c.".bt($CE_NOMBRE)." nom" : "'' nom").",
                 ".($CE_ESC_NOM ? "c.".bt($CE_ESC_NOM)." esc" : "'' esc").",
                 ".($CE_ESC_LOGO? "c.".bt($CE_ESC_LOGO)." logo": "'' logo").",
                 ".($CE_FOTO    ? "c.".bt($CE_FOTO)." foto": "'' foto").",
                 ".($CE_WINS    ? "CAST(c.".bt($CE_WINS)." AS SIGNED)" : "0")." Wb,
                 ".($CE_LOSSES  ? "CAST(c.".bt($CE_LOSSES)." AS SIGNED)" : "0")." Lb,
                 ".($CE_DRAWS   ? "CAST(c.".bt($CE_DRAWS)." AS SIGNED)" : "0")." Db,
                 ".($CE_NC      ? "CAST(c.".bt($CE_NC)." AS SIGNED)" : "0")." NCb,
                 ".($CE_MODAL_ID? "c.".bt($CE_MODAL_ID) : "NULL")." mod_id,
                 ".($CE_PESO_ID ? "c.".bt($CE_PESO_ID)  : "NULL")." peso_id
          FROM competidores_evento c
          WHERE ".bt($CE_DNI)." = ?";
  $st=$conexion->prepare($sql); $st->bind_param('s',$dni); $st->execute(); $rs=$st->get_result();
  $fichas=[]; while($r=$rs->fetch_assoc()){ $fichas[]=$r; $ids[]=(int)$r['id']; }
  $st->close();
  if (!$ids) { exit('No se encontraron fichas con ese DNI.'); }
  usort($fichas,fn($a,$b)=>((int)$a['id'])<=>((int)$b['id']));
  $base = end($fichas);
  $perfil['dni']=$dni;
  $perfil['id_base']=(int)$base['id'];
  $perfil['nombre']=trim(($base['ape']??'').' '.($base['nom']??'')) ?: '—';
  $perfil['escuela']=$base['esc']??'';
  $perfil['logo']=$base['logo']??'';
  $perfil['foto']=$base['foto']??'';
  $perfil['W']+=(int)$base['Wb']; $perfil['L']+=(int)$base['Lb']; $perfil['D']+=(int)$base['Db']; $perfil['NC']+=(int)$base['NCb'];
  $mod_id = $base['mod_id'] ?? null;
  $peso_id= $base['peso_id'] ?? null;
} else {
  $id = $comp_id;
  $sql = "SELECT c.".bt($CE_ID)." id,
                 ".($CE_DNI     ? "c.".bt($CE_DNI)." dni" : "NULL dni").",
                 ".($CE_APELLIDO? "c.".bt($CE_APELLIDO)." ape" : "'' ape").",
                 ".($CE_NOMBRE  ? "c.".bt($CE_NOMBRE)." nom" : "'' nom").",
                 ".($CE_ESC_NOM ? "c.".bt($CE_ESC_NOM)." esc" : "'' esc").",
                 ".($CE_ESC_LOGO? "c.".bt($CE_ESC_LOGO)." logo": "'' logo").",
                 ".($CE_FOTO    ? "c.".bt($CE_FOTO)." foto": "'' foto").",
                 ".($CE_WINS    ? "CAST(c.".bt($CE_WINS)." AS SIGNED)" : "0")." Wb,
                 ".($CE_LOSSES  ? "CAST(c.".bt($CE_LOSSES)." AS SIGNED)" : "0")." Lb,
                 ".($CE_DRAWS   ? "CAST(c.".bt($CE_DRAWS)." AS SIGNED)" : "0")." Db,
                 ".($CE_NC      ? "CAST(c.".bt($CE_NC)." AS SIGNED)" : "0")." NCb,
                 ".($CE_MODAL_ID? "c.".bt($CE_MODAL_ID) : "NULL")." mod_id,
                 ".($CE_PESO_ID ? "c.".bt($CE_PESO_ID)  : "NULL")." peso_id
          FROM competidores_evento c WHERE c.".bt($CE_ID)." = ?";
  $st=$conexion->prepare($sql); $st->bind_param('i',$id); $st->execute(); $base=$st->get_result()->fetch_assoc(); $st->close();
  if (!$base) { exit('Competidor no encontrado.'); }
  $perfil['dni'] = $base['dni'] ?? null;
  if ($perfil['dni']) {
    // incluir todas las fichas del DNI
    $dni = $perfil['dni'];
    $st=$conexion->prepare("SELECT ".bt($CE_ID)." id FROM competidores_evento WHERE ".bt($CE_DNI)." = ?"); $st->bind_param('s',$dni); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()){ $ids[]=(int)$r['id']; }
    $st->close();
  } else {
    $ids[]=(int)$base['id'];
  }
  $perfil['id_base']=(int)$base['id'];
  $perfil['nombre']=trim(($base['ape']??'').' '.($base['nom']??'')) ?: '—';
  $perfil['escuela']=$base['esc']??'';
  $perfil['logo']=$base['logo']??'';
  $perfil['foto']=$base['foto']??'';
  $perfil['W']+=(int)$base['Wb']; $perfil['L']+=(int)$base['Lb']; $perfil['D']+=(int)$base['Db']; $perfil['NC']+=(int)$base['NCb'];
  $mod_id = $base['mod_id'] ?? null;
  $peso_id= $base['peso_id'] ?? null;
}

/* etiquetas opcionales de modalidad y peso */
$perfil['modalidad'] = null; $perfil['peso'] = null;
if ($hasMod && $mod_id) { $r=$conexion->query("SELECT nombre FROM modalidades_evento WHERE id=".(int)$mod_id); if($r){ $perfil['modalidad']=$r->fetch_row()[0]??null; $r->close(); } }
if ($hasPeso && $peso_id){ $r=$conexion->query("SELECT nombre FROM categorias_peso_evento WHERE id=".(int)$peso_id); if($r){ $perfil['peso']=$r->fetch_row()[0]??null; $r->close(); } }

/* ===== Historial de peleas ===== */
$historial = [];
if ($ids && $PE_ROJO && $PE_AZUL) {
  $in = implode(',', array_map('intval',$ids));
  $cols = "p.".bt($PE_ID)." pelea_id,
           p.".bt($PE_ROJO)." rojo_id,
           p.".bt($PE_AZUL)." azul_id".
           ($PE_FECHA ? ", p.".bt($PE_FECHA)." fecha" : ", NULL fecha").
           ($PE_EVENTO? ", p.".bt($PE_EVENTO)." evento_id" : ", NULL evento_id").
           ($PE_GAN   ? ", p.".bt($PE_GAN)." ganador" : ", NULL ganador").",
           cr.apellido r_ape, cr.nombre r_nom, cr.escuela_nombre r_esc, cr.foto_competidor r_foto,
           ca.apellido a_ape, ca.nombre a_nom, ca.escuela_nombre a_esc, ca.foto_competidor a_foto,
           mr.nombre r_mod, ma.nombre a_mod";
  $sql = "SELECT $cols
          FROM peleas_evento p
          JOIN competidores_evento cr ON p.".bt($PE_ROJO)." = cr.".bt($CE_ID)."
          JOIN competidores_evento ca ON p.".bt($PE_AZUL)." = ca.".bt($CE_ID)."
          LEFT JOIN modalidades_evento mr ON mr.id = cr.".bt($CE_MODAL_ID)."
          LEFT JOIN modalidades_evento ma ON ma.id = ca.".bt($CE_MODAL_ID)."
          WHERE p.".bt($PE_ROJO)." IN ($in) OR p.".bt($PE_AZUL)." IN ($in)";
  if ($r=$conexion->query($sql)){
    while($row=$r->fetch_assoc()){
      $historial[]=$row;
    }
    $r->close();
  }

  /* Determinar ganador por mayoría si no hay ganador explícito */
  $mayoria = [];
  if ($useRJ && $historial){
    $idsP = array_map(fn($x)=>(int)$x['pelea_id'],$historial);
    $inP  = implode(',', $idsP);
    $rq = $conexion->query("SELECT pelea_id,
      SUM(ganador='azul') az, SUM(ganador='rojo') ro, SUM(ganador='empate') em
      FROM resultados_jueces
      WHERE pelea_id IN ($inP) AND (estado IS NULL OR estado='enviado')
      GROUP BY pelea_id");
    if ($rq){ while($z=$rq->fetch_assoc()){
      $g=null;
      if ($z['az']>$z['ro'] && $z['az']>$z['em']) $g='azul';
      elseif ($z['ro']>$z['az'] && $z['ro']>$z['em']) $g='rojo';
      elseif ($z['em']>$z['az'] && $z['em']>$z['ro']) $g='empate';
      $mayoria[(int)$z['pelea_id']]=$g;
    } $rq->close(); }
  }

  /* Normalizar cada pelea: fecha, rival, esquina, modalidad, resultado relativo */
  foreach ($historial as &$p) {
    $yo_rojo = in_array((int)$p['rojo_id'],$ids,true);
    $yo_azul = in_array((int)$p['azul_id'],$ids,true);
    $p['_lado'] = $yo_rojo ? 'Rojo' : ($yo_azul ? 'Azul' : '?');

    $p['_mi_nombre'] = $yo_rojo ? trim(($p['r_ape']??'').' '.($p['r_nom']??'')) : trim(($p['a_ape']??'').' '.($p['a_nom']??''));
    $p['_rv_nombre'] = $yo_rojo ? trim(($p['a_ape']??'').' '.($p['a_nom']??'')) : trim(($p['r_ape']??'').' '.($p['r_nom']??''));
    $p['_rv_esc']    = $yo_rojo ? ($p['a_esc']??'') : ($p['r_esc']??'');
    $p['_rv_foto']   = $yo_rojo ? ($p['a_foto']??'') : ($p['r_foto']??'');

    $mod_r = $p['r_mod'] ?? ''; $mod_a = $p['a_mod'] ?? '';
    $p['_modalidad'] = $yo_rojo ? $mod_r : ($mod_a ?: $mod_r); // si riv tiene, usar la propia; sino cualquiera

    $g = isset($p['ganador']) && $p['ganador']!=='' ? strtolower(trim((string)$p['ganador'])) : null;
    if (!$g && isset($mayoria[(int)$p['pelea_id']])) $g = $mayoria[(int)$p['pelea_id']];

    if ($g==='empate'){ $p['_res']='Empate'; $perfil['D']++; }
    elseif ($g==='azul' || $g==='rojo'){
      $yo = $yo_azul ? 'azul' : ($yo_rojo ? 'rojo' : null);
      if ($yo && $g===$yo){ $p['_res']='Victoria'; $perfil['W']++; }
      else { $p['_res']='Derrota'; $perfil['L']++; }
    } else { $p['_res']='—'; }
  } unset($p);

  /* Orden por fecha asc (nulls al final), luego id asc */
  usort($historial,function($A,$B) use($PE_ID){
    $fa = $A['fecha'] ?? null; $fb = $B['fecha'] ?? null;
    if ($fa && $fb) return strcmp($fa,$fb);
    if ($fa && !$fb) return -1;
    if (!$fa && $fb) return 1;
    return ((int)$A['pelea_id']) <=> ((int)$B['pelea_id']);
  });
}

/* ===== Render ===== */
$phUser='assets/placeholder-user.png';
$phGym ='assets/placeholder-gym.png';
$foto = $perfil['foto'] ?: $phUser;
$logo = $perfil['logo'] ?: $phGym;
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>🥇 Ver Competidor (Ranking)</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
  body{background:#0b1115;color:#e6eef4}
  .wrap{max-width:1100px;margin:20px auto;padding:12px}
  .head{display:grid;grid-template-columns:120px 1fr 160px;gap:14px;align-items:center}
  .pfp{width:110px;height:110px;object-fit:cover;border-radius:14px;border:1px solid #2b3c4f;background:#0f1720}
  .logo{width:110px;height:110px;object-fit:contain;border-radius:14px;border:1px solid #2b3c4f;background:#0f1720}
  .card{background:#0f1720;border:1px solid #1f2a33;border-radius:14px;padding:14px;margin-top:12px}
  .pill{display:inline-block;padding:4px 10px;border-radius:999px;border:1px solid #27455c;font-weight:700}
  .table-wrap{overflow-x:auto;border:1px solid #1f2a33;border-radius:12px;margin-top:12px}
  table{width:100%;border-collapse:collapse}
  th,td{padding:10px;border-bottom:1px solid #1c2a36}
  th{color:#9ecbff;background:#0f1a26;position:sticky;top:0}
  .muted{color:#bcd8ff}
  .badge{font-size:12px;color:#9ecbff}
</style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <div class="card">
    <div class="head">
      <img class="pfp" src="<?= h($foto) ?>" alt="Competidor">
      <div>
        <h2 style="margin:0 0 6px 0"><?= h($perfil['nombre']) ?></h2>
        <?php if (!empty($perfil['dni'])): ?>
          <div class="badge">DNI: <?= h($perfil['dni']) ?> • Ficha base: <?= (int)$perfil['id_base'] ?></div>
        <?php else: ?>
          <div class="badge">Ficha base: <?= (int)$perfil['id_base'] ?></div>
        <?php endif; ?>
        <div style="margin-top:8px">
          <span class="pill">W: <?= (int)$perfil['W'] ?></span>
          <span class="pill" style="margin-left:6px">L: <?= (int)$perfil['L'] ?></span>
          <span class="pill" style="margin-left:6px">D: <?= (int)$perfil['D'] ?></span>
          <span class="pill" style="margin-left:6px">NC: <?= (int)$perfil['NC'] ?></span>
        </div>
        <div class="muted" style="margin-top:8px">
          <?= $perfil['modalidad'] ? 'Modalidad: '.h($perfil['modalidad']).' • ' : '' ?>
          <?= $perfil['peso'] ? 'Peso: '.h($perfil['peso']) : '' ?>
        </div>
      </div>
      <img class="logo" src="<?= h($logo) ?>" alt="Escuela">
    </div>
  </div>

  <div class="card">
    <h3 style="margin:0 0 8px 0">📜 Historial de peleas</h3>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Evento</th>
            <th>Esquina</th>
            <th>Rival</th>
            <th>Escuela rival</th>
            <th>Modalidad</th>
            <th>Resultado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$historial): ?>
            <tr><td colspan="7" class="muted">Sin peleas cargadas.</td></tr>
          <?php else: foreach ($historial as $p):
            $f = $p['fecha'] ? h($p['fecha']) : '—';
            $evento = isset($p['evento_id']) ? ('#'.(int)$p['evento_id']) : '—';
            $rvFoto = $p['_rv_foto'] ?: $phUser;
          ?>
            <tr>
              <td><?= $f ?></td>
              <td><?= h($evento) ?></td>
              <td><?= h($p['_lado']) ?></td>
              <td>
                <div style="display:flex;gap:8px;align-items:center">
                  <img src="<?= h($rvFoto) ?>" alt="rival" style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid #2b3c4f">
                  <div><?= h($p['_rv_nombre'] ?: '—') ?></div>
                </div>
              </td>
              <td><?= h($p['_rv_esc'] ?: '—') ?></td>
              <td><?= h($p['_modalidad'] ?: '—') ?></td>
              <td><?= h($p['_res']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="muted" style="margin-top:8px">
      * El resultado usa <code>peleas_evento.ganador</code> si está cargado; si no, se calcula por mayoría desde <code>resultados_jueces</code> (si existe).  
      * El récord mostrado combina el score base de la ficha y las peleas listadas aquí.
    </div>
  </div>
</div>
</body>
</html>
