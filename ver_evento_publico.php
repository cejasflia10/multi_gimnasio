<?php
/* =========================================================================
   ver_evento_publico.php — Cartelera pública de un evento (todas las peleas)
   - Muestra cada pelea con RINCÓN ROJO / AZUL + escuela/logo + división + peso + modalidad
   - Resalta estado y ganador (si finalizada)
   - Soporta distintos nombres de columnas (instalaciones previas)
   - Link rápido al overlay: en_vivo.php?overlay=1&pelea_id=XX
   ========================================================================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma', 'no-cache');
header('Expires', '0');

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

/* ===== Helpers ===== */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $table, string $col): bool {
  $t=$db->real_escape_string($table); $c=$db->real_escape_string($col);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)) { $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function get_int_qs(array $src, string $key, ?int $def=null): ?int {
  if (!isset($src[$key])) return $def;
  $v = trim((string)$src[$key]);
  if ($v === '' || !preg_match('/^-?\d+$/', $v)) return $def;
  return (int)$v;
}

/* ===== Entrada ===== */
$evento_id = get_int_qs($_GET, 'evento_id', null);
if (is_null($evento_id)) $evento_id = get_int_qs($_GET, 'evento', null);
if (is_null($evento_id)) $evento_id = get_int_qs($_GET, 'id_evento', null);

if (is_null($evento_id) || $evento_id <= 0) {
  echo '<div style="max-width:900px;margin:18px auto;padding:14px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:10px;">Falta <b>evento_id</b> en la URL.</div>';
  exit;
}

/* ===== Título del evento (soporta 'eventos_deportivos' o 'eventos') ===== */
$evento_titulo = 'Evento #' . (int)$evento_id;
$evento_fecha = null; $evento_lugar = null;

if (table_exists($conexion, 'eventos_deportivos')) {
  $cols = ['titulo','fecha','lugar'];
  $pres = [];
  foreach ($cols as $c) if (has_col($conexion, 'eventos_deportivos', $c)) $pres[] = $c;
  if ($pres) {
    $sql = "SELECT ".implode(',', array_map('bt',$pres))." FROM eventos_deportivos WHERE id=? LIMIT 1";
    if ($st = $conexion->prepare($sql)) {
      $st->bind_param('i', $evento_id);
      $st->execute(); $r = $st->get_result();
      if ($row = $r->fetch_assoc()) {
        if (!empty($row['titulo'])) $evento_titulo = (string)$row['titulo'];
        if (!empty($row['fecha']))  $evento_fecha  = (string)$row['fecha'];
        if (!empty($row['lugar']))  $evento_lugar  = (string)$row['lugar'];
      }
      $st->close();
    }
  }
} elseif (table_exists($conexion, 'eventos')) {
  $cand = ['titulo','nombre','fecha','lugar'];
  $pres = [];
  foreach ($cand as $c) if (has_col($conexion,'eventos',$c)) $pres[] = $c;
  if ($pres) {
    $sql = "SELECT ".implode(',', array_map('bt',$pres))." FROM eventos WHERE id=? LIMIT 1";
    if ($st = $conexion->prepare($sql)) {
      $st->bind_param('i', $evento_id);
      $st->execute(); $r = $st->get_result();
      if ($row = $r->fetch_assoc()) {
        if (!empty($row['titulo'])) $evento_titulo = (string)$row['titulo'];
        elseif (!empty($row['nombre'])) $evento_titulo = (string)$row['nombre'];
        if (!empty($row['fecha']))  $evento_fecha  = (string)$row['fecha'];
        if (!empty($row['lugar']))  $evento_lugar  = (string)$row['lugar'];
      }
      $st->close();
    }
  }
}

/* ===== Listado de peleas del evento ===== */
$peleas = [];  // cada item: [id, numero?, estado?, ganador_color?, azul_id?, rojo_id?]
$C_NUM = null; $C_ESTADO = null; $C_GCOL = null; $C_AZ = null; $C_RO = null;

if (!table_exists($conexion,'peleas_evento')) {
  echo '<div style="max-width:900px;margin:18px auto;padding:14px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:10px;">No existe la tabla <b>peleas_evento</b>.</div>';
  exit;
}

/* Columnas variables que buscamos */
$C_NUM = null; foreach(['numero','nro','orden','n_orden','num'] as $c) { if (has_col($conexion,'peleas_evento',$c)) { $C_NUM=$c; break; } }
$C_ESTADO = has_col($conexion,'peleas_evento','estado') ? 'estado' : null;
$C_GCOL   = has_col($conexion,'peleas_evento','ganador_color') ? 'ganador_color' : (has_col($conexion,'peleas_evento','ganador')?'ganador':null);
$C_AZ     = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
$C_RO     = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);

