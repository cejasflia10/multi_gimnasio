<?php
/* ==========================================================
   EN VIVO — Página pública con YouTube + info de la pelea
   - Acepta ?ytid=VIDEO_ID (prioridad)
   - O intenta leer el YouTube del evento (?evento_id, ?evento, ?id_evento)
   - Muestra paneles Azul/Rojo (sin “logo feo” si no hay imagen)
   - Polling cada 5s a combate_en_vivo.php?ajax=estado&pelea_id=...
   ========================================================== */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma', 'no-cache');
header('Expires', '0');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
@$conexion->set_charset('utf8mb4');
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }

/* ============== Helpers ============== */
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
function get_str_qs(array $src, string $key, ?string $def=null): ?string {
  if (!isset($src[$key])) return $def;
  $v = trim((string)$src[$key]);
  return $v === '' ? $def : $v;
}
function youtube_id_from_url(string $u): ?string {
  // Soporta https://youtube.com/live/ID ... o watch?v=ID
  if (preg_match('~youtube\.com/(?:live/|watch\?v=)([A-Za-z0-9_\-]{6,})~i', $u, $m)) return $m[1];
  if (preg_match('~youtu\.be/([A-Za-z0-9_\-]{6,})~i', $u, $m)) return $m[1];
  return null;
}

/* ============== Entrada (QS) ============== */
$evento_id = null;
foreach (['evento_id','evento','id_evento'] as $k) {
  $tmp = get_int_qs($_GET, $k, null);
  if (!is_null($tmp)) { $evento_id = $tmp; break; }
}
$pelea_id = get_int_qs($_GET, 'pelea_id', null);
$ytid     = get_str_qs($_GET, 'ytid', null); // prioriza si viene

/* ============== Buscar YouTube si no vino ?ytid ============== */
if (!$ytid && !is_null($evento_id) && table_exists($conexion,'eventos')) {
  // columnas candidatas posibles
  $cands = ['youtube_live_id','youtube_id','youtube','youtube_url','link_youtube','url_youtube','live_url'];
  $present = [];
  foreach ($cands as $c) if (has_col($conexion,'eventos',$c)) $present[] = $c;

  if ($present) {
    $sql = "SELECT ".implode(',', array_map('bt',$present))." FROM eventos WHERE id=? LIMIT 1";
    if ($st = $conexion->prepare($sql)) {
      $st->bind_param('i', $evento_id);
      $st->execute(); $res = $st->get_result();
      if ($row = $res->fetch_assoc()) {
        // Buscar el primer campo que tenga valor
        foreach ($present as $c) {
          $v = trim((string)($row[$c] ?? ''));
          if ($v !== '') {
            // si es URL completa, parsear; si no, tomar como ID
            $ytid = youtube_id_from_url($v) ?: $v;
            break;
          }
        }
      }
      $st->close();
    }
  }
}

/* ============== Info de pelea / competidores (si hay pelea_id) ============== */
$azul_nom='Azul'; $rojo_nom='Rojo';
$azul_escuela=''; $rojo_escuela='';
$azul_logo=''; $rojo_logo='';
$rojo_div=''; $azul_div='';
$rojo_peso=''; $azul_peso='';
$rojo_mod='';  $azul_mod='';
$estado_txt='en juego';
$ganador_color=null;

