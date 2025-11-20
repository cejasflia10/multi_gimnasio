<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* Helpers */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }
function pick_col(array $cands, array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; }
function has_table(mysqli $db, string $t): bool { $t=$db->real_escape_string($t); $q=$db->query("SHOW TABLES LIKE '$t'"); $ok=$q&&$q->num_rows>0; if($q)$q->close(); return $ok; }
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $r=$db->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1");
  $ok=$r&&$r->num_rows>0; if($r)$r->close(); return $ok;
}
function toIntOrNull($v){ return ($v==='' || !is_numeric($v)) ? null : (int)$v; }

/* Base dir */
$SELF_DIR = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$BASE = ($SELF_DIR === '' || $SELF_DIR === '.') ? '' : $SELF_DIR;
$back = $BASE.'/index.php';

/* Detectar columnas de competidores_evento */
$colsCe=[];
if ($q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`")){
  while($r=$q->fetch_assoc()){ $colsCe[strtolower($r['Field'])]=$r['Field']; }
  $q->close();
}
$CE_ID       = pick_col(['id','competidor_id'],$colsCe);
$CE_DNI      = pick_col(['dni','documento','doc'],$colsCe);
$CE_NOMBRE   = pick_col(['nombre','nombres','display_name','nombre_completo','nombreyapellido'],$colsCe) ?: 'nombre';
$CE_APELLIDO = pick_col(['apellido','apellidos'],$colsCe) ?: 'apellido';
$CE_ESC_NOM  = pick_col(['escuela_nombre','academia','gimnasio','equipo'],$colsCe) ?: 'escuela_nombre';
$CE_ESC_LOGO = pick_col(['escuela_logo','logo_escuela','logo_academia'],$colsCe) ?: 'escuela_logo';
$CE_FOTO     = pick_col(['foto_competidor','foto','avatar'],$colsCe) ?: 'foto_competidor';
$CE_MODAL_ID = pick_col(['modalidad_id'],$colsCe);
$CE_PESO_ID  = pick_col(['categoria_peso_id','peso_id'],$colsCe);
$CE_WINS     = pick_col(['wins','ganadas','w'],$colsCe);
$CE_LOSSES   = pick_col(['losses','perdidas','l'],$colsCe);
$CE_DRAWS    = pick_col(['draws','empates','d'],$colsCe);
$CE_NC       = pick_col(['no_contest','nc','sin_decision'],$colsCe);
if (!$CE_ID) { http_response_code(500); exit('❌ No se detectó columna ID en competidores_evento'); }

/* Flags extra */
$hasMod = has_table($conexion,'modalidades_evento');
$hasPeso= has_table($conexion,'categorias_peso_evento');

/* Peleas */
$colsPe=[];
if ($q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`")){
  while($r=$q->fetch_assoc()){ $colsPe[strtolower($r['Field'])]=$r['Field']; }
  $q->close();
}
$PE_ID    = pick_col(['id','pelea_id','id_pelea'],$colsPe) ?: 'id';
$PE_ROJO  = pick_col(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'],$colsPe);
$PE_AZUL  = pick_col(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'],$colsPe);
$PE_EVENTO= pick_col(['evento_id','id_evento','evento'],$colsPe);
$PE_FECHA = pick_col(['fecha','fecha_pelea','fpelea','created_at','creado_en'],$colsPe);
$PE_GAN   = pick_col(['ganador','resultado','winner'],$colsPe);
if (!$PE_ROJO || !$PE_AZUL) { $PE_ROJO=null; $PE_AZUL=null; }

/* resultados_combates & resultados_jueces */
$hasRC = has_table($conexion,'resultados_combates');
$useRJ = has_table($conexion,'resultados_jueces') &&
         has_col($conexion,'resultados_jueces','pelea_id') &&
         has_col($conexion,'resultados_jueces','ganador');

/* Parámetros */
$comp_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
$dni_in  = isset($_GET['dni']) ? trim((string)$_GET['dni']) : null;

if (!$comp_id && !$dni_in) {
  http_response_code(400);
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Ficha de competidor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= h($BASE) ?>/estilo_unificado.css?v=3">
  </head>
  <body>
    <div class="wrap">
      <div class="page-card">
        <h3>Falta parámetro</h3>
        <p>No se indicó <code>dni</code> ni <code>id</code> del competidor.</p>
      </div>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* Mensajes flash (por si vienen de admin) */
$flash_ok    = $_SESSION['flash_ok']    ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

/* Resolver IDs del competidor */
$ids=[];
$perfil=[
  'dni'=>null,
  'id_base'=>null,
  'nombre'=>'',
  'escuela'=>'',
  'logo'=>'',
  'foto'=>'',
  'modalidad'=>null,
  'peso'=>null,
  'W'=>0,'L'=>0,'D'=>0,'NC'=>0
];
$mod_id=null;
$peso_id=null;

if ($dni_in) {
  $dni=$dni_in;

  /* 1) Todas las fichas con ese DNI */
  $sql="SELECT c.".bt($CE_ID)." id,
               ".($CE_DNI?"c.".bt($CE_DNI)." dni":"NULL dni").",
               c.".bt($CE_APELLIDO)." ape,
               c.".bt($CE_NOMBRE)." nom,
               c.".bt($CE_ESC_NOM)." esc,
               c.".bt($CE_ESC_LOGO)." logo,
               c.".bt($CE_FOTO)." foto,
               ".($CE_WINS?"CAST(c.".bt($CE_WINS)." AS SIGNED)":"0")." Wb,
               ".($CE_LOSSES?"CAST(c.".bt($CE_LOSSES)." AS SIGNED)":"0")." Lb,
               ".($CE_DRAWS?"CAST(c.".bt($CE_DRAWS)." AS SIGNED)":"0")." Db,
               ".($CE_NC?"CAST(c.".bt($CE_NC)." AS SIGNED)":"0")." NCb,
               ".($CE_MODAL_ID?"c.".bt($CE_MODAL_ID):"NULL")." mod_id,
               ".($CE_PESO_ID?"c.".bt($CE_PESO_ID):"NULL")." peso_id
        FROM competidores_evento c
        WHERE ".bt($CE_DNI)." = ?";
  $st=$conexion->prepare($sql); $st->bind_param('s',$dni); $st->execute(); $rs=$st->get_result();
  $f=[]; while($r=$rs->fetch_assoc()){ $f[]=$r; $ids[]=(int)$r['id']; }
  $st->close();

  if (!$f) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Ficha de competidor</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="<?= h($BASE) ?>/estilo_unificado.css?v=3">
    </head>
    <body>
      <div class="wrap">
        <div class="page-card">
          <h3>No se encontró el DNI</h3>
          <p>El DNI ingresado no tiene ficha en el sistema.</p>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
  }

  /* Tomamos apellido+nombre de la primera ficha para buscar clones sin DNI o con otro DNI */
  $apeRef = trim((string)$f[0]['ape']);
  $nomRef = trim((string)$f[0]['nom']);

  if ($apeRef !== '' || $nomRef !== '') {
    $sqlDup = "SELECT c.".bt($CE_ID)." id,
                      ".($CE_DNI?"c.".bt($CE_DNI)." dni":"NULL dni").",
                      c.".bt($CE_APELLIDO)." ape,
                      c.".bt($CE_NOMBRE)." nom,
                      c.".bt($CE_ESC_NOM)." esc,
                      c.".bt($CE_ESC_LOGO)." logo,
                      c.".bt($CE_FOTO)." foto,
                      ".($CE_WINS?"CAST(c.".bt($CE_WINS)." AS SIGNED)":"0")." Wb,
                      ".($CE_LOSSES?"CAST(c.".bt($CE_LOSSES)." AS SIGNED)":"0")." Lb,
                      ".($CE_DRAWS?"CAST(c.".bt($CE_DRAWS)." AS SIGNED)":"0")." Db,
                      ".($CE_NC?"CAST(c.".bt($CE_NC)." AS SIGNED)":"0")." NCb,
                      ".($CE_MODAL_ID?"c.".bt($CE_MODAL_ID):"NULL")." mod_id,
                      ".($CE_PESO_ID?"c.".bt($CE_PESO_ID):"NULL")." peso_id
               FROM competidores_evento c
               WHERE ".bt($CE_APELLIDO)." = ? AND ".bt($CE_NOMBRE)." = ?";

    $st=$conexion->prepare($sqlDup); $st->bind_param('ss',$apeRef,$nomRef);
    $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()){
      $idCur = (int)$r['id'];
      if (!in_array($idCur,$ids,true)) {
        $f[] = $r;
        $ids[] = $idCur;
      }
    }
    $st->close();
  }

  /* Agregar TODAS las estadísticas sumadas y elegir ficha base y foto/logo */
  $perfil['dni']=$dni;
  $perfil['W']=0; $perfil['L']=0; $perfil['D']=0; $perfil['NC']=0;
  $mejorFotoId = null; $mejorLogoId = null;
  $fotoPorId = []; $logoPorId = [];
  $mod_id = null; $peso_id = null;

  foreach($f as $row){
    $perfil['W'] += (int)$row['Wb'];
    $perfil['L'] += (int)$row['Lb'];
    $perfil['D'] += (int)$row['Db'];
    $perfil['NC']+= (int)$row['NCb'];

    $idRow = (int)$row['id'];
    $fotoPorId[$idRow] = $row['foto'] ?? '';
    $logoPorId[$idRow] = $row['logo'] ?? '';

    if ($row['foto'] ?? '')  $mejorFotoId = $idRow;
    if ($row['logo'] ?? '')  $mejorLogoId = $idRow;

    // guardamos nombre/escuela de la última fila (la de ID más alto al ordenar luego)
  }

  // ordenar por id para determinar base (ID más alto)
  usort($f,function($a,$b){ return ((int)$a['id'])<=>((int)$b['id']); });
  $base=end($f);

  $perfil['id_base']=(int)$base['id'];
  $perfil['nombre']=trim(($base['ape']??'').' '.($base['nom']??''))?:'—';
  $perfil['escuela']=$base['esc']??'';
  $mod_id=$base['mod_id']??null;
  $peso_id=$base['peso_id']??null;

  // foto/logo: preferimos las de la ficha que tenga, sino la base
  $fotoBaseId = $mejorFotoId ?: $perfil['id_base'];
  $logoBaseId = $mejorLogoId ?: $perfil['id_base'];
  $perfil['foto'] = $fotoPorId[$fotoBaseId] ?? '';
  $perfil['logo'] = $logoPorId[$logoBaseId] ?? '';

} else {
  /* Entrada por ID directo */
  $id=(int)$comp_id;

  $sql="SELECT c.".bt($CE_ID)." id,
               ".($CE_DNI?"c.".bt($CE_DNI)." dni":"NULL dni").",
               c.".bt($CE_APELLIDO)." ape,
               c.".bt($CE_NOMBRE)." nom,
               c.".bt($CE_ESC_NOM)." esc,
               c.".bt($CE_ESC_LOGO)." logo,
               c.".bt($CE_FOTO)." foto,
               ".($CE_WINS?"CAST(c.".bt($CE_WINS)." AS SIGNED)":"0")." Wb,
               ".($CE_LOSSES?"CAST(c.".bt($CE_LOSSES)." AS SIGNED)":"0")." Lb,
               ".($CE_DRAWS?"CAST(c.".bt($CE_DRAWS)." AS SIGNED)":"0")." Db,
               ".($CE_NC?"CAST(c.".bt($CE_NC)." AS SIGNED)":"0")." NCb,
               ".($CE_MODAL_ID?"c.".bt($CE_MODAL_ID):"NULL")." mod_id,
               ".($CE_PESO_ID?"c.".bt($CE_PESO_ID):"NULL")." peso_id
        FROM competidores_evento c WHERE c.".bt($CE_ID)." = ?";
  $st=$conexion->prepare($sql); $st->bind_param('i',$id); $st->execute();
  $base=$st->get_result()->fetch_assoc(); $st->close();

  if (!$base) {
    http_response_code(404);
    ?>
    <!doctype html>
    <html lang="es">
    <head>
      <meta charset="utf-8">
      <title>Ficha de competidor</title>
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="<?= h($BASE) ?>/estilo_unificado.css?v=3">
    </head>
    <body>
      <div class="wrap">
        <div class="page-card">
          <h3>Competidor no encontrado</h3>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit;
  }

  $perfil['dni']=$base['dni']??null;

  // incluir todos los IDs que compartan el mismo DNI (si tiene)
  if ($perfil['dni']) {
    $dni = $perfil['dni'];
    $st=$conexion->prepare("SELECT ".bt($CE_ID)." id FROM competidores_evento WHERE ".bt($CE_DNI)." = ?");
    $st->bind_param('s',$dni); $st->execute(); $rs=$st->get_result();
    while($r=$rs->fetch_assoc()){ $ids[]=(int)$r['id']; }
    $st->close();
  }
  if (!in_array((int)$base['id'],$ids,true)) $ids[]=(int)$base['id'];

  $perfil['id_base']=(int)$base['id'];
  $perfil['nombre']=trim(($base['ape']??'').' '.($base['nom']??''))?:'—';
  $perfil['escuela']=$base['esc']??'';
  $perfil['foto']=$base['foto']??'';
  $perfil['logo']=$base['logo']??'';
  $perfil['W']+=(int)$base['Wb'];
  $perfil['L']+=(int)$base['Lb'];
  $perfil['D']+=(int)$base['Db'];
  $perfil['NC']+=(int)$base['NCb'];
  $mod_id=$base['mod_id']??null;
  $peso_id=$base['peso_id']??null;
}

/* etiquetas */
$perfil['modalidad']=null; $perfil['peso']=null;
if ($hasMod && !empty($mod_id)){
  $r=$conexion->query("SELECT nombre FROM modalidades_evento WHERE id=".(int)$mod_id);
  if($r){ $perfil['modalidad']=$r->fetch_row()[0]??null; $r->close(); }
}
if ($hasPeso && !empty($peso_id)){
  $r=$conexion->query("SELECT nombre FROM categorias_peso_evento WHERE id=".(int)$peso_id);
  if($r){ $perfil['peso']=$r->fetch_row()[0]??null; $r->close(); }
}

/* Placeholders */
$phUser='assets/placeholder-user.png';
$phGym ='assets/placeholder-gym.png';
$svgUser = "data:image/svg+xml;utf8,".rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110"><rect width="100%" height="100%" fill="#e5e7eb"/><circle cx="55" cy="42" r="22" fill="#cbd5e1"/><rect x="20" y="72" width="70" height="20" rx="10" fill="#cbd5e1"/></svg>');
$svgLogo = "data:image/svg+xml;utf8,".rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="110" height="110"><rect width="100%" height="100%" fill="#e5e7eb"/><text x="50%" y="55%" font-size="16" text-anchor="middle" fill="#94a3b8" font-family="Arial">LOGO</text></svg>');

/* Historial de peleas — rojo, azul o ganador en resultados_combates */
$historial=[];
if ($ids && $PE_ROJO && $PE_AZUL) {
  $idsInt = array_map('intval',$ids);
  $in = implode(',', $idsInt);

  $cols = "p.".bt($PE_ID)." pelea_id,
           p.".bt($PE_ROJO)." rojo_id,
           p.".bt($PE_AZUL)." azul_id".
           ($PE_FECHA ? ", p.".bt($PE_FECHA)." fecha" : ", NULL fecha").
           ($PE_EVENTO? ", p.".bt($PE_EVENTO)." evento_id" : ", NULL evento_id").",
           rc.ganador_color rc_gcolor, rc.ganador_id rc_gid, rc.metodo rc_metodo, rc.detalle rc_detalle,
           rc.puntos_rojo rc_pr, rc.puntos_azul rc_pa, rc.creado_en rc_creado,
           ".($PE_GAN ? "p.".bt($PE_GAN)." pe_ganador" : "NULL pe_ganador").",
           cr.".bt($CE_APELLIDO)." r_ape, cr.".bt($CE_NOMBRE)." r_nom, cr.".bt($CE_ESC_NOM)." r_esc, cr.".bt($CE_FOTO)." r_foto, mr.nombre r_mod,
           ca.".bt($CE_APELLIDO)." a_ape, ca.".bt($CE_NOMBRE)." a_nom, ca.".bt($CE_ESC_NOM)." a_esc, ca.".bt($CE_FOTO)." a_foto, ma.nombre a_mod";

  $sql = "SELECT $cols
          FROM peleas_evento p
          LEFT JOIN competidores_evento cr ON p.".bt($PE_ROJO)." = cr.".bt($CE_ID)."
          LEFT JOIN competidores_evento ca ON p.".bt($PE_AZUL)." = ca.".bt($CE_ID)."
          LEFT JOIN modalidades_evento mr ON mr.id = cr.".bt($CE_MODAL_ID)."
          LEFT JOIN modalidades_evento ma ON ma.id = ca.".bt($CE_MODAL_ID)."
          LEFT JOIN resultados_combates rc ON rc.pelea_id = p.".bt($PE_ID)."
          WHERE
            p.".bt($PE_ROJO)." IN ($in)
            OR p.".bt($PE_AZUL)." IN ($in)
            ".($hasRC ? "OR rc.ganador_id IN ($in)" : "")."
          ";

  if ($r=$conexion->query($sql)){
    while($row=$r->fetch_assoc()){ $historial[]=$row; }
    $r->close();
  }

  /* Mayoría por resultados_jueces si no hay ganador explícito */
  $mayoria=[];
  if ($useRJ && $historial){
    $idsP = array_map(fn($x)=>(int)$x['pelea_id'],$historial);
    if ($idsP){
      $inP=implode(',', $idsP);
      $rq=$conexion->query("SELECT pelea_id, SUM(ganador='azul') az, SUM(ganador='rojo') ro, SUM(ganador='empate') em
                            FROM resultados_jueces
                            WHERE pelea_id IN ($inP) AND (estado IS NULL OR estado='enviado')
                            GROUP BY pelea_id");
      if($rq){
        while($z=$rq->fetch_assoc()){
          $g=null;
          if ($z['az']>$z['ro'] && $z['az']>$z['em']) $g='azul';
          elseif ($z['ro']>$z['az'] && $z['ro']>$z['em']) $g='rojo';
          elseif ($z['em']>$z['az'] && $z['em']>$z['ro']) $g='empate';
          $mayoria[(int)$z['pelea_id']]=$g;
        }
        $rq->close();
      }
    }
  }

  foreach ($historial as &$p) {
    $yo_rojo = in_array((int)$p['rojo_id'],$idsInt,true);
    $yo_azul = in_array((int)$p['azul_id'],$idsInt,true);
    $p['_lado'] = $yo_rojo ? 'Rojo' : ($yo_azul ? 'Azul' : '?' );

    $p['_rv_nombre'] = $yo_rojo
      ? trim(($p['a_ape']??'').' '.($p['a_nom']??'')) 
      : trim(($p['r_ape']??'').' '.($p['r_nom']??''));
    $p['_rv_esc']    = $yo_rojo ? ($p['a_esc']??'') : ($p['r_esc']??'');

    $rvRaw = $yo_rojo ? ($p['a_foto']??'') : ($p['r_foto']??'');
    $p['_rv_foto'] = $rvRaw ?: $phUser;

    $mod_r = $p['r_mod'] ?? ''; $mod_a = $p['a_mod'] ?? '';
    $p['_modalidad'] = $yo_rojo ? $mod_r : ($mod_a ?: $mod_r);

    // Determinar ganador
    $g = $p['rc_gcolor'] ? strtolower((string)$p['rc_gcolor']) : null;
    if (!$g && !empty($p['pe_ganador'])) $g = strtolower((string)$p['pe_ganador']);
    if (!$g && isset($mayoria[(int)$p['pelea_id']])) $g = $mayoria[(int)$p['pelea_id']];

    if ($g==='empate'){ $p['_res']='Empate'; $perfil['D']++; }
    elseif ($g==='azul' || $g==='rojo'){
      $yo = $yo_azul ? 'azul' : ($yo_rojo ? 'rojo' : null);
      if ($yo && $g===$yo){ $p['_res']='Victoria'; $perfil['W']++; }
      else { $p['_res']='Derrota'; $perfil['L']++; }
    } else { $p['_res']='—'; }

    $p['_metodo']  = (string)($p['rc_metodo']  ?? '');
    $p['_detalle'] = (string)($p['rc_detalle'] ?? '');
    $p['_pR']      = ($p['rc_pr'] !== null) ? (string)$p['rc_pr'] : '';
    $p['_pA']      = ($p['rc_pa'] !== null) ? (string)$p['rc_pa'] : '';
    $p['_rc_fecha']= (string)($p['rc_creado'] ?? '');
  }
  unset($p);

  // Ordenar por fecha, luego por ID
  usort($historial,function($A,$B){
    $fa=$A['fecha']??null; $fb=$B['fecha']??null;
    if ($fa && $fb) return strcmp($fa,$fb);
    if ($fa && !$fb) return -1;
    if (!$fa && $fb) return 1;
    return ((int)$A['pelea_id'])<=>((int)$B['pelea_id']);
  });
}

/* Datos de ranking */
$totalPeleas = (int)$perfil['W'] + (int)$perfil['L'] + (int)$perfil['D'] + (int)$perfil['NC'];
$porcVictorias = $totalPeleas > 0 ? round(((int)$perfil['W'] * 100) / $totalPeleas) : null;
$estaRankeado = $totalPeleas > 0;

$foto = !empty($perfil['foto']) ? $perfil['foto'] : $phUser;
$logo = !empty($perfil['logo']) ? $perfil['logo'] : $phGym;

/* Info de duplicados para que los ubiques en admin */
$duplicates = array_values(array_unique(array_map('intval',$ids)));
sort($duplicates);
$duplicados = array_values(array_filter($duplicates, fn($v)=>$v !== (int)$perfil['id_base']));
$isAdmin = !empty($_SESSION['gimnasio_id']) || !empty($_SESSION['usuario_id']);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>🥇 Ficha de competidor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="<?= h($BASE) ?>/estilo_unificado.css?v=3">
<style>
  body{background:#020617;color:#e5e7eb;font-family:system-ui,Segoe UI,Roboto,Helvetica,Arial}
  .pfp, .logo {
    background:#020617;
    border:1px solid var(--stroke,#1f2937);
    border-radius:18px;
    box-shadow:0 10px 25px rgba(15,23,42,.35);
  }
  .encabezado-competidor{
    display:grid;
    grid-template-columns:120px 1fr 120px;
    gap:14px;
    align-items:center;
  }
  @media (max-width:700px){
    .encabezado-competidor{
      grid-template-columns:80px 1fr;
      grid-template-rows:auto auto;
    }
    .encabezado-competidor .logo{
      grid-column:1 / -1;
      justify-self:flex-start;
      margin-top:8px;
      width:80px !important;
      height:80px !important;
    }
  }
  .record-strip{
    margin-top:6px;
    display:flex;
    flex-wrap:wrap;
    gap:6px;
  }
  .record-main{
    font-size:13px;
    font-weight:600;
    color:#e5e7eb;
  }
  .record-main span{font-weight:700;}
  .pill{
    display:inline-flex;
    align-items:center;
    gap:4px;
    background:#0f172a;
    padding:3px 10px;
    border-radius:999px;
    font-size:12px;
    font-weight:500;
  }
  .pill-win  { background:#166534; }
  .pill-loss { background:#b91c1c; }
  .pill-draw { background:#0369a1; }
  .pill-nc   { background:#4b5563; }

  .badge-ranked{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:linear-gradient(135deg,#facc15,#f97316);
    color:#1f2933;
    border-radius:999px;
    padding:4px 10px;
    font-size:11px;
    font-weight:700;
    letter-spacing:.04em;
    text-transform:uppercase;
    box-shadow:0 6px 16px rgba(0,0,0,.25);
  }
  .badge-ranked svg{width:14px;height:14px;}

  .hist-table table { background:#ffffff !important; border-collapse:separate; border-spacing:0; }
  .hist-table thead th{
    color:#0f172a !important;
    background:#e5e7eb !important;
    position:sticky; top:0; z-index:1;
  }
  .hist-table tbody td{ color:#0f172a !important; background:#ffffff !important; }
  .hist-table tbody tr:hover{ background:#f8fafc !important; }

  .row-win td{
    border-left:4px solid #22c55e;
    background:linear-gradient(90deg,rgba(34,197,94,.06),#ffffff);
  }
  .row-loss td{
    border-left:4px solid #ef4444;
    background:linear-gradient(90deg,rgba(239,68,68,.04),#ffffff);
  }
  .row-draw td{
    border-left:4px solid #0ea5e9;
    background:linear-gradient(90deg,rgba(14,165,233,.04),#ffffff);
  }
  .badge{
    padding:3px 8px;
    border-radius:999px;
    font-size:11px;
  }
  .badge-victoria{ background:#22c55e1a; color:#15803d; }
  .badge-derrota{ background:#ef44441a; color:#b91c1c; }
  .badge-empate { background:#0ea5e91a; color:#0369a1; }

  .table-wrap{
    max-height:450px;
    overflow:auto;
    border-radius:12px;
    border:1px solid #e5e7eb;
  }
  .section-title{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }
  .section-title small{
    font-size:11px;
    color:#6b7280;
  }
  .muted{color:#9ca3af;font-size:12px}
  .btn-admin{
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#111827;
    color:#fff;
    padding:6px 12px;
    font-size:12px;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    gap:6px;
  }
  .btn-admin:hover{background:#0f172a;}
  .dup-box{
    margin-top:10px;
    padding:8px 10px;
    border-radius:10px;
    background:#111827;
    border:1px solid #374151;
    font-size:12px;
  }
</style>
</head>
<body>

<div class="wrap">

  <?php if (!empty($flash_ok)): ?><div class="ok"><?= h($flash_ok) ?></div><?php endif; ?>
  <?php if (!empty($flash_error)): ?><div class="bad"><?= h($flash_error) ?></div><?php endif; ?>

  <div class="page-card">
    <div class="encabezado-competidor">
      <img class="pfp" src="<?= h($foto) ?>"
           alt="Competidor"
           width="110" height="110"
           style="width:110px;height:110px;object-fit:cover"
           onerror="this.onerror=null;this.src='<?= h($svgUser) ?>';">

      <div>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:4px;">
          <h2 style="margin:0"><?= h($perfil['nombre']) ?></h2>
          <?php if ($estaRankeado): ?>
            <span class="badge-ranked" title="Este competidor tiene peleas registradas">
              <svg viewBox="0 0 24 24" aria-hidden="true">
                <path fill="currentColor" d="M9 3L7 8H3l3.5 3-1.5 5L9 13l4 3-1.5-5L15 8h-4L9 3z"/>
              </svg>
              Rankeado
            </span>
          <?php endif; ?>
        </div>

        <?php if (!empty($perfil['dni'])): ?>
          <div class="muted">
            DNI: <?= h($perfil['dni']) ?> • Ficha base ID <?= (int)$perfil['id_base'] ?>
          </div>
        <?php else: ?>
          <div class="muted">
            Ficha base ID <?= (int)$perfil['id_base'] ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($perfil['escuela'])): ?>
          <div class="muted" style="margin-top:4px">
            Escuela / Equipo: <strong><?= h($perfil['escuela']) ?></strong>
          </div>
        <?php endif; ?>

        <div class="record-strip">
          <span class="record-main">
            Record: <span><?= (int)$perfil['W'] ?>-<?= (int)$perfil['L'] ?>-<?= (int)$perfil['D'] ?></span>
            <?php if ($totalPeleas > 0): ?>
              (<?= $totalPeleas ?> pelea<?= $totalPeleas!==1?'s':'' ?><?= $porcVictorias !== null ? ' • '.$porcVictorias.'% victorias' : '' ?>)
            <?php endif; ?>
          </span>
          <span class="pill pill-win">W: <?= (int)$perfil['W'] ?></span>
          <span class="pill pill-loss">L: <?= (int)$perfil['L'] ?></span>
          <span class="pill pill-draw">D: <?= (int)$perfil['D'] ?></span>
          <span class="pill pill-nc">NC: <?= (int)$perfil['NC'] ?></span>
        </div>

        <div class="muted" style="margin-top:8px">
          <?= $perfil['modalidad'] ? 'Modalidad: <strong>'.h($perfil['modalidad']).'</strong> • ' : '' ?>
          <?= $perfil['peso'] ? 'Peso: <strong>'.h($perfil['peso']).'</strong>' : '' ?>
        </div>

        <?php if ($isAdmin): ?>
          <div style="margin-top:10px">
            <a class="btn-admin" href="<?= h($BASE) ?>/ver_competidor_evento.php?id=<?= (int)$perfil['id_base'] ?>">
              🔧 Abrir en panel admin
            </a>
          </div>
        <?php endif; ?>

        <?php if (count($duplicates) > 1): ?>
          <div class="dup-box">
            <strong>⚠ DNI con fichas duplicadas:</strong><br>
            IDs en competidores_evento: <?= implode(', ', array_map('intval',$duplicates)) ?>.<br>
            Esta ficha pública usa la base <strong>ID <?= (int)$perfil['id_base'] ?></strong>.
          </div>
        <?php endif; ?>

      </div>

      <img class="logo" src="<?= h($logo) ?>"
           alt="Escuela"
           width="110" height="110"
           style="width:110px;height:110px;object-fit:contain"
           onerror="this.onerror=null;this.src='<?= h($svgLogo) ?>';">
    </div>
  </div>

  <div class="page-card">
    <div class="section-title">
      <h3 style="margin:0">📜 Historial de peleas</h3>
      <?php if ($totalPeleas > 0): ?>
        <small><?= $totalPeleas ?> pelea<?= $totalPeleas!==1?'s':'' ?> registradas</small>
      <?php else: ?>
        <small>Sin peleas registradas aún</small>
      <?php endif; ?>
    </div>

    <div class="table-wrap hist-table" aria-label="Historial de peleas">
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
            <th>Método</th>
            <th>Detalle</th>
            <th>Puntos (R-A)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$historial): ?>
            <tr><td colspan="10" class="muted" style="text-align:center;padding:16px">Sin peleas cargadas.</td></tr>
          <?php else: foreach ($historial as $p):
            $fec = $p['fecha'] ? h($p['fecha']) : ($p['_rc_fecha'] ? h($p['_rc_fecha']) : '—');
            $evento = isset($p['evento_id']) && $p['evento_id']!==null ? ('#'.(int)$p['evento_id']) : '—';
            $rvFoto = $p['_rv_foto'] ?: $phUser;
            $pts = ($p['_pR']!=='' || $p['_pA']!=='') ? ((int)$p['_pR'].' — '.(int)$p['_pA']) : '—';

            $res = $p['_res'];
            $rowCls = '';
            $badgeCls = 'badge';
            if ($res === 'Victoria') { $rowCls = 'row-win'; $badgeCls .= ' badge-victoria'; }
            elseif ($res === 'Derrota') { $rowCls = 'row-loss'; $badgeCls .= ' badge-derrota'; }
            elseif ($res === 'Empate') { $rowCls = 'row-draw'; $badgeCls .= ' badge-empate'; }
          ?>
            <tr class="<?= h($rowCls) ?>">
              <td><?= $fec ?></td>
              <td><?= h($evento) ?></td>
              <td><?= h($p['_lado']) ?></td>
              <td>
                <div style="display:flex;gap:8px;align-items:center">
                  <img src="<?= h($rvFoto) ?>" alt="rival"
                       width="40" height="40"
                       style="width:40px;height:40px;object-fit:cover;border-radius:8px;border:1px solid var(--stroke,#e5e7eb);background:#0b0d12"
                       onerror="this.onerror=null;this.src='<?= h($svgUser) ?>';">
                  <div style="color:#0f172a;font-size:13px;font-weight:500"><?= h($p['_rv_nombre'] ?: '—') ?></div>
                </div>
              </td>
              <td><?= h($p['_rv_esc'] ?: '—') ?></td>
              <td><?= h($p['_modalidad'] ?: '—') ?></td>
              <td><span class="<?= h($badgeCls) ?>"><?= h($res) ?></span></td>
              <td><?= h($p['_metodo'] ?: '—') ?></td>
              <td><?= h($p['_detalle'] ?: '—') ?></td>
              <td><?= h($pts) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
</body>
</html>