$cols = ['id'];
if ($C_NUM) $cols[] = bt($C_NUM).' AS num';
if ($C_ESTADO) $cols[] = bt($C_ESTADO).' AS estado';
if ($C_GCOL)   $cols[] = bt($C_GCOL).'   AS ganador_color';
if ($C_AZ)     $cols[] = bt($C_AZ).'     AS az';
if ($C_RO)     $cols[] = bt($C_RO).'     AS ro';

$sql = "SELECT ".implode(',', $cols)." FROM peleas_evento WHERE ";
if (has_col($conexion,'peleas_evento','evento_id')) {
  $sql .= "evento_id=? ";
} else {
  // fallback: por si tuvieran otra columna (improbable)
  $sql .= "1=1 AND 0=1 ";
}
$order = $C_NUM ? " ORDER BY ".bt($C_NUM)." ASC, id ASC" : " ORDER BY id ASC";
$sql .= $order;

$ids_comp = [];
if ($st=$conexion->prepare($sql)) {
  $st->bind_param('i',$evento_id);
  $st->execute(); $res=$st->get_result();
  while($row = $res->fetch_assoc()){
    $peleas[] = $row;
    if (!empty($row['az'])) $ids_comp[] = (int)$row['az'];
    if (!empty($row['ro'])) $ids_comp[] = (int)$row['ro'];
  }
  $st->close();
}

/* ===== Datos de competidores_evento (batch) ===== */
$mapComp = []; // id_comp => [nom, escuela, logo, division, peso, modalidad]
if ($ids_comp && table_exists($conexion, 'competidores_evento')) {
  $C_ESCUELA = has_col($conexion,'competidores_evento','escuela') ? 'escuela'
              : (has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre'
              : (has_col($conexion,'competidores_evento','gimnasio') ? 'gimnasio' : null));
  $LOGO_CANDS = ['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela'];
  $C_LOGO = null; foreach($LOGO_CANDS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }

  $haveDV  = table_exists($conexion,'divisiones_evento');
  $haveCP  = table_exists($conexion,'categorias_peso_evento');
  $haveMD  = table_exists($conexion,'modalidades_evento');

  $C_DIV_ID  = has_col($conexion,'competidores_evento','division_id') ? 'division_id' : (has_col($conexion,'competidores_evento','id_division')?'id_division':null);
  $C_DIV_TXT = has_col($conexion,'competidores_evento','division') ? 'division' : null;

  $C_PESO_ID  = has_col($conexion,'competidores_evento','categoria_peso_id') ? 'categoria_peso_id' : (has_col($conexion,'competidores_evento','id_categoria_peso')?'id_categoria_peso':null);
  $C_PESO_TXT = has_col($conexion,'competidores_evento','peso_kg') ? 'peso_kg' : (has_col($conexion,'competidores_evento','peso') ? 'peso' : (has_col($conexion,'competidores_evento','categoria_peso')?'categoria_peso':null));

  $C_MOD_ID  = has_col($conexion,'competidores_evento','modalidad_id') ? 'modalidad_id' : null;
  $C_MOD_TXT = has_col($conexion,'competidores_evento','modalidad') ? 'modalidad' : null;

  $sel = "ce.id, TRIM(CONCAT(COALESCE(ce.apellido,''),' ',COALESCE(ce.nombre,''))) AS nom";
  $sel .= $C_ESCUELA?(", ce.".bt($C_ESCUELA)." AS esc") : ", NULL AS esc";
  $sel .= $C_LOGO?(", ce.".bt($C_LOGO)." AS logo") : ", NULL AS logo";

  if ($haveDV && $C_DIV_ID)  { $sel .= ", dv.nombre AS division"; }
  elseif ($C_DIV_TXT)        { $sel .= ", ce.".bt($C_DIV_TXT)." AS division"; }
  else                       { $sel .= ", NULL AS division"; }

  if ($haveCP && $C_PESO_ID) { $sel .= ", cp.nombre AS peso"; }
  elseif ($C_PESO_TXT)       { $sel .= ", ce.".bt($C_PESO_TXT)." AS peso"; }
  else                       { $sel .= ", NULL AS peso"; }

  if ($haveMD && $C_MOD_ID)  { $sel .= ", md.nombre AS modalidad"; }
  elseif ($C_MOD_TXT)        { $sel .= ", ce.".bt($C_MOD_TXT)." AS modalidad"; }
  else                       { $sel .= ", NULL AS modalidad"; }

  $joins = "";
  if ($haveDV && $C_DIV_ID)  $joins .= " LEFT JOIN divisiones_evento dv ON dv.id = ce.".bt($C_DIV_ID);
  if ($haveCP && $C_PESO_ID) $joins .= " LEFT JOIN categorias_peso_evento cp ON cp.id = ce.".bt($C_PESO_ID);
  if ($haveMD && $C_MOD_ID)  $joins .= " LEFT JOIN modalidades_evento md ON md.id = ce.".bt($C_MOD_ID);

  $ph = implode(',', array_fill(0, count($ids_comp), '?'));
  $typ = str_repeat('i', count($ids_comp));
  $sqlC = "SELECT $sel FROM competidores_evento ce $joins WHERE ce.id IN ($ph)";
  if ($st = $conexion->prepare($sqlC)) {
    $st->bind_param($typ, ...$ids_comp);
    $st->execute(); $st->store_result();
    $st->bind_result($cid,$nom,$esc,$logo,$division,$peso,$modalidad);
    while($st->fetch()){
      $mapComp[(int)$cid] = [
        'nom' => (string)($nom??''),
        'esc' => (string)($esc??''),
        'logo'=> (string)($logo??''),
        'division'=> (string)($division??''),
        'peso'=> (string)($peso??''),
        'modalidad'=> (string)($modalidad??''),
      ];
    }
    $st->close();
  }
}

