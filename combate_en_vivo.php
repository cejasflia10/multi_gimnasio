<?php
/* ===========================
   COMBATE EN VIVO — COMPLETO
   =========================== */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* === DEBUG/ANTICACHE — quitá esto al finalizar === */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }
$__BUILD = @filemtime(__FILE__) ?: time();
/* ================================================ */

if (!isset($conexion) || !($conexion instanceof mysqli)) { http_response_code(500); exit('❌ Sin conexión a BD.'); }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function bt($c){ return '`'.str_replace('`','``',$c).'`'; }

$pelea_id = isset($_GET['pelea_id']) && is_numeric($_GET['pelea_id']) ? (int)$_GET['pelea_id'] : 0;
if ($pelea_id <= 0) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Falta <b>pelea_id</b>.</div>';
  exit;
}

/* Detectar columnas de peleas_evento */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM `peleas_evento`");
if (!$res) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>';
  exit;
}
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$res->close();
$pick = function(array $cands) use ($cols){ foreach($cands as $c){ $lc=strtolower($c); if(isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_EVENTO = $pick(['evento_id','id_evento','evento']);
$C_ROJO   = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL   = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS = $pick(['rondas']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Faltan columnas obligatorias en <b>peleas_evento</b> (evento_id, competidor_rojo_id, competidor_azul_id).</div>';
  exit;
}

/* Traer info pelea + competidores */
$colE = bt($C_EVENTO);
$colR = bt($C_ROJO);
$colA = bt($C_AZUL);
$selRondas = $C_RONDAS ? "p.".bt($C_RONDAS)." AS rondas," : "NULL AS rondas,";

$sql = "
  SELECT
    p.id AS pelea_id, p.$colE AS evento_id, $selRondas
    p.$colR AS rojo_id, p.$colA AS azul_id,

    cr.apellido AS r_apellido, cr.nombre AS r_nombre, cr.escuela_nombre AS r_escuela,
    cr.foto_competidor AS r_foto, cr.edad AS r_edad,
    mr.nombre AS r_modalidad, dvr.nombre AS r_division, cpr.nombre AS r_peso,

    ca.apellido AS a_apellido, ca.nombre AS a_nombre, ca.escuela_nombre AS a_escuela,
    ca.foto_competidor AS a_foto, ca.edad AS a_edad,
    ma.nombre AS a_modalidad, dva.nombre AS a_division, cpa.nombre AS a_peso
  FROM `peleas_evento` p
  JOIN `competidores_evento` cr ON p.$colR = cr.id
  JOIN `competidores_evento` ca ON p.$colA = ca.id
  LEFT JOIN `modalidades_evento`     mr ON mr.id = cr.modalidad_id
  LEFT JOIN `divisiones_evento`      dvr ON dvr.id = cr.division_id
  LEFT JOIN `categorias_peso_evento` cpr ON cpr.id = cr.categoria_peso_id
  LEFT JOIN `modalidades_evento`     ma ON ma.id = ca.modalidad_id
  LEFT JOIN `divisiones_evento`      dva ON dva.id = ca.division_id
  LEFT JOIN `categorias_peso_evento` cpa ON cpa.id = ca.categoria_peso_id
  WHERE p.id = ?
  LIMIT 1
";
$st = $conexion->prepare($sql);
if (!$st) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error preparando SQL: '.h($conexion->error).'</div>'; exit; }
$st->bind_param('i', $pelea_id);
$st->execute();
$st->bind_result(
  $X_pelea_id, $X_evento_id, $X_rondas,
  $X_rojo_id,  $X_azul_id,
  $r_apellido, $r_nombre, $r_escuela, $r_foto, $r_edad, $r_modalidad, $r_division, $r_peso,
  $a_apellido, $a_nombre, $a_escuela, $a_foto, $a_edad, $a_modalidad, $a_division, $a_peso
);
$ok = $st->fetch(); $st->close();
if (!$ok) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se encontró la pelea.</div>'; exit; }

$info = [
  'pelea_id'=>$X_pelea_id, 'evento_id'=>$X_evento_id, 'rondas'=>$X_rondas,
  'r_apellido'=>$r_apellido, 'r_nombre'=>$r_nombre, 'r_escuela'=>$r_escuela,
  'r_foto'=>$r_foto, 'r_edad'=>$r_edad, 'r_modalidad'=>$r_modalidad,
  'r_division'=>$r_division, 'r_peso'=>$r_peso,
  'a_apellido'=>$a_apellido, 'a_nombre'=>$a_nombre, 'a_escuela'=>$a_escuela,
  'a_foto'=>$a_foto, 'a_edad'=>$a_edad, 'a_modalidad'=>$a_modalidad,
  'a_division'=>$a_division, 'a_peso'=>$a_peso
];

$phUser = 'assets/placeholder-user.png';
$rFoto = !empty($info['r_foto']) ? $info['r_foto'] : $phUser;
$aFoto = !empty($info['a_foto']) ? $info['a_foto'] : $phUser;

$rondasEsperadas = (isset($info['rondas']) && (int)$info['rondas']>0) ? (int)$info['rondas'] : 3;
$incluir_menu = empty($_SESSION['__JUEZ_MODE__']);

/* Mayoría (tarjetas cerradas/“enviado”) — en el render inicial */
$cntAz=0; $cntRo=0; $cntEmp=0; $sumAz=0; $sumRo=0; $tarjetas=0;
if ($rs = $conexion->prepare("SELECT ganador, total_azul, total_rojo FROM resultados_jueces WHERE pelea_id=? AND estado='enviado'")) {
  $rs->bind_param('i',$pelea_id); $rs->execute(); $rs->bind_result($g,$ta,$tr);
  while($rs->fetch()){ $tarjetas++; $sumAz+=(int)$ta; $sumRo+=(int)$tr; if ($g==='azul') $cntAz++; elseif ($g==='rojo') $cntRo++; else $cntEmp++; }
  $rs->close();
}
$mayoria = null;
if ($tarjetas>0){
  if ($cntAz>$cntRo && $cntAz>$cntEmp) $mayoria='azul';
  elseif ($cntRo>$cntAz && $cntRo>$cntEmp) $mayoria='rojo';
  else $mayoria='empate';
}
$resumen_txt = $tarjetas>0
  ? ("Tarjetas: AZUL $cntAz · ROJO $cntRo · EMP $cntEmp — Totales sumados: Azul $sumAz / Rojo $sumRo — Decisión por mayoría: ".strtoupper($mayoria))
  : "Aún no hay tarjetas cerradas de jueces.";

/* Empate => +1 round SOLO si hay empate por mayoría */
$rondasMax = $rondasEsperadas; $ext_por_empate=false;
if ($mayoria==='empate' && $tarjetas>0){ $rondasMax=$rondasEsperadas+1; $ext_por_empate=true; }

/* Enviar resultado requiere >=3 tarjetas cerradas */
$puede_enviar_resultado = ($tarjetas >= 3);
$return_to = 'ver_peleas_evento.php?evento_id='.(int)$info['evento_id'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css?v=<?= $__BUILD ?>">
  <style>
    body{background:#0c0c0c;color:#fff}
    .stage{max-width:1100px;margin:0 auto;padding:16px;text-align:center}
    .grid{display:grid;grid-template-columns:1fr 1.2fr 1fr;gap:12px;align-items:stretch}
    @media (max-width:980px){ .grid{grid-template-columns:1fr} }
    .panel{background:#131313;border:1px solid #2a2a2a;border-radius:14px;padding:14px}
    .red{background:linear-gradient(#2a0000,#130000);border-color:#3a0000}
    .blue{background:linear-gradient(#001a3a,#000a1a);border-color:#002a6a}
    .corner-title{font-weight:800;letter-spacing:.5px;margin-bottom:8px}
    .corner-card{display:flex;gap:10px;align-items:center;justify-content:center}
    .pfp{width:78px;height:78px;object-fit:cover;border-radius:12px;border:2px solid #444}
    .name{font-size:20px;font-weight:800;line-height:1.05}
    .meta{font-size:12.5px;color:#ddd}
    .tag{display:inline-block;padding:2px 6px;border-radius:999px;background:#222;margin-right:5px;font-size:11px}
    .timer-face{font-size:72px;font-weight:900;letter-spacing:1px;transition:color .15s ease}
    .sub{font-size:14px;color:#ddd}
    .controls{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px}
    .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
    .btn-primary{background:#00b894;color:#fff}
    .btn-warn{background:#ffb300;color:#1a1a1a}
    .btn-danger{background:#e53935;color:#fff}
    .btn-gray{background:#2a2a2a;color:#fff}
    .num{font-weight:800}
    .row{display:flex;gap:6px;justify-content:center;flex-wrap:wrap}
    .blink{animation:blink .8s step-start infinite}
    @keyframes blink{50%{opacity:.35}}
    .score-panel{margin-top:12px;background:#0f0f0f;border:1px solid #2a2a2a;border-radius:12px;padding:10px}
    .score-table{width:100%;border-collapse:collapse}
    .score-table th,.score-table td{border:1px solid #2b2b2b;padding:6px 8px;font-size:13px}
    .score-table th{background:#171717}
    .badge{display:inline-block;padding:2px 6px;border-radius:8px;background:#1f1f1f;font-size:11px;margin-left:4px}
    .winR{color:#ff6b6b;font-weight:800}
    .winA{color:#6bb6ff;font-weight:800}
    .draw{color:#ffd54f;font-weight:800}
    .pending{opacity:.5}
    .total-cell{font-weight:800}
    .ok{margin-top:8px;padding:8px;border-radius:10px;background:#0f251b;border:1px solid #164b31;color:#b6f3d1}
    .final-msg{margin-top:8px;padding:10px;border-radius:10px;background:#25100f;border:1px solid #4b1616;color:#f3b6b6}
    .build{position:fixed;right:8px;bottom:8px;background:#111;color:#9fe;border:1px solid #234;padding:6px 8px;border-radius:8px;font:12px/1.1 monospace;z-index:99999}
  </style>
</head>
<body>
<?php if ($incluir_menu) { @include __DIR__ . '/menu_eventos.php'; } ?>

<div class="stage">
  <h2>🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></h2>

  <div class="grid">
    <section class="panel red">
      <div class="corner-title">🔴 RINCÓN ROJO</div>
      <div class="corner-card">
        <img class="pfp" src="<?= h($rFoto) ?>" alt="Rojo">
        <div>
          <div class="name"><?= h($info['r_apellido'].' '.$info['r_nombre']) ?></div>
          <div class="meta">🏫 <?= h($info['r_escuela'] ?? '-') ?> ·
            <span class="tag"><?= h($info['r_peso'] ?? '-') ?></span>
            <span class="tag"><?= h($info['r_division'] ?? '-') ?></span>
            <span class="tag"><?= h($info['r_modalidad'] ?? '-') ?></span>
          </div>
          <div class="meta">Edad: <?= h($info['r_edad'] ?? '-') ?></div>
        </div>
      </div>
    </section>

    <section class="panel">
      <div class="sub">Evento #<?= (int)$info['evento_id'] ?> · Rondas: <span id="txtRondas"><?= (int)$rondasEsperadas ?></span><?= $ext_por_empate ? ' <span class="badge">+1 por EMPATE</span>' : '' ?></div>
      <div id="timer" class="timer-face">03:00</div>
      <div class="sub">Round <span id="round" class="num">1</span> / <span class="num" id="maxr"><?= (int)$rondasMax ?></span></div>

      <div class="controls">
        <button id="btnStart" class="btn btn-primary">▶️ Iniciar</button>
        <button id="btnPause" class="btn btn-warn">⏸️ Pausar</button>
        <button id="btnReset" class="btn btn-danger">⟲ Reiniciar</button>
        <button id="btnNext"  class="btn btn-gray">⏭️ Siguiente round</button>
        <label class="sub" style="display:flex;align-items:center;gap:6px;">
          🔊 Volumen
          <input id="vol" type="range" min="0" max="100" value="95" style="width:160px">
        </label>
      </div>

      <!-- ====== Finalizar por método (KO/KOT/RSC/IRC/SURRENDER/ABANDONO/EMPATE) ====== -->
      <div id="finishBlock" class="panel" style="margin-top:12px;background:#181818;border-color:#2a2a2a;text-align:left">
        <div style="display:flex;align-items:center;gap:8px;justify-content:space-between;flex-wrap:wrap">
          <div style="font-weight:800">🛑 Finalizar por decisión</div>
          <button type="button" id="btnFillNow" class="btn btn-gray">Autocompletar round/tiempo</button>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
          <div>
            <div class="sub" style="margin-bottom:6px">Método</div>
            <select id="finMetodo" class="btn btn-gray" style="width:100%">
              <option value="">— Elegí —</option>
              <option value="KO">KO</option>
              <option value="KOT">KOT (TKO)</option>
              <option value="RSC">RSC / ESC</option>
              <option value="IRC">IRC</option>
              <option value="SURRENDER">SURRENDER</option>
              <option value="ABANDONO">ABANDONO</option>
              <option value="EMPATE">EMPATE</option>
            </select>
          </div>
          <div>
            <div class="sub" style="margin-bottom:6px">Ganador</div>
            <select id="finGanador" class="btn btn-gray" style="width:100%">
              <option value="">— Elegí —</option>
              <option value="azul">🔵 Azul</option>
              <option value="rojo">🔴 Rojo</option>
              <option value="empate">⚖️ Empate</option>
            </select>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:10px">
          <div>
            <div class="sub">Round</div>
            <input id="finRound" class="btn btn-gray" style="width:100%" placeholder="N°" inputmode="numeric">
          </div>
          <div>
            <div class="sub">Tiempo (mm:ss)</div>
            <input id="finTiempo" class="btn btn-gray" style="width:100%" placeholder="ej: 1:12">
          </div>
          <div>
            <div class="sub">Detalle (opcional)</div>
            <input id="finDetalle" class="btn btn-gray" style="width:100%" placeholder="Ej: KO derecha limpia">
          </div>
        </div>

        <div class="row" style="justify-content:flex-end;margin-top:12px">
          <button type="button" id="btnQuickAzulKO" class="btn btn-danger">KO Azul</button>
          <button type="button" id="btnQuickRojoKO" class="btn btn-danger">KO Rojo</button>
          <button type="button" id="btnFinishConfirm" class="btn btn-primary">✅ Confirmar y enviar</button>
        </div>
        <div class="sub" style="margin-top:6px;opacity:.8">Al confirmar se corta el reloj y se envía el resultado.</div>
      </div>
      <!-- ====== /Finalizar por método ====== -->

      <div class="score-panel">
        <div class="sub" style="margin-bottom:6px;">Tarjetas de jueces (en vivo)</div>
        <div class="table-wrap">
          <table class="score-table" id="scores"></table>
        </div>
        <div class="sub" id="scoreHint"></div>

        <div class="ok" id="resumenBox">
          <b>Resumen actual:</b> <?= h($resumen_txt) ?><br>
          <small>El envío registra el resultado de la pelea y vuelve al listado del evento.</small>
        </div>
        <div class="final-msg" id="finalMsg" style="display:none;">🏁 <b>Pelea finalizada.</b> Revisá el resumen y enviá el resultado cuando esté listo.</div>

        <form id="formResultados" method="POST" action="resultados_combates.php" style="margin-top:10px;"
              onsubmit="return confirm('¿Enviar el resultado de la pelea al sistema?');">
          <input type="hidden" name="pelea_id" value="<?= (int)$info['pelea_id'] ?>">
          <input type="hidden" name="evento_id" value="<?= (int)$info['evento_id'] ?>">
          <input type="hidden" name="mayoria" value="<?= h((string)$mayoria) ?>">
          <input type="hidden" name="votos_azul" value="<?= (int)$cntAz ?>">
          <input type="hidden" name="votos_rojo" value="<?= (int)$cntRo ?>">
          <input type="hidden" name="votos_empate" value="<?= (int)$cntEmp ?>">
          <input type="hidden" name="sum_total_azul" value="<?= (int)$sumAz ?>">
          <input type="hidden" name="sum_total_rojo" value="<?= (int)$sumRo ?>">
          <input type="hidden" name="rondas_config" value="<?= (int)$rondasEsperadas ?>">
          <input type="hidden" name="rondas_max" value="<?= (int)$rondasMax ?>">

          <!-- Cierre anticipado por método -->
          <input type="hidden" name="cierre_tipo" id="cierre_tipo" value="">
          <input type="hidden" name="metodo_final" id="metodo_final" value="">
          <input type="hidden" name="ganador_final" id="ganador_final" value="">
          <input type="hidden" name="cierre_round" id="cierre_round" value="">
          <input type="hidden" name="cierre_segundos" id="cierre_segundos" value="">
          <input type="hidden" name="motivo_texto" id="motivo_texto" value="">
          <input type="hidden" name="finalizada" id="finalizada" value="0">

          <input type="hidden" name="return_to" value="<?= h($return_to) ?>">

          <button id="btnSubmit" class="btn btn-danger" <?= $puede_enviar_resultado ? '' : 'disabled' ?>>📤 Enviar resultado</button>
          <a class="btn btn-gray" href="<?= h($return_to) ?>">↩️ Volver sin enviar</a>
        </form>
      </div>
    </section>

    <section class="panel blue">
      <div class="corner-title">🔵 RINCÓN AZUL</div>
      <div class="corner-card">
        <img class="pfp" src="<?= h($aFoto) ?>" alt="Azul">
        <div>
          <div class="name"><?= h($info['a_apellido'].' '.$info['a_nombre']) ?></div>
          <div class="meta">🏫 <?= h($info['a_escuela'] ?? '-') ?> ·
            <span class="tag"><?= h($info['a_peso'] ?? '-') ?></span>
            <span class="tag"><?= h($info['a_division'] ?? '-') ?></span>
            <span class="tag"><?= h($info['a_modalidad'] ?? '-') ?></span>
          </div>
          <div class="meta">Edad: <?= h($info['a_edad'] ?? '-') ?></div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Sello de versión (para confirmar que ves ESTE archivo) -->
<div class="build">combate_en_vivo.php · build <?= $__BUILD ?> · <?= h(__FILE__) ?></div>

<!-- Audios -->
<audio id="bellStart" preload="auto" src="assets/sounds/ring_start_bell.mp3"></audio>
<audio id="bellEnd"   preload="auto" src="assets/sounds/ring_end_bell.mp3"></audio>
<audio id="woodHit"   preload="auto" src="assets/sounds/wood_block.mp3"></audio>
<audio id="segAfuera" preload="auto" src="assets/sounds/segundos_afuera.mp3"></audio>

<script>
(function(){
  const MAX_R = <?= (int)$rondasMax ?>;    // tope de rondas (con +1 por empate si aplica)
  const CONF_R = <?= (int)$rondasEsperadas ?>; // rondas configuradas originalmente
  const peleaId = <?= (int)$pelea_id ?>;

  const timerEl = document.getElementById('timer');
  const roundEl = document.getElementById('round');
  const maxrEl  = document.getElementById('maxr');
  const btnStart = document.getElementById('btnStart');
  const btnPause = document.getElementById('btnPause');
  const btnReset = document.getElementById('btnReset');
  const btnNext  = document.getElementById('btnNext');
  const selDur   = document.getElementById('selDuracion');
  const selRest  = document.getElementById('selDescanso');
  const volEl    = document.getElementById('vol');
  const finalMsg = document.getElementById('finalMsg');
  const submitBtn= document.getElementById('btnSubmit');
  const formResultados = document.getElementById('formResultados');
  const finalizadaInput= document.getElementById('finalizada');

  // Finish-by-method panel refs:
  const btnFillNow       = document.getElementById('btnFillNow');
  const finMetodo        = document.getElementById('finMetodo');
  const finGanador       = document.getElementById('finGanador');
  const finRound         = document.getElementById('finRound');
  const finTiempo        = document.getElementById('finTiempo');
  const finDetalle       = document.getElementById('finDetalle');
  const btnFinishConfirm = document.getElementById('btnFinishConfirm');
  const btnQuickAzulKO   = document.getElementById('btnQuickAzulKO');
  const btnQuickRojoKO   = document.getElementById('btnQuickRojoKO');

  // Hidden fields:
  const hCierreTipo   = document.getElementById('cierre_tipo');
  const hMetodoFinal  = document.getElementById('metodo_final');
  const hGanadorFinal = document.getElementById('ganador_final');
  const hCierreRound  = document.getElementById('cierre_round');
  const hCierreSeg    = document.getElementById('cierre_segundos');
  const hMotivoTxt    = document.getElementById('motivo_texto');

  let duration = parseInt(selDur.value,10);
  let rest     = parseInt(selRest.value,10);
  let remain   = duration;
  let round    = 1;
  let t        = null;
  let inRest   = false;
  let warned10 = false;
  let warned15Rest = false;
  let startedSound = false;
  let finished = false;

  function fmt(s){ const m=Math.floor(s/60), ss=(s%60).toString().padStart(2,'0'); return `${m}:${ss}`; }
  function paint(){ timerEl.textContent = fmt(remain); roundEl.textContent = round; }
  function updateControls(){
    btnNext.disabled  = finished || round >= MAX_R;
    btnStart.disabled = finished;
    btnPause.disabled = finished;
    selDur.disabled   = finished;
    selRest.disabled  = finished;
    // deshabilitar panel finish si terminó
    const fb = document.getElementById('finishBlock');
    if (fb){ fb.querySelectorAll('input,select,button').forEach(el=> el.disabled = finished); }
  }

  /* ===== Audio mínimo (compacto) ===== */
  const AC = window.AudioContext || window.webkitAudioContext;
  const audioCtx = AC ? new AC() : null;
  const master = audioCtx ? audioCtx.createGain() : null;
  if (master) master.connect(audioCtx.destination);
  const bellStart=document.getElementById('bellStart'), bellEnd=document.getElementById('bellEnd'), woodHit=document.getElementById('woodHit'), segAfuera=document.getElementById('segAfuera');
  function ensureAudioReady(){ if (audioCtx && audioCtx.state==='suspended'){ audioCtx.resume().catch(()=>{}); } }
  function playEl(el){ if(!el) return; ensureAudioReady(); el.currentTime=0; el.play().catch(()=>{}); }
  function playWoodClone(){ const c=woodHit.cloneNode(true); c.volume=woodHit.volume; c.play().catch(()=>{}); }
  function soundStartRound(){ playEl(bellStart); }
  function soundWarn10(){ for(let i=0;i<5;i++) setTimeout(playWoodClone, i*200); }
  function soundEndBell(){ playEl(bellEnd); }
  function voiceSegundosAfuera(){ playEl(segAfuera); }

  /* ====== Abrir/cerrar votación SOLO desde acá (descanso) ====== */
  async function openVotingWindow() {
    try{
      const fd = new FormData();
      fd.set('pelea_id', String(peleaId));
      fd.set('action','open');
      fd.set('round', String(round));
      fd.set('seconds', String(rest));
      await fetch('set_votacion.php', {method:'POST', body:fd});
    }catch(_){}
  }
  async function closeVotingWindow() {
    try{
      const fd = new FormData();
      fd.set('pelea_id', String(peleaId));
      fd.set('action','close');
      await fetch('set_votacion.php', {method:'POST', body:fd});
    }catch(_){}
  }

  function enterRound(){ inRest=false; warned10=false; warned15Rest=false; startedSound=false; remain=duration; timerEl.style.color='#fff'; timerEl.classList.remove('blink'); paint(); updateControls(); closeVotingWindow(); }
  function enterRest(){ inRest=true; warned10=false; warned15Rest=false; startedSound=false; remain=rest; timerEl.style.color='#ffb300'; timerEl.classList.remove('blink'); paint(); openVotingWindow(); }

  function finalizeBout(setPts=true){
    if (finished) return;
    finished = true;
    pause();
    timerEl.textContent="00:00"; timerEl.classList.remove('blink'); timerEl.style.color='#ff6b6b';
    finalMsg.style.display=''; updateControls(); closeVotingWindow();
    if (setPts && !hMetodoFinal.value) hMetodoFinal.value='PTS';
    if (finalizadaInput) finalizadaInput.value='1';
  }

  function tick(){
    if (finished) return;
    if (remain>0) {
      remain--; paint();
      if (!inRest && !warned10 && remain===10){ warned10=true; timerEl.classList.add('blink'); soundWarn10(); }
      if (inRest && !warned15Rest && remain===15){ warned15Rest=true; voiceSegundosAfuera(); }
      return;
    }
    if (!inRest){
      soundEndBell();
      if (round >= MAX_R){ finalizeBout(true); return; }
      enterRest();
    } else {
      if (round >= MAX_R){ finalizeBout(true); return; }
      round++; enterRound(); soundStartRound();
    }
  }

  function start(){ if(!t && !finished){ if(!inRest && remain===duration && !startedSound){ soundStartRound(); startedSound=true; } t=setInterval(tick,1000); ensureAudioReady(); } }
  function pause(){ if(t){ clearInterval(t); t=null; } }
  function reset(){ pause(); finished=false; duration=parseInt(selDur.value,10); rest=parseInt(selRest.value,10); round=1; enterRound(); finalMsg.style.display='none'; hMetodoFinal.value=''; hGanadorFinal.value=''; hCierreRound.value=''; hCierreSeg.value=''; hMotivoTxt.value=''; hCierreTipo.value=''; finalizadaInput.value='0'; }

  function nextRound(){ if (finished || round>=MAX_R) { finalizeBout(true); return; } pause(); round++; enterRound(); }

  selDur.addEventListener('change', ()=>{ if (finished) return; duration=parseInt(selDur.value,10); if(!t && !inRest){ remain=duration; paint(); } });
  selRest.addEventListener('change', ()=>{ if (finished) return; rest=parseInt(selRest.value,10); });

  btnStart.addEventListener('click', start);
  btnPause.addEventListener('click', pause);
  btnReset.addEventListener('click', reset);
  btnNext .addEventListener('click', nextRound);
  paint(); updateControls();

  /* ===== Finalizar por método ===== */
  function autoFillFinishFields(){
    finRound.value=String(round);
    const secs=(!inRest?(duration-remain):0);
    const mm=Math.floor(secs/60), ss=String(secs%60).padStart(2,'0');
    finTiempo.value=`${mm}:${ss}`;
  }
  function mmssToSeconds(str){
    const t=(str||'').trim(); if(!/^\d{1,2}:\d{2}$/.test(t)) return 0;
    const [m,s]=t.split(':').map(n=>parseInt(n,10)); return (m*60+s)||0;
  }
  function finalizeByMethod(){
    const metodo=(finMetodo.value||'').trim(), ganador=(finGanador.value||'').trim();
    if (!metodo) return alert('Elegí un método.');
    if (metodo==='EMPATE'){ if(ganador!=='empate') return alert('Si el método es EMPATE, el ganador debe ser Empate.'); }
    else { if(ganador!=='azul' && ganador!=='rojo') return alert('Elegí el ganador.'); }
    const r=parseInt(finRound.value||'0',10); if(!r||r<1) return alert('Round inválido.');
    const segs=mmssToSeconds(finTiempo.value||'0:00');

    hCierreTipo.value='anticipado';
    hMetodoFinal.value=metodo;
    hGanadorFinal.value=ganador;
    hCierreRound.value=String(r);
    hCierreSeg.value=String(segs);
    hMotivoTxt.value=(finDetalle.value||'').trim();

    finalizeBout(false); finalizadaInput.value='1'; submitBtn?.removeAttribute('disabled');
    const oldC=window.confirm; window.confirm=()=>true;
    try{ formResultados.submit(); } finally { window.confirm=oldC; }
  }
  btnFillNow?.addEventListener('click', autoFillFinishFields);
  btnFinishConfirm?.addEventListener('click', finalizeByMethod);
  btnQuickAzulKO?.addEventListener('click', ()=>{ autoFillFinishFields(); finMetodo.value='KO'; finGanador.value='azul'; finalizeByMethod(); });
  btnQuickRojoKO?.addEventListener('click', ()=>{ autoFillFinishFields(); finMetodo.value='KO'; finGanador.value='rojo'; finalizeByMethod(); });
  autoFillFinishFields();

  /* ===== Tablero (polling) con Σ de puntos por round y cierre por “3 jueces x round” ===== */
  const tabla = document.getElementById('scores');
  const hint  = document.getElementById('scoreHint');
  const juecesMap = new Map();

  function icon(g){ if(g==='rojo') return '<span class="winR">🔴</span>'; if(g==='azul') return '<span class="winA">🔵</span>'; return '<span class="draw">⚖️</span>'; }
  function judgeLabelById(id, fb){ const j=juecesMap.get(id) || (fb?{apellido:fb.apellido||'',nombre:fb.nombre||'',id:fb.id||fb.juez_id}:null); const ape=(j?.apellido||j?.nombre||'Juez'); return `${j?.id ?? id} — ${ape}`; }
  function headerOrderFrom(data){ if (data?.rounds?.length && Array.isArray(data.rounds[0].judges)) return data.rounds[0].judges.map(j=>j.juez_id??j.id).filter(x=>x!=null); return Array.from(juecesMap.keys()); }

  function renderBoard(data){
    if(!data || !data.ok){
      if (!tabla.dataset.hasInit) tabla.innerHTML='<tr><td style="padding:10px">Sin datos de tarjetas.</td></tr>';
      hint.textContent='(Reintentando conexión…)';
      return;
    }
    (data.jueces||[]).forEach(j=>{ const id=j.id??j.juez_id; if(id!=null && !juecesMap.has(id)){ juecesMap.set(id,{id,apellido:j.apellido||'',nombre:j.nombre||''}); } });
    const order = headerOrderFrom(data);
    const numJudges = (data.jueces||[]).length;

    let html = '<thead><tr><th>Round</th>';
    order.forEach(id=>{ const fb=(data.jueces||[]).find(j=>(j.id??j.juez_id)===id); html += `<th>${judgeLabelById(id, fb)}</th>`; });
    html += '<th>Rojo (Rds)</th><th>Azul (Rds)</th><th>Σ Rojo Pts</th><th>Σ Azul Pts</th></tr></thead><tbody>';

    let sumR=0,sumA=0, totalPtsR=0, totalPtsA=0;
    let roundsCompletos = 0;

    (data.rounds||[]).forEach(r=>{
      let rR=0,rA=0, ptsR=0, ptsA=0, llenos=0;

      html += `<tr><td>${r.round}</td>`;
      const byId=new Map(); (r.judges||[]).forEach(j=> byId.set(j.juez_id??j.id, j));

      order.forEach(id=>{
        const j = byId.get(id);
        if(!j || j.ganador==null){
          html += '<td class="pending">—</td>';
        } else {
          if(j.ganador==='rojo') rR++; if(j.ganador==='azul') rA++;
          if (j.rojo_puntos!=null) ptsR += parseInt(j.rojo_puntos,10)||0;
          if (j.azul_puntos!=null) ptsA += parseInt(j.azul_puntos,10)||0;
          const pts = (j.azul_puntos!=null && j.rojo_puntos!=null) ? ` <div class="sub" style="opacity:.9">${j.azul_puntos}–${j.rojo_puntos}</div>` : '';
          html += `<td>${icon(j.ganador)}${pts}</td>`;
          llenos++;
        }
      });

      if (numJudges >= 3 && llenos === numJudges) roundsCompletos++;

      sumR+=rR; sumA+=rA; totalPtsR+=ptsR; totalPtsA+=ptsA;
      html += `<td class="total-cell">${rR}</td><td class="total-cell">${rA}</td><td class="total-cell">${ptsR}</td><td class="total-cell">${ptsA}</td></tr>`;
    });

    html += `<tr><td><b>Σ</b></td>${order.map(()=>'<td></td>').join('')}<td class="winR">${sumR}</td><td class="winA">${sumA}</td><td class="winR">${totalPtsR}</td><td class="winA">${totalPtsA}</td></tr>`;
    html += '</tbody>';
    tabla.innerHTML = html; tabla.dataset.hasInit = '1';

    // Proyección simple opcional (si el endpoint trae)
    hint.textContent = data.proyeccion ? ('Proyección: '+data.proyeccion) : '';

    // 🔒 Cerrar automáticamente si:
    // 1) Se alcanzó el tope de rounds con jueces completos (>=3) por round
    if (!finished && roundsCompletos >= MAX_R){
      finalizeBout(true);
      return;
    }

    // 2) O si el endpoint ya no devuelve más rounds que CONF_R (por seguridad)
    const roundsCargados = Array.isArray(data.rounds) ? data.rounds.length : 0;
    if (!finished && roundsCargados >= MAX_R){
      finalizeBout(true);
    }
  }

  async function tryFetchJson(url, tries=3){
    for(let i=0;i<tries;i++){
      try{ const r=await fetch(url,{cache:'no-store'}); if (r.ok) return await r.json(); }catch(_){}
      await new Promise(res=>setTimeout(res, 500*(i+1)));
    }
    return null;
  }
  async function loadJudges(){ const j = await tryFetchJson('get_jueces_pelea.php?pelea_id='+peleaId, 3); if (j?.ok && Array.isArray(j.jueces)){ j.jueces.forEach(x=>{ if(x && x.id!=null){ juecesMap.set(x.id,{id:x.id,apellido:x.apellido||'',nombre:x.nombre||''}); } }); } }
  async function loadBoard(){ const data = await tryFetchJson('get_tablero_tarjetas.php?pelea_id='+peleaId, 3); if (data) renderBoard(data); }

  loadJudges().finally(()=>{ loadBoard(); setInterval(loadBoard, 3000); });
})();
</script>
</body>
</html>