if (!is_null($pelea_id) && table_exists($conexion,'peleas_evento')) {
  // detectar columnas
  $C_AZUL_ID = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_ROJO_ID = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_ESTADO  = has_col($conexion,'peleas_evento','estado') ? 'estado' : null;
  $C_GCOLOR  = has_col($conexion,'peleas_evento','ganador_color') ? 'ganador_color' : (has_col($conexion,'peleas_evento','ganador')?'ganador':null);

  $sel = ['id'];
  if ($C_AZUL_ID) $sel[] = bt($C_AZUL_ID).' AS az';
  if ($C_ROJO_ID) $sel[] = bt($C_ROJO_ID).' AS ro';
  if ($C_ESTADO)  $sel[] = bt($C_ESTADO).' AS est';
  if ($C_GCOLOR)  $sel[] = bt($C_GCOLOR).' AS gcolor';

  $sql = "SELECT ".implode(',', $sel)." FROM peleas_evento WHERE id=? LIMIT 1";
  if ($st=$conexion->prepare($sql)) {
    $st->bind_param('i', $pelea_id);
    $st->execute(); $r = $st->get_result();
    if ($row = $r->fetch_assoc()) {
      $az_id = isset($row['az']) ? (int)$row['az'] : null;
      $ro_id = isset($row['ro']) ? (int)$row['ro'] : null;
      if (!empty($row['est'])) $estado_txt = (string)$row['est'];
      if (!empty($row['gcolor'])) $ganador_color = strtolower((string)$row['gcolor']);

      // Cargar competidores_evento si existe
      $ids = [];
      if ($az_id) $ids[] = $az_id;
      if ($ro_id) $ids[] = $ro_id;

      if ($ids && table_exists($conexion,'competidores_evento')) {
        $C_ESCUELA = has_col($conexion,'competidores_evento','escuela_nombre') ? 'escuela_nombre' : (has_col($conexion,'competidores_evento','gimnasio')?'gimnasio':null);
        $LOGO_CANDS = ['escuela_logo','logo_escuela','logo_url','escudo_url','escuela_escudo','logo','foto_escuela'];
        $C_LOGO = null; foreach($LOGO_CANDS as $c){ if (has_col($conexion,'competidores_evento',$c)){ $C_LOGO=$c; break; } }

        $haveDV  = table_exists($conexion,'divisiones_evento');
        $haveCP  = table_exists($conexion,'categorias_peso_evento');
        $haveMD  = table_exists($conexion,'modalidades_evento');

        $C_DIV_ID  = has_col($conexion,'competidores_evento','division_id') ? 'division_id' : (has_col($conexion,'competidores_evento','id_division')?'id_division':null);
        $C_DIV_TXT = has_col($conexion,'competidores_evento','division') ? 'division' : null;

        $C_PESO_ID  = has_col($conexion,'competidores_evento','categoria_peso_id') ? 'categoria_peso_id' : (has_col($conexion,'competidores_evento','id_categoria_peso')?'id_categoria_peso':null);
        $C_PESO_TXT = has_col($conexion,'competidores_evento','peso') ? 'peso' : (has_col($conexion,'competidores_evento','categoria_peso')?'categoria_peso':null);

        $C_MOD_ID  = has_col($conexion,'competidores_evento','modalidad_id') ? 'modalidad_id' : null;
        $C_MOD_TXT = has_col($conexion,'competidores_evento','modalidad') ? 'modalidad' : null;

        $cols = "ce.id, TRIM(CONCAT(COALESCE(ce.apellido,''),' ',COALESCE(ce.nombre,''))) AS nom";
        $cols .= $C_ESCUELA?(", ce.".bt($C_ESCUELA)." AS esc") : ", NULL AS esc";
        $cols .= $C_LOGO?(", ce.".bt($C_LOGO)." AS logo") : ", NULL AS logo";

        if ($haveDV && $C_DIV_ID)  { $cols .= ", dv.nombre AS division"; }
        elseif ($C_DIV_TXT)        { $cols .= ", ce.".bt($C_DIV_TXT)." AS division"; }
        else                       { $cols .= ", NULL AS division"; }

        if ($haveCP && $C_PESO_ID) { $cols .= ", cp.nombre AS peso"; }
        elseif ($C_PESO_TXT)       { $cols .= ", ce.".bt($C_PESO_TXT)." AS peso"; }
        else                       { $cols .= ", NULL AS peso"; }

        if ($haveMD && $C_MOD_ID)  { $cols .= ", md.nombre AS modalidad"; }
        elseif ($C_MOD_TXT)        { $cols .= ", ce.".bt($C_MOD_TXT)." AS modalidad"; }
        else                       { $cols .= ", NULL AS modalidad"; }

        $joins = "";
        if ($haveDV && $C_DIV_ID)  $joins .= " LEFT JOIN divisiones_evento dv ON dv.id = ce.".bt($C_DIV_ID);
        if ($haveCP && $C_PESO_ID) $joins .= " LEFT JOIN categorias_peso_evento cp ON cp.id = ce.".bt($C_PESO_ID);
        if ($haveMD && $C_MOD_ID)  $joins .= " LEFT JOIN modalidades_evento md ON md.id = ce.".bt($C_MOD_ID);

        $ph  = implode(',', array_fill(0,count($ids),'?'));
        $typ = str_repeat('i', count($ids));
        $sql2 = "SELECT $cols FROM competidores_evento ce $joins WHERE ce.id IN ($ph)";
        if ($st2=$conexion->prepare($sql2)){
          $st2->bind_param($typ, ...$ids);
          $st2->execute(); $st2->store_result();
          $st2->bind_result($cid,$nom,$esc,$logo,$division,$peso,$modalidad);
          while($st2->fetch()){
            if ((int)$cid === (int)$az_id){
              if ($nom) $azul_nom = $nom;
              $azul_escuela = (string)($esc??'');
              $azul_logo = (string)($logo??'');
              $azul_div = (string)($division??''); $azul_peso = (string)($peso??''); $azul_mod = (string)($modalidad??'');
            } elseif ((int)$cid === (int)$ro_id){
              if ($nom) $rojo_nom = $nom;
              $rojo_escuela = (string)($esc??'');
              $rojo_logo = (string)($logo??'');
              $rojo_div = (string)($division??''); $rojo_peso = (string)($peso??''); $rojo_mod = (string)($modalidad??'');
            }
          }
          $st2->close();
        }
      }
    }
    $st->close();
  }
}