/* ===== HTML ===== */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🥊 Cartelera — <?= h($evento_titulo) ?></title>
<style>
  :root{ --bg:#0b0e12; --panel:#0F1216; --br:#1f2a35; --muted:#a9bacb; --gold:#ffd600; }
  body{background:var(--bg);color:#eaf2fb;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  h1{margin:0 0 4px;font-size:28px}
  .sub{color:var(--muted);font-size:14px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px}
  @media (max-width:1000px){ .grid{grid-template-columns:1fr} }

  .fight{background:var(--panel);border:1px solid var(--br);border-radius:14px;overflow:hidden}
  .hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--br);background:#0c1116}
  .hdr .nro{font-weight:900;letter-spacing:.5px}
  .hdr .estado{font-size:12px;color:#cfe2ff;padding:4px 8px;border:1px solid #29445b;border-radius:999px;background:#11202c}
  .hdr .estado.fin{background:#23151a;color:#ffd7dd;border-color:#4e1b29}
  .hdr .estado.juego{background:#112317;color:#d9ffdf;border-color:#224a2d}

  .cols{display:grid;grid-template-columns:1fr 1fr;gap:0}
  .col{padding:12px}
  .red{background:linear-gradient(#250f12,#14090b)}
  .blue{background:linear-gradient(#0b1b2a,#081219)}
  .corner{font-weight:800;margin-bottom:6px;letter-spacing:.5px;opacity:.9}
  .name{font-size:20px;font-weight:900}
  .esc{display:flex;gap:10px;align-items:center;margin-top:8px}
  .esc img{width:52px;height:52px;object-fit:contain;background:#0b0e12;border-radius:10px;border:1px solid #213142}
  .tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .tag{padding:5px 10px;border-radius:999px;background:#121a1f;border:1px solid #2a3a4a;font-size:12px}
  .foot{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-top:1px solid #1f2a35;background:#0c1116}
  .gan{padding:6px 10px;border-radius:999px;border:1px solid #2a3a4a}
  .win-blue{background:#0b5aa6;color:#fff;border-color:#0b5aa6}
  .win-red{background:#a60b1f;color:#fff;border-color:#a60b1f}
  .link{font-size:13px;color:#9fc7ff;text-decoration:none;border-bottom:1px dashed #3a5a8a}
  .link:hover{opacity:.9}
</style>
</head>
<body>
<div class="wrap">
  <h1><?= h($evento_titulo) ?></h1>
  <div class="sub">
    <?php if ($evento_fecha): ?>📅 <?= h($evento_fecha) ?> · <?php endif; ?>
    <?php if ($evento_lugar): ?>📍 <?= h($evento_lugar) ?><?php endif; ?>
  </div>

  <?php if (!$peleas): ?>
    <div class="sub" style="margin-top:12px">No hay peleas cargadas todavía.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($peleas as $p):
        $pid = (int)$p['id'];
        $num = isset($p['num']) && $p['num']!=='' ? (int)$p['num'] : null;
        $estado = strtolower((string)($p['estado'] ?? ''));
        $gcol = strtolower((string)($p['ganador_color'] ?? ''));

        $az = isset($p['az']) ? (int)$p['az'] : null;
        $ro = isset($p['ro']) ? (int)$p['ro'] : null;

        $azD = $az && isset($mapComp[$az]) ? $mapComp[$az] : ['nom'=>'Azul','esc'=>'','logo'=>'','division'=>'','peso'=>'','modalidad'=>''];
        $roD = $ro && isset($mapComp[$ro]) ? $mapComp[$ro] : ['nom'=>'Rojo','esc'=>'','logo'=>'','division'=>'','peso'=>'','modalidad'=>''];

        $clsEstado = ($estado==='finalizada' || $estado==='finalizado') ? 'estado fin' : (($estado==='en juego' || $estado==='en_juego' || $estado==='activo') ? 'estado juego' : 'estado');
      ?>
      <article class="fight">
        <div class="hdr">
          <div class="nro">Pelea <?= $num ? ('N° '.(int)$num) : ('#'.$pid) ?></div>
          <div class="<?= h($clsEstado) ?>"><?= $estado ? h($estado) : 'pendiente' ?></div>
        </div>

        <div class="cols">
          <!-- ROJO -->
          <div class="col red">
            <div class="corner">🔴 RINCÓN ROJO</div>
            <div class="name"><?= h($roD['nom']) ?></div>
            <?php if ($roD['esc'] || $roD['logo']): ?>
              <div class="esc">
                <?php if ($roD['logo']): ?><img src="<?= h($roD['logo']) ?>" alt="Escuela roja" loading="lazy"><?php endif; ?>
                <?php if ($roD['esc']): ?><div><b><?= h($roD['esc']) ?></b></div><?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="tags">
              <?php if ($roD['division']): ?><span class="tag">División: <?= h($roD['division']) ?></span><?php endif; ?>
              <?php if ($roD['peso']!==''): ?>
                <span class="tag">Peso: <?= h(is_numeric($roD['peso'])?(0+$roD['peso']).' kg':$roD['peso']) ?></span>
              <?php endif; ?>
              <?php if ($roD['modalidad']): ?><span class="tag">Modalidad: <?= h($roD['modalidad']) ?></span><?php endif; ?>
            </div>
          </div>

          <!-- AZUL -->
          <div class="col blue">
            <div class="corner">🔵 RINCÓN AZUL</div>
            <div class="name"><?= h($azD['nom']) ?></div>
            <?php if ($azD['esc'] || $azD['logo']): ?>
              <div class="esc">
                <?php if ($azD['logo']): ?><img src="<?= h($azD['logo']) ?>" alt="Escuela azul" loading="lazy"><?php endif; ?>
                <?php if ($azD['esc']): ?><div><b><?= h($azD['esc']) ?></b></div><?php endif; ?>
              </div>
            <?php endif; ?>
            <div class="tags">
              <?php if ($azD['division']): ?><span class="tag">División: <?= h($azD['division']) ?></span><?php endif; ?>
              <?php if ($azD['peso']!==''): ?>
                <span class="tag">Peso: <?= h(is_numeric($azD['peso'])?(0+$azD['peso']).' kg':$azD['peso']) ?></span>
              <?php endif; ?>
              <?php if ($azD['modalidad']): ?><span class="tag">Modalidad: <?= h($azD['modalidad']) ?></span><?php endif; ?>
            </div>
          </div>
        </div>

        <div class="foot">
          <div>
            <?php if ($gcol==='azul'): ?>
              <span class="gan win-blue">Ganador: 🔵 Azul</span>
            <?php elseif ($gcol==='rojo'): ?>
              <span class="gan win-red">Ganador: 🔴 Rojo</span>
            <?php elseif ($gcol==='empate'): ?>
              <span class="gan">Ganador: ⚖️ Empate</span>
            <?php else: ?>
              <span class="gan">Ganador: —</span>
            <?php endif; ?>
          </div>
          <div>
            <a class="link" href="en_vivo.php?overlay=1&pelea_id=<?= (int)$pid ?>" target="_blank">Abrir overlay de esta pelea</a>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
