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

/* ===== CSRF para eliminar ===== */
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];

/* ===== Tablas requeridas ===== */
if (!has_table($conexion,'competidores_evento') || !has_table($conexion,'peleas_evento')) {
  exit('❌ Faltan tablas requeridas: competidores_evento / peleas_evento');
}

/* ===== Descubrir columnas ===== */
$colsPe=[]; $q=$conexion->query("SHOW COLUMNS FROM `peleas_evento`"); while($r=$q->fetch_assoc()){ $colsPe[strtolower($r['Field'])]=$r['Field']; } if($q)$q->close();
$colsCe=[]; $q=$conexion->query("SHOW COLUMNS FROM `competidores_evento`"); while($r=$q->fetch_assoc()){ $colsCe[strtolower($r['Field'])]=$r['Field']; } if($q)$q->close();

$pick=function(array $cands,array $pool){ foreach($cands as $c){ $lc=strtolower($c); if(isset($pool[$lc])) return $pool[$lc]; } return null; };

$C_AZUL   = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul'], $colsPe);
$C_ROJO   = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo'], $colsPe);
$C_EVENTO = $pick(['evento_id','id_evento','evento'], $colsPe);
$C_FECHA  = $pick(['fecha','fecha_pelea','fpelea','created_at'], $colsPe);
if (!$C_AZUL || !$C_ROJO) { exit('❌ No se detectaron columnas de rincón azul/rojo en peleas_evento.'); }

$C_ID        = $pick(['id','competidor_id'], $colsCe);
$C_NOMBRE    = $pick(['nombre'], $colsCe);
$C_APELLIDO  = $pick(['apellido'], $colsCe);
$C_ESC_NOM   = $pick(['escuela_nombre','academia','gimnasio','equipo'], $colsCe);
$C_ESC_LOGO  = $pick(['escuela_logo','logo_escuela','logo_academia'], $colsCe);
$C_FOTO      = $pick(['foto_competidor','foto','avatar'], $colsCe);
$C_PESO_ID   = $pick(['categoria_peso_id','peso_id'], $colsCe);
$C_MODAL_ID  = $pick(['modalidad_id'], $colsCe);
$C_ACTIVO    = $pick(['activo'], $colsCe);
$C_ESTADO    = $pick(['estado'], $colsCe);
if (!$C_ID) { exit('❌ No se detectó columna ID en competidores_evento.'); }

/* ===== Eliminar / Archivar (sin permisos, para todos) ===== */
$flashMsg=''; $flashErr='';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['accion'] ?? '')==='eliminar') {
  if (!hash_equals($CSRF, (string)($_POST['csrf'] ?? ''))) {
    $flashErr='CSRF inválido.';
  } else {
    $delId = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($delId<=0) { $flashErr='ID inválido.'; }
    else {
      // ¿Tiene peleas?
      $ref=0;
      $sqlRef="SELECT COUNT(*) FROM `peleas_evento` WHERE ".bt($C_AZUL)."=? OR ".bt($C_ROJO)."=?";
      if($st=$conexion->prepare($sqlRef)){ $st->bind_param('ii',$delId,$delId); $st->execute(); $st->bind_result($ref); $st->fetch(); $st->close(); }

      // Preferir ARCHIVAR si hay columna
      if ($C_ACTIVO) {
        if ($st=$conexion->prepare("UPDATE `competidores_evento` SET ".bt($C_ACTIVO)."=0 WHERE ".bt($C_ID)."=?")){
          $st->bind_param('i',$delId); $ok=$st->execute(); $st->close();
          $flashMsg = $ok ? 'Competidor archivado (activo=0).' : 'No se pudo archivar.';
        } else { $flashErr='Error preparando archivado.'; }
      } elseif ($C_ESTADO) {
        if ($st=$conexion->prepare("UPDATE `competidores_evento` SET ".bt($C_ESTADO)."='baja' WHERE ".bt($C_ID)."=?")){
          $st->bind_param('i',$delId); $ok=$st->execute(); $st->close();
          $flashMsg = $ok ? 'Competidor marcado como baja.' : 'No se pudo marcar baja.';
        } else { $flashErr='Error preparando baja.'; }
      } else {
        // Sin columna de archivado: borrar solo si no tiene peleas
        if ($ref>0) {
          $flashErr='No se puede eliminar: el competidor tiene peleas asociadas y no hay columna activo/estado para archivar.';
        } else {
          if ($st=$conexion->prepare("DELETE FROM `competidores_evento` WHERE ".bt($C_ID)."=? LIMIT 1")){
            $st->bind_param('i',$delId); $ok=$st->execute(); $st->close();
            $flashMsg = $ok ? 'Competidor eliminado.' : 'No se pudo eliminar.';
          } else { $flashErr='Error preparando DELETE.'; }
        }
      }
    }
  }
}

