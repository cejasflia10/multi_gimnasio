<?php
/* ============================================================
   vivo.php — Página pública para compartir el vivo del evento
   - ?evento_id=ID   (requerido)
   - Toma youtube_live_id de eventos_deportivos
   - Lee pelea_actual y datos desde combate_estado/peleas_evento
   - NO toca nada de la mesa/overlay que ya funciona
   ============================================================ */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin BD'); }
if (function_exists('mysqli_report')) mysqli_report(MYSQLI_REPORT_OFF);
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',(string)$c).'`'; }
function table_exists(mysqli $db, string $name): bool {
  $name = $db->real_escape_string($name);
  if ($r = $db->query("SHOW TABLES LIKE '$name'")) { $ok = (bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}
function has_col(mysqli $db, string $t, string $c): bool {
  $t=$db->real_escape_string($t); $c=$db->real_escape_string($c);
  $sql="SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND COLUMN_NAME='$c' LIMIT 1";
  if ($r=$db->query($sql)){ $ok=(bool)$r->num_rows; $r->close(); return $ok; }
  return false;
}

/* ===== Param ===== */
$evento_id = (int)($_GET['evento_id'] ?? 0);
if ($evento_id<=0){ http_response_code(400); echo 'Falta evento_id'; exit; }

/* ===== Datos del evento (YouTube) ===== */
$yt_id = '';
$ev_titulo = 'Evento';
$ev_fecha = '';
$ev_lugar = '';

if (table_exists($conexion,'eventos_deportivos')){
  $cols = ['id'];
  if (has_col($conexion,'eventos_deportivos','youtube_live_id')) $cols[]='youtube_live_id';
  if (has_col($conexion,'eventos_deportivos','titulo')) $cols[]='titulo';
  if (has_col($conexion,'eventos_deportivos','fecha')) $cols[]='fecha';
  if (has_col($conexion,'eventos_deportivos','lugar')) $cols[]='lugar';
  $sql = "SELECT ".implode(',', array_map('bt',$cols))." FROM eventos_deportivos WHERE id=? LIMIT 1";
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('i',$evento_id);
    $st->execute(); $r=$st->get_result(); if($row=$r->fetch_assoc()){
      $yt_id = (string)($row['youtube_live_id'] ?? '');
      $ev_titulo = (string)($row['titulo'] ?? $ev_titulo);
      $ev_fecha = (string)($row['fecha'] ?? '');
      $ev_lugar = (string)($row['lugar'] ?? '');
    }
    $st->close();
  }
}

/* ===== Pelea actual desde combate_estado (si existe) ===== */
$pelea_actual_id = 0;
if (table_exists($conexion,'combate_estado')){
  if ($st=$conexion->prepare("SELECT pelea_actual_id FROM combate_estado WHERE evento_id=? LIMIT 1")){
    $st->bind_param('i',$evento_id);
    $st->execute(); $st->bind_result($pid); if($st->fetch()){ $pelea_actual_id = (int)$pid; }
    $st->close();
  }
}

/* ===== Listado de peleas (para la grilla) ===== */
$peleas = [];
if (table_exists($conexion,'peleas_evento')){
  $C_NUM = has_col($conexion,'peleas_evento','numero') ? 'numero' : (has_col($conexion,'peleas_evento','nro')?'nro':(has_col($conexion,'peleas_evento','orden')?'orden':null));
  $C_AZ  = has_col($conexion,'peleas_evento','competidor_azul_id') ? 'competidor_azul_id' : (has_col($conexion,'peleas_evento','azul_id')?'azul_id':null);
  $C_RO  = has_col($conexion,'peleas_evento','competidor_rojo_id') ? 'competidor_rojo_id' : (has_col($conexion,'peleas_evento','rojo_id')?'rojo_id':null);
  $C_MOD = has_col($conexion,'peleas_evento','modalidad') ? 'modalidad' : null;
  $C_DIV = has_col($conexion,'peleas_evento','division') ? 'division' : null;

  $sel = "id";
  if ($C_NUM) $sel .= ", ".bt($C_NUM)." AS num";
  if ($C_AZ)  $sel .= ", ".bt($C_AZ)." AS az";
  if ($C_RO)  $sel .= ", ".bt($C_RO)." AS ro";
  if ($C_MOD) $sel .= ", ".bt($C_MOD)." AS modalidad";
  if ($C_DIV) $sel .= ", ".bt($C_DIV)." AS division";

  $sql = "SELECT $sel FROM peleas_evento WHERE evento_id=? ORDER BY ".($C_NUM? 'num':'id');
  if ($st=$conexion->prepare($sql)){
    $st->bind_param('i',$evento_id);
    $st->execute(); $rs=$st->get_result();
    while($row=$rs->fetch_assoc()){ $peleas[]=$row; }
    $st->close();
  }

  // Cargar nombres de competidores si hay tabla
  if ($peleas && table_exists($conexion,'competidores_evento')){
    $ids=[]; foreach($peleas as $p){ if(!empty($p['az']))$ids[]=(int)$p['az']; if(!empty($p['ro']))$ids[]=(int)$p['ro']; }
    $ids = array_values(array_unique(array_filter($ids)));
    if ($ids){
      $ph = implode(',', array_fill(0,count($ids),'?'));
      $typ = str_repeat('i', count($ids));
      $sqlC = "SELECT id, TRIM(CONCAT(COALESCE(apellido,''),' ',COALESCE(nombre,''))) AS nom FROM competidores_evento WHERE id IN ($ph)";
      $map=[]; if ($st=$conexion->prepare($sqlC)){
        $st->bind_param($typ, ...$ids);
        $st->execute(); $st->bind_result($cid,$nom);
        while($st->fetch()) $map[(int)$cid]=$nom;
        $st->close();
      }
      foreach($peleas as &$p){
        $p['az_nom'] = isset($map[(int)($p['az']??0)]) ? $map[(int)$p['az']] : 'Azul';
        $p['ro_nom'] = isset($map[(int)($p['ro']??0)]) ? $map[(int)$p['ro']] : 'Rojo';
      }
      unset($p);
    }
  }
}

$share_url = (isset($_SERVER['REQUEST_SCHEME'])?$_SERVER['REQUEST_SCHEME']:'https').'://'.($_SERVER['HTTP_HOST'] ?? '').'/vivo.php?evento_id='.$evento_id;

/* ===== HTML ===== */
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>🔴 En vivo — <?= h($ev_titulo) ?></title>
<style>
  body{margin:0;background:#0b0e12;color:#eaf2ff;font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif}
  .wrap{max-width:1200px;margin:0 auto;padding:14px}
  .card{background:#11161d;border:1px solid #223040;border-radius:14px;padding:14px;margin-bottom:12px}
  .row{display:grid;grid-template-columns:2fr 1fr;gap:12px}
  @media (max-width:1024px){ .row{grid-template-columns:1fr} }
  .yt{position:relative;padding-top:56.25%;background:#000;border-radius:12px;overflow:hidden}
  .yt iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
  .title{font-weight:800;font-size:20px;margin:0 0 8px}
  .muted{color:#9fb2c6}
  .pill{display:inline-block;background:#17212b;border:1px solid #2c3a48;border-radius:999px;padding:6px 10px;margin-right:6px}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid #223040;padding:8px;text-align:left}
  .corner{font-weight:800}
  .az{color:#77b2ff}
  .ro{color:#ff8a8a}
  .qr{display:flex;align-items:center;gap:10px}
  .qr img{width:92px;height:92px}
  .now{font-weight:800;letter-spacing:.3px}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <div class="title">🔴 En vivo — <?= h($ev_titulo) ?></div>
    <div class="muted"><?= h($ev_lugar) ?> <?= $ev_fecha?('· '.h($ev_fecha)) : '' ?></div>
  </div>

  <div class="row">
    <div class="card">
      <div class="yt">
        <?php if ($yt_id): ?>
          <iframe src="https://www.youtube.com/embed/<?= h($yt_id) ?>?autoplay=1" allow="autoplay; encrypted-media" allowfullscreen></iframe>
        <?php else: ?>
          <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#8aa0b8">Sin YouTube configurado</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="title" style="margin-bottom:6px;">⏱️ Pelea actual</div>
      <div id="nowFight" class="now">Buscando…</div>
      <div class="muted" id="nowExtra"></div>
      <hr style="border:none;border-top:1px solid #223040; margin:10px 0">
      <div class="title" style="margin-bottom:6px;">📲 Compartir</div>
      <div class="qr">
        <?php
          $qr = "https://chart.googleapis.com/chart?cht=qr&chs=190x190&chld=L|0&chl=".rawurlencode($share_url);
        ?>
        <img src="<?= h($qr) ?>" alt="QR">
        <div>
          <div class="muted">Link del evento</div>
          <div><a href="<?= h($share_url) ?>" target="_blank" style="color:#9fd3ff"><?= h($share_url) ?></a></div>
          <div class="muted" style="margin-top:4px">Escaneá el QR para ver el vivo</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="title">📋 Cartelera</div>
    <table>
      <thead><tr><th>#</th><th>Azul</th><th>Rojo</th><th>Modalidad</th><th>División</th></tr></thead>
      <tbody>
        <?php if(!$peleas){ ?>
          <tr><td colspan="5" class="muted">Sin peleas cargadas</td></tr>
        <?php } else { foreach($peleas as $p){ ?>
          <tr<?= ($pelea_actual_id && (int)$p['id'] === (int)$pelea_actual_id) ? ' style="background:#0f1720"' : '' ?>>
            <td><?= (int)($p['num'] ?? $p['id']) ?></td>
            <td class="corner az">🔵 <?= h($p['az_nom'] ?? 'Azul') ?></td>
            <td class="corner ro">🔴 <?= h($p['ro_nom'] ?? 'Rojo') ?></td>
            <td class="muted"><?= h($p['modalidad'] ?? '') ?></td>
            <td class="muted"><?= h($p['division'] ?? '') ?></td>
          </tr>
        <?php }} ?>
      </tbody>
    </table>
  </div>
</div>

<script>
/* ====== Refresca “Pelea actual” usando el mismo estado que overlay ====== */
const eventoId = <?= (int)$evento_id ?>;
let peleaId = <?= (int)$pelea_actual_id ?>;

async function loadEstado(){
  // 1) Si no sabemos la pelea aún, la pedimos a combate_estado (ligero)
  if (!peleaId){
    try{
      const r = await fetch('combate_estado_pelea_actual.php?evento_id='+eventoId, {cache:'no-store'});
      if (r.ok){ const j = await r.json(); if(j && j.ok && j.pelea_id) peleaId = j.pelea_id; }
    }catch(e){}
  }
  if (!peleaId) { setTimeout(loadEstado, 1200); return; }

  // 2) Leemos el estado completo (mismo que usa overlay)
  try{
    const r = await fetch('combate_en_vivo.php?ajax=estado&pelea_id='+peleaId, {cache:'no-store'});
    const j = await r.json();
    if (j && j.ok && j.data){
      const d = j.data;
      // si cambió la pelea actual, lo tomamos
      if (d.timer && d.pelea_id && d.pelea_id !== peleaId) peleaId = d.pelea_id;

      const az = d.azul?.nombre || 'Azul';
      const ro = d.rojo?.nombre || 'Rojo';
      const mod = d.rojo?.modalidad || d.azul?.modalidad || '';
      const esc1 = d.azul?.escuela || '';
      const esc2 = d.rojo?.escuela || '';
      const rlabel = (d.timer?.ronda || 1);

      const now = document.getElementById('nowFight');
      const ex  = document.getElementById('nowExtra');
      now.textContent = `R${rlabel} — 🔵 ${az} vs 🔴 ${ro}`;
      ex.textContent  = [esc1 && `Azul: ${esc1}`, esc2 && `Rojo: ${esc2}`, mod].filter(Boolean).join(' · ');
    }
  }catch(e){}
  setTimeout(loadEstado, 1000);
}
loadEstado();
</script>
</body>
</html>
