<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

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
if (!$st) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error preparando SQL: '.h($conexion->error).'</div>';
  exit;
}
$st->bind_param('i', $pelea_id);
$st->execute();
$info = $st->get_result()->fetch_assoc();
$st->close();

if (!$info) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se encontró la pelea.</div>';
  exit;
}

$phUser = 'assets/placeholder-user.png';
$rFoto = !empty($info['r_foto']) ? $info['r_foto'] : $phUser;
$aFoto = !empty($info['a_foto']) ? $info['a_foto'] : $phUser;

$rondasEsperadas = (isset($info['rondas']) && (int)$info['rondas']>0) ? (int)$info['rondas'] : 3;
$incluir_menu = empty($_SESSION['__JUEZ_MODE__']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <link rel="stylesheet" href="estilo_unificado.css">
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
      <div class="sub">Evento #<?= (int)$info['evento_id'] ?> · Rondas: <?= (int)$rondasEsperadas ?></div>
      <div id="timer" class="timer-face">03:00</div>
      <div class="sub">Round <span id="round" class="num">1</span></div>

      <div class="controls">
        <button id="btnStart" class="btn btn-primary">▶️ Iniciar</button>
        <button id="btnPause" class="btn btn-warn">⏸️ Pausar</button>
        <button id="btnReset" class="btn btn-danger">⟲ Reiniciar</button>
        <button id="btnNext"  class="btn">⏭️ Siguiente round</button>
        <label class="sub" style="display:flex;align-items:center;gap:6px;">
          🔊 Volumen
          <input id="vol" type="range" min="0" max="100" value="95" style="width:160px">
        </label>
      </div>

      <div style="margin-top:10px" class="row">
        Duración del round:
        <select id="selDuracion" class="btn">
          <option value="180" selected>3:00</option>
          <option value="120">2:00</option>
          <option value="90">1:30</option>
          <option value="60">1:00</option>
        </select>
        <span class="sub"> · Descanso (entre rounds): </span>
        <select id="selDescanso" class="btn">
          <option value="60" selected>1:00</option>
          <option value="30">0:30</option>
          <option value="90">1:30</option>
        </select>
      </div>

      <div class="score-panel">
        <div class="sub" style="margin-bottom:6px;">Tarjetas de jueces (en vivo)</div>
        <div class="table-wrap">
          <table class="score-table" id="scores"></table>
        </div>
        <div class="sub" id="scoreHint"></div>

        <?php if (!empty($_SESSION['user_rol']) && $_SESSION['user_rol']==='admin'): ?>
          <form method="POST" action="finalizar_pelea.php" style="margin-top:10px;">
            <input type="hidden" name="pelea_id" value="<?= (int)$pelea_id ?>">
            <input type="hidden" name="evento_id" value="<?= (int)$info['evento_id'] ?>">
            <button class="btn btn-danger" onclick="return confirm('¿Finalizar pelea y registrar decisión?')">🏁 Finalizar pelea</button>
          </form>
        <?php endif; ?>
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

<!-- Audios reales (campana/tabla/voz) -->
<audio id="bellStart" preload="auto" src="assets/sounds/ring_start_bell.mp3"></audio>
<audio id="bellEnd"   preload="auto" src="assets/sounds/ring_end_bell.mp3"></audio>
<audio id="woodHit"   preload="auto" src="assets/sounds/wood_block.mp3"></audio>
<audio id="segAfuera" preload="auto" src="assets/sounds/segundos_afuera.mp3"></audio>

<script>
(function(){
  /* ===== Cronómetro ===== */
  const timerEl = document.getElementById('timer');
  const roundEl = document.getElementById('round');
  const btnStart = document.getElementById('btnStart');
  const btnPause = document.getElementById('btnPause');
  const btnReset = document.getElementById('btnReset');
  const btnNext  = document.getElementById('btnNext');
  const selDur   = document.getElementById('selDuracion');
  const selRest  = document.getElementById('selDescanso');
  const volEl    = document.getElementById('vol');

  let duration = parseInt(selDur.value,10);
  let rest     = parseInt(selRest.value,10);
  let remain   = duration;
  let round    = 1;
  let t        = null;
  let inRest   = false;
  let warned10 = false;     // 10s del round
  let warned15Rest = false; // “segundos afuera” a 15s del fin del descanso
  let startedSound = false;

  function fmt(s){ const m=Math.floor(s/60), ss=(s%60).toString().padStart(2,'0'); return `${m}:${ss}`; }
  function paint(){ timerEl.textContent = fmt(remain); roundEl.textContent = round; }

  /* ====== Audio setup (WebAudio + compresor + media elements) ====== */
  const AC = window.AudioContext || window.webkitAudioContext;
  const audioCtx = AC ? new AC() : null;
  const master = audioCtx ? audioCtx.createGain() : null;
  const comp = audioCtx ? audioCtx.createDynamicsCompressor() : null;

  if (comp){
    comp.threshold.setValueAtTime(-16, audioCtx.currentTime);
    comp.knee.setValueAtTime(20, audioCtx.currentTime);
    comp.ratio.setValueAtTime(20, audioCtx.currentTime);
    comp.attack.setValueAtTime(0.003, audioCtx.currentTime);
    comp.release.setValueAtTime(0.25, audioCtx.currentTime);
    comp.connect(master);
  }
  if (master){
    master.gain.value = 0.95;
    master.connect(audioCtx.destination);
  }

  const bellStart = document.getElementById('bellStart');
  const bellEnd   = document.getElementById('bellEnd');
  const woodHit   = document.getElementById('woodHit');
  const segAfuera = document.getElementById('segAfuera');

  function syncMediaVolume(){
    const v = Math.max(0, Math.min(1, parseInt(volEl.value,10)/100));
    [bellStart,bellEnd,woodHit,segAfuera].forEach(a=>{ if(a) a.volume = v; });
    if (master) master.gain.value = v;
  }
  volEl.addEventListener('input', syncMediaVolume);
  syncMediaVolume();

  function connectMediaElement(el){
    if (!audioCtx || !comp || !el) return;
    try{
      const node = audioCtx.createMediaElementSource(el);
      node.connect(comp);
    }catch(e){ /* ignore if already connected */ }
  }
  [bellStart,bellEnd,woodHit,segAfuera].forEach(connectMediaElement);

  async function ensureAudioReady(){
    if (audioCtx && audioCtx.state==='suspended'){
      try{ await audioCtx.resume(); }catch(_){}
    }
  }
  function playEl(el){
    if(!el) return;
    ensureAudioReady();
    el.currentTime = 0;
    el.play().catch(()=>{});
  }
  function playWoodClone(){
    const c = woodHit.cloneNode(true);
    c.volume = woodHit.volume;
    c.play().catch(()=>{});
  }

  /* ===== Fallbacks ===== */
  const useFallback = { start:false, end:false, wood:false, voz:false };
  bellStart?.addEventListener('error', ()=> useFallback.start = true);
  bellEnd  ?.addEventListener('error', ()=> useFallback.end   = true);
  woodHit  ?.addEventListener('error', ()=> useFallback.wood  = true);
  segAfuera?.addEventListener('error', ()=> useFallback.voz   = true);

  function fallbackTone(freq=1200, dur=0.18, type='triangle', vol=0.35){
    if(!audioCtx) return;
    const o = audioCtx.createOscillator(), g = audioCtx.createGain(), now=audioCtx.currentTime;
    o.type=type; o.frequency.setValueAtTime(freq,now);
    g.gain.setValueAtTime(vol,now); g.gain.exponentialRampToValueAtTime(0.0001, now+dur);
    o.connect(g); g.connect(comp || master || audioCtx.destination);
    o.start(now); o.stop(now+dur);
  }
  function fallbackSpeak(text){
    if (!('speechSynthesis' in window)) return;
    try{
      window.speechSynthesis.cancel();
      const u = new SpeechSynthesisUtterance(text);
      // Elegir voz española/latina si está
      const voices = speechSynthesis.getVoices();
      const pref = voices.find(v=>/es-|Spanish/i.test(v.lang||v.name)) || voices[0];
      if (pref) u.voice = pref;
      u.rate = 0.95; u.pitch = 1.0; u.volume = Math.max(0, Math.min(1, parseInt(volEl.value,10)/100));
      speechSynthesis.speak(u);
    }catch(_){}
  }

  /* ===== Sonidos “reales” ===== */
  function soundStartRound(){
    if (!useFallback.start) { playEl(bellStart); }
    else { fallbackTone(1600,0.18,'triangle',0.38); setTimeout(()=>fallbackTone(1600,0.18,'triangle',0.38), 180); }
  }
  function soundWarn10(){
    if (!useFallback.wood) {
      for(let i=0;i<5;i++){ setTimeout(playWoodClone, i*200); }
    } else {
      for(let i=0;i<5;i++){ setTimeout(()=>fallbackTone(800,0.08,'square',0.4), i*200); }
    }
  }
  function soundEndBell(){
    if (!useFallback.end) { playEl(bellEnd); }
    else {
      fallbackTone(1700,0.12,'triangle',0.42);
      setTimeout(()=>fallbackTone(1700,0.12,'triangle',0.42), 180);
      setTimeout(()=>fallbackTone(1700,0.12,'triangle',0.42), 360);
    }
  }
  function voiceSegundosAfuera(){
    if (!useFallback.voz) { playEl(segAfuera); }
    else { fallbackSpeak('¡Segundos afuera!'); }
  }

  /* ===== Lógica del reloj ===== */
  function enterRound(){
    inRest=false; warned10=false; warned15Rest=false; startedSound=false;
    remain = duration; timerEl.style.color='#fff'; timerEl.classList.remove('blink'); paint();
  }
  function enterRest(){
    inRest=true; warned10=false; warned15Rest=false; startedSound=false;
    remain = rest; timerEl.style.color='#ffb300'; timerEl.classList.remove('blink'); paint();
  }

  function tick(){
    if (remain > 0) {
      remain--;
      paint();

      // Aviso de 10s en el round (golpes)
      if (!inRest && !warned10 && remain === 10) {
        warned10 = true;
        timerEl.classList.add('blink');
        soundWarn10();
      }

      // Aviso “segundos afuera” cuando faltan 15s para acabar el DESCANSO
      if (inRest && !warned15Rest && remain === 15) {
        warned15Rest = true;
        voiceSegundosAfuera();
      }

      return;
    }

    // Cambio de estado al llegar a 0
    if (!inRest){ soundEndBell(); enterRest(); }
    else { round++; enterRound(); soundStartRound(); }
  }

  function start(){
    if(!t){
      if(!inRest && remain===duration && !startedSound){ soundStartRound(); startedSound=true; }
      t = setInterval(tick,1000);
      ensureAudioReady();
    }
  }
  function pause(){ if(t){ clearInterval(t); t=null; } }
  function reset(){ pause(); duration=parseInt(selDur.value,10); rest=parseInt(selRest.value,10); round=1; enterRound(); }
  function nextRound(){ pause(); round++; enterRound(); }

  selDur.addEventListener('change', ()=>{ duration=parseInt(selDur.value,10); if(!t && !inRest){ remain=duration; paint(); } });
  selRest.addEventListener('change', ()=>{ rest=parseInt(selRest.value,10); });

  btnStart.addEventListener('click', start);
  btnPause.addEventListener('click', pause);
  btnReset.addEventListener('click', reset);
  btnNext .addEventListener('click', nextRound);

  paint();

  /* ===== Tarjetas (jueces) ===== */
  const tabla = document.getElementById('scores');
  const hint  = document.getElementById('scoreHint');
  const peleaId = <?= (int)$pelea_id ?>;
  const juecesMap = new Map();

  function icon(g){ if(g==='rojo') return '<span class="winR">🔴</span>'; if(g==='azul') return '<span class="winA">🔵</span>'; return '<span class="draw">⚖️</span>'; }
  function judgeLabelById(id, fallback){
    const j = juecesMap.get(id);
    if (j){ const ape = (j.apellido||j.nombre||'Juez'); return `${j.id ?? id} — ${ape}`; }
    if (fallback){ const ape=(fallback.apellido||fallback.nombre||'Juez'); return `${fallback.id ?? id ?? ''}${(fallback.id||id)?' — ':''}${ape}`; }
    return String(id ?? 'Juez');
  }
  function headerOrderFrom(data){
    if (data && Array.isArray(data.rounds) && data.rounds.length && Array.isArray(data.rounds[0].judges)){
      return data.rounds[0].judges.map(j => j.juez_id ?? j.id).filter(x=>x!=null);
    }
    return Array.from(juecesMap.keys());
  }
  function renderBoard(data){
    if(!data || !data.ok){
      if (!tabla.dataset.hasInit) {
        tabla.innerHTML='<tr><td style="padding:10px">Sin datos de tarjetas.</td></tr>';
      }
      hint.textContent='(Reintentando conexión…)';
      return;
    }
    (data.jueces||[]).forEach(j=>{
      const id=j.id??j.juez_id; if(id!=null && !juecesMap.has(id)){ juecesMap.set(id,{id,apellido:j.apellido||'',nombre:j.nombre||''}); }
    });
    const order = headerOrderFrom(data);
    let html = '<thead><tr><th>Round</th>';
    order.forEach(id=>{
      const fb = (data.jueces||[]).find(j=>(j.id??j.juez_id)===id);
      html += `<th>${judgeLabelById(id, fb)}</th>`;
    });
    html += '<th>Rojo (Rds)</th><th>Azul (Rds)</th></tr></thead><tbody>';

    let sumR=0,sumA=0;
    (data.rounds||[]).forEach(r=>{
      let rR=0,rA=0;
      html += `<tr><td>${r.round}</td>`;
      const byId=new Map(); (r.judges||[]).forEach(j=> byId.set(j.juez_id??j.id, j));
      order.forEach(id=>{
        const j = byId.get(id);
        if(!j || j.ganador==null){ html += '<td class="pending">—</td>'; }
        else{
          if(j.ganador==='rojo') rR++; if(j.ganador==='azul') rA++;
          const tag = j.metodo? ` <span class="badge">${j.metodo}</span>` : '';
          html += `<td>${icon(j.ganador)}${tag}</td>`;
        }
      });
      sumR+=rR; sumA+=rA;
      html += `<td class="total-cell">${rR}</td><td class="total-cell">${rA}</td></tr>`;
    });
    html += `<tr><td><b>Σ</b></td>${order.map(()=>'<td></td>').join('')}<td class="winR">${sumR}</td><td class="winA">${sumA}</td></tr>`;
    html += '</tbody>';
    tabla.innerHTML = html;
    tabla.dataset.hasInit = '1';

    hint.textContent = data.proyeccion ? ('Proyección: '+data.proyeccion) : '';
  }

  async function tryFetchJson(url, tries=3){
    for(let i=0;i<tries;i++){
      try{
        const r = await fetch(url, {cache:'no-store'});
        if (r.ok){ return await r.json(); }
      }catch(_){}
      await new Promise(res=>setTimeout(res, 500*(i+1)));
    }
    return null;
  }

  async function loadJudges(){
    const j = await tryFetchJson('get_jueces_pelea.php?pelea_id='+peleaId, 3);
    if (j && j.ok && Array.isArray(j.jueces)){
      j.jueces.forEach(x=>{ if(x && x.id!=null){ juecesMap.set(x.id,{id:x.id,apellido:x.apellido||'',nombre:x.nombre||''}); } });
    }
  }
  async function loadBoard(){
    const data = await tryFetchJson('get_tablero_tarjetas.php?pelea_id='+peleaId, 3);
    if (data) renderBoard(data);
  }

  loadJudges().finally(()=>{
    loadBoard();
    setInterval(loadBoard, 3000);
  });
})();
</script>
</body>
</html>