/* ===== Mayoría por pelea desde resultados_jueces ===== */
$winnerByFight=[];
if (has_table($conexion,'resultados_jueces') && has_col($conexion,'resultados_jueces','pelea_id') && has_col($conexion,'resultados_jueces','ganador')) {
  $sql="SELECT pelea_id,
    CASE
      WHEN SUM(ganador='azul')>SUM(ganador='rojo') AND SUM(ganador='azul')>SUM(ganador='empate') THEN 'azul'
      WHEN SUM(ganador='rojo')>SUM(ganador='azul') AND SUM(ganador='rojo')>SUM(ganador='empate') THEN 'rojo'
      ELSE 'empate'
    END AS g
    FROM resultados_jueces
    WHERE estado IS NULL OR estado='enviado'
    GROUP BY pelea_id";
  if ($r=$conexion->query($sql)) { while($row=$r->fetch_assoc()){ $winnerByFight[(int)$row['pelea_id']]=$row['g']; } $r->close(); }
}

/* ===== Traer peleas ===== */
$peleaCols="p.id AS pelea_id, p.".bt($C_AZUL)." AS azul_id, p.".bt($C_ROJO)." AS rojo_id";
if ($C_FECHA)  $peleaCols.=", p.".bt($C_FECHA)." AS f";
if ($C_EVENTO) $peleaCols.=", p.".bt($C_EVENTO)." AS evento_id";
$peleas=[];
if ($r=$conexion->query("SELECT $peleaCols FROM `peleas_evento` p")){
  while($row=$r->fetch_assoc()){
    $row['pelea_id']=(int)$row['pelea_id'];
    $row['azul_id'] =(int)$row['azul_id'];
    $row['rojo_id'] =(int)$row['rojo_id'];
    $row['g']      = $winnerByFight[$row['pelea_id']] ?? null;
    $peleas[]=$row;
  }
  $r->close();
}

/* ===== Traer competidores (todos los registrados) ===== */
$selCe = "c.".bt($C_ID)." AS id";
$selCe.= $C_APELLIDO ? ", c.".bt($C_APELLIDO)." AS apellido" : ", NULL AS apellido";
$selCe.= $C_NOMBRE   ? ", c.".bt($C_NOMBRE)  ." AS nombre"   : ", NULL AS nombre";
$selCe.= $C_ESC_NOM  ? ", c.".bt($C_ESC_NOM) ." AS escuela"  : ", NULL AS escuela";
$selCe.= $C_ESC_LOGO ? ", c.".bt($C_ESC_LOGO)." AS escuela_logo" : ", NULL AS escuela_logo";
$selCe.= $C_FOTO     ? ", c.".bt($C_FOTO)    ." AS foto"     : ", NULL AS foto";
$selCe.= $C_MODAL_ID ? ", c.".bt($C_MODAL_ID)." AS modalidad_id" : ", NULL AS modalidad_id";
$selCe.= $C_PESO_ID  ? ", c.".bt($C_PESO_ID) ." AS peso_id"       : ", NULL AS peso_id";
if ($C_ACTIVO) $selCe .= ", c.".bt($C_ACTIVO)." AS activo";
if ($C_ESTADO) $selCe .= ", c.".bt($C_ESTADO)." AS estado";

$joins=""; $selExtra="";
if (has_table($conexion,'modalidades_evento'))     { $joins.=" LEFT JOIN modalidades_evento mo ON mo.id = c.".bt($C_MODAL_ID);  $selExtra.=", mo.nombre AS modalidad"; }
else { $selExtra.=", NULL AS modalidad"; }
if (has_table($conexion,'categorias_peso_evento')) { $joins.=" LEFT JOIN categorias_peso_evento cp ON cp.id = c.".bt($C_PESO_ID); $selExtra.=", cp.nombre AS peso"; }
else { $selExtra.=", NULL AS peso"; }

$competidores=[];
if ($r=$conexion->query("SELECT $selCe $selExtra FROM `competidores_evento` c $joins")){
  while($row=$r->fetch_assoc()){
    $id=(int)$row['id'];
    $competidores[$id]=[
      'id'=>$id,
      'apellido'=>$row['apellido'] ?? '',
      'nombre'  =>$row['nombre'] ?? '',
      'escuela' =>$row['escuela'] ?? '',
      'escuela_logo'=>$row['escuela_logo'] ?? '',
      'foto'    =>$row['foto'] ?? '',
      'modalidad'=>$row['modalidad'] ?? '',
      'peso'    =>$row['peso'] ?? '',
      'W'=>0,'L'=>0,'D'=>0,'NC'=>0,
      'ult_fecha'=>null,
      'activo'   =>$row['activo'] ?? null,
      'estado'   =>$row['estado'] ?? null,
    ];
  }
  $r->close();
}