/* ============== HTML ============== */
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>🎥 En vivo<?= $pelea_id?(' — Pelea #'.(int)$pelea_id):'' ?></title>
<style>
  :root{ --panel-bg:#0F1216; --panel-br:#1f2a35; --muted:#a9bacb; }
  body{background:#0b0e12;color:#eaf2fb;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;margin:0}
  .wrap{max-width:1200px;margin:0 auto;padding:16px}
  .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  @media (max-width:900px){ .grid{grid-template-columns:1fr} }

  .panel{background:var(--panel-bg);border:1px solid var(--panel-br);border-radius:14px;padding:14px}
  .red{background:linear-gradient(#250f12,#14090b)}
  .blue{background:linear-gradient(#0b1b2a,#081219)}
  .corner{font-weight:800;margin-bottom:6px;letter-spacing:.5px;opacity:.9}
  .name{font-size:22px;font-weight:900}
  .sub{font-size:14px;color:var(--muted)}
  .esc{display:flex;gap:10px;align-items:center;margin-top:8px}
  .esc img{width:54px;height:54px;object-fit:contain;background:#0b0e12;border-radius:10px;border:1px solid #213142}
  .tags{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
  .tag{padding:5px 10px;border-radius:999px;background:#121a1f;border:1px solid #2a3a4a;font-size:12px}
  .status{margin:10px 0 12px}
  .badge{padding:6px 10px;border-radius:999px;background:#121a24;border:1px solid #2a3a4a;margin-right:8px}
  .win-blue{background:#0b5aa6;color:#fff;border-color:#0b5aa6}
  .win-red {background:#a60b1f;color:#fff;border-color:#a60b1f}

  /* Player responsivo 16:9 */
  .player{position:relative;width:100%;padding-top:56.25%;border-radius:12px;overflow:hidden;border:1px solid #1f2a35;background:#000}
  .player iframe{position:absolute;top:0;left:0;width:100%;height:100%;border:0}

  .warn{max-width:900px;margin:10px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px}
</style>
</head>
<body>
<div class="wrap">
  <h2 style="margin:0 0 10px">🎥 Transmisión en vivo <?= $evento_id?('· Evento #'.(int)$evento_id):'' ?> <?= $pelea_id?('· Pelea #'.(int)$pelea_id):'' ?></h2>

  <?php if (!$ytid): ?>
    <div class="warn">
      ⚠️ Falta configurar el Live de YouTube.<br>
      Pasá <code>?ytid=VIDEO_ID</code> en la URL
      <?= $evento_id? ' o cargá el campo de YouTube en el evento #'.(int)$evento_id : '' ?>.
    </div>
  <?php endif; ?>

  <!-- Player -->
  <div class="player" style="margin-bottom:14px;">
    <?php if ($ytid): ?>
      <iframe
        src="https://www.youtube.com/embed/<?= h($ytid) ?>?autoplay=1&rel=0"
        title="YouTube live" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
        allowfullscreen></iframe>
    <?php else: ?>
      <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#9fb0c2">
        <div>Sin YouTube configurado</div>
      </div>
    <?php endif; ?>
  </div>

  <!-- Paneles competidores -->
  <div class="grid">
    <section class="panel red">
      <div class="corner">🔴 RINCÓN ROJO</div>
      <div class="name"><?= h($rojo_nom) ?></div>
      <?php if ($rojo_escuela || $rojo_logo): ?>
        <div class="esc">
          <?php if ($rojo_logo): ?><img src="<?= h($rojo_logo) ?>" alt="Escuela roja" loading="lazy"><?php endif; ?>
          <?php if ($rojo_escuela): ?><div class="sub"><b><?= h($rojo_escuela) ?></b></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($rojo_div): ?><span class="tag">División: <?= h($rojo_div) ?></span><?php endif; ?>
        <?php if ($rojo_peso): ?><span class="tag">Peso: <?= h($rojo_peso) ?></span><?php endif; ?>
        <?php if ($rojo_mod):  ?><span class="tag">Modalidad: <?= h($rojo_mod)  ?></span><?php endif; ?>
      </div>
    </section>

    <section class="panel blue">
      <div class="corner">🔵 RINCÓN AZUL</div>
      <div class="name"><?= h($azul_nom) ?></div>
      <?php if ($azul_escuela || $azul_logo): ?>
        <div class="esc">
          <?php if ($azul_logo): ?><img src="<?= h($azul_logo) ?>" alt="Escuela azul" loading="lazy"><?php endif; ?>
          <?php if ($azul_escuela): ?><div class="sub"><b><?= h($azul_escuela) ?></b></div><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="tags">
        <?php if ($azul_div): ?><span class="tag">División: <?= h($azul_div) ?></span><?php endif; ?>
        <?php if ($azul_peso): ?><span class="tag">Peso: <?= h($azul_peso) ?></span><?php endif; ?>
        <?php if ($azul_mod):  ?><span class="tag">Modalidad: <?= h($azul_mod)  ?></span><?php endif; ?>
      </div>
    </section>
  </div>

  <!-- Estado -->
  <div class="status">
    <span class="badge" id="lblEstado">Estado: <?= h($estado_txt) ?></span>
    <span class="badge <?= $ganador_color==='azul'?'win-blue':'' ?> <?= $ganador_color==='rojo'?'win-red':'' ?>" id="lblGanador">
      <?php
        if ($ganador_color==='azul') echo 'Ganador: 🔵 Azul';
        elseif ($ganador_color==='rojo') echo 'Ganador: 🔴 Rojo';
        else echo 'Ganador: —';
      ?>
    </span>
  </div>
</div>

<script>
(function(){
  const peleaId = <?= is_null($pelea_id)?'null':(int)$pelea_id ?>;
  if (!peleaId) return; // nada que monitorear

  const lblEstado  = document.getElementById('lblEstado');
  const lblGanador = document.getElementById('lblGanador');

  async function pollEstado(){
    try{
      const url = 'combate_en_vivo.php?ajax=estado&pelea_id='+encodeURIComponent(peleaId);
      const r = await fetch(url, {cache:'no-store'});
      const j = await r.json();
      if (!j || !j.ok) return;

      const est = (j.data && j.data.estado) ? String(j.data.estado) : 'en juego';
      lblEstado.textContent = 'Estado: ' + est;

      const g = (j.data && j.data.ganador_color) ? String(j.data.ganador_color).toLowerCase() : '';
      lblGanador.classList.remove('win-blue','win-red');
      if (g === 'azul'){
        lblGanador.classList.add('win-blue'); lblGanador.textContent = 'Ganador: 🔵 Azul';
      } else if (g === 'rojo'){
        lblGanador.classList.add('win-red');  lblGanador.textContent = 'Ganador: 🔴 Rojo';
      } else if (g === 'empate'){
        lblGanador.textContent = 'Ganador: ⚖️ Empate';
      } else {
        lblGanador.textContent = 'Ganador: —';
      }
    }catch(e){ /* silencio */ }
  }
  pollEstado();
  setInterval(pollEstado, 5000);
})();
</script>
</body>
</html>