/* ===== Calcular récords ===== */
foreach($peleas as $p){
  $az=(int)$p['azul_id']; $ro=(int)$p['rojo_id']; $g=$p['g'] ?? null;
  if(!$az || !$ro) continue;

  foreach([$az,$ro] as $cid){
    if(!isset($competidores[$cid])){
      $competidores[$cid]=['id'=>$cid,'apellido'=>'','nombre'=>'','escuela'=>'','escuela_logo'=>'','foto'=>'','modalidad'=>'','peso'=>'','W'=>0,'L'=>0,'D'=>0,'NC'=>0,'ult_fecha'=>null];
    }
  }

  if ($g===null) { $competidores[$az]['NC']++; $competidores[$ro]['NC']++; }
  elseif ($g==='azul'){ $competidores[$az]['W']++; $competidores[$ro]['L']++; }
  elseif ($g==='rojo'){ $competidores[$ro]['W']++; $competidores[$az]['L']++; }
  else { $competidores[$az]['D']++; $competidores[$ro]['D']++; }

  if (!empty($C_FECHA) && !empty($p['f'])){
    $f=$p['f'];
    if (!$competidores[$az]['ult_fecha'] || $f>$competidores[$az]['ult_fecha']) $competidores[$az]['ult_fecha']=$f;
    if (!$competidores[$ro]['ult_fecha'] || $f>$competidores[$ro]['ult_fecha']) $competidores[$ro]['ult_fecha']=$f;
  }
}

/* ===== Filtros/Orden ===== */
$busca = trim((string)($_GET['q'] ?? ''));
$orden = (string)($_GET['sort'] ?? 'wins'); // wins|name|gym
$lista = array_values($competidores);

if ($busca!==''){
  $q = mb_strtolower($busca,'UTF-8');
  $lista = array_values(array_filter($lista,function($c) use($q){
    $s = mb_strtolower(($c['apellido'].' '.$c['nombre'].' '.$c['escuela']), 'UTF-8');
    return strpos($s,$q)!==false;
  }));
}

usort($lista,function($a,$b) use($orden){
  if ($orden==='name'){
    return strnatcasecmp(($a['apellido'].' '.$a['nombre']),($b['apellido'].' '.$b['nombre']));
  } elseif ($orden==='gym'){
    return strnatcasecmp($a['escuela'] ?? '', $b['escuela'] ?? '');
  } else {
    $da = $b['W'] <=> $a['W']; if ($da) return $da;
    $db = ($a['L'] <=> $b['L']); if ($db) return $db;
    return strnatcasecmp(($a['apellido'].' '.$a['nombre']),($b['apellido'].' '.$b['nombre']));
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
  .actions form{display:inline}
  .actions .pill{margin-right:6px}
</style>
</head>
<body>
<?php @include __DIR__.'/menu_eventos.php'; ?>

<div class="wrap">
  <div class="card">
    <h2 style="margin:0 0 8px 0">📊 Competidores (todos los eventos)</h2>

    <?php if (!empty($flashMsg)): ?><div style="margin:8px 0;padding:8px;border-radius:8px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1"><?= h($flashMsg) ?></div><?php endif; ?>
    <?php if (!empty($flashErr)): ?><div style="margin:8px 0;padding:8px;border-radius:8px;background:#2a1414;border:1px solid #5e2626;color:#ffb4b4"><?= h($flashErr) ?></div><?php endif; ?>

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
            <th class="muted">Última pelea</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$lista): ?>
          <tr><td colspan="10" class="muted">Sin registros.</td></tr>
        <?php else: foreach($lista as $c):
          $nombre = trim(($c['apellido'].' '.$c['nombre'])) ?: '—';
          $foto = $c['foto'] ?: $phUser;
          $logo = $c['escuela_logo'] ?: $phGym;
          $badge='';
          if (array_key_exists('activo',$c) && $c['activo']!=='')      $badge = ((int)$c['activo']===0) ? ' (archivado)' : '';
          elseif (array_key_exists('estado',$c) && $c['estado'])       $badge = ' ('.h($c['estado']).')';
        ?>
          <tr>
            <td>
              <div class="row" style="gap:8px;align-items:center">
                <img class="pfp" src="<?= h($foto) ?>" alt="foto">
                <div>
                  <div style="font-weight:800"><?= h($nombre) ?><?= $badge ?></div>
                  <div class="muted" style="font-size:12px">ID: <?= (int)$c['id'] ?></div>
                </div>
              </div>
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
            <td class="muted"><?= h($c['ult_fecha'] ?: '—') ?></td>

            <td class="actions">
              <a class="pill" href="editar_competidor_evento.php?id=<?= (int)$c['id'] ?>">✏️ Editar</a>
              <form method="post" onsubmit="return confirm('¿Eliminar/archivar competidor #<?= (int)$c['id'] ?>?');" style="display:inline">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <input type="hidden" name="csrf" value="<?= h($CSRF) ?>">
                <button class="pill" style="background:#2a1414;color:#ffb4b4;border-color:#5e2626;cursor:pointer">🗑 Eliminar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="muted" style="margin-top:8px">
      * W/L/D/NC calculado por mayoría de tarjetas en <code>resultados_jueces</code>. Si una pelea no tiene mayoría cargada, se cuenta como <b>NC</b> (sin resultado).
    </div>
  </div>
</div>
</body>
</html>
