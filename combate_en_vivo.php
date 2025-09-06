<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

/* Incluir menú SOLO si NO es modo juez (para que al juez no lo redirija) */
if (empty($_SESSION['__JUEZ_MODE__'])) {
  @require_once __DIR__ . '/menu_eventos.php'; // si no existe, no rompe
}

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

/* === Detectar columnas de peleas_evento === */
$cols = [];
$res = $conexion->query("SHOW COLUMNS FROM `peleas_evento`");
if (!$res) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se pudo leer columnas de <b>peleas_evento</b>: '.h($conexion->error).'</div>'; exit;
}
while($r = $res->fetch_assoc()){ $cols[strtolower($r['Field'])] = $r['Field']; }
$res->close();

$pick = function(array $cands) use ($cols){ foreach($cands as $c){ $lc=strtolower($c); if(isset($cols[$lc])) return $cols[$lc]; } return null; };

$C_EVENTO = $pick(['evento_id','id_evento','evento']);
$C_ROJO   = $pick(['competidor_rojo_id','rojo_id','id_rojo','id_competidor_rojo','rojo']);
$C_AZUL   = $pick(['competidor_azul_id','azul_id','id_azul','id_competidor_azul','azul']);
$C_RONDAS = $pick(['rondas']);

if (!$C_EVENTO || !$C_ROJO || !$C_AZUL) {
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">
    Faltan columnas obligatorias en <b>peleas_evento</b>. Necesarias: evento_id / id_evento / evento, y
    competidor_rojo_id / rojo_id …, competidor_azul_id / azul_id …
  </div>'; exit;
}

/* === Traer info de la pelea + competidores === */
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
  echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">Error preparando SQL: '.h($conexion->error).'</div>'; exit;
}
$st->bind_param('i', $pelea_id);
$st->execute();
$info = $st->get_result()->fetch_assoc();
$st->close();

if (!$info) { echo '<div style="max-width:900px;margin:16px auto;padding:12px;border:1px solid #f5c6cb;background:#fdecea;color:#b71c1c;border-radius:8px;">No se encontró la pelea.</div>'; exit; }

$phUser = 'assets/placeholder-user.png';
$rFoto = !empty($info['r_foto']) ? $info['r_foto'] : $phUser;
$aFoto = !empty($info['a_foto']) ? $info['a_foto'] : $phUser;

$rondasEsperadas = (isset($info['rondas']) && (int)$info['rondas']>0) ? (int)$info['rondas'] : 3;
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
    /* Cronómetro */
    .timer-face{font-size:72px;font-weight:900;letter-spacing:1px}
    .sub{font-size:14px;color:#ddd}
    .controls{display:flex;flex-wrap:wrap;gap:8px;justify-content:center;margin-top:10px}
    .btn{padding:10px 14px;border-radius:10px;border:0;cursor:pointer}
    .btn-primary{background:#00b894;color:#fff}
    .btn-warn{background:#ffb300;color:#1a1a1a}
    .btn-danger{background:#e53935;color:#fff}
    .num{font-weight:800}
    .row{display:flex;gap:6px;justify-content:center;flex-wrap:wrap}

    /* ==== TABLERO TARJETAS (amateurs) ==== */
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
<div class="stage">
  <h2>🥊 Combate en vivo — Pelea #<?= (int)$pelea_id ?></h2>

  <div class="grid">
    <!-- ROJO -->
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

    <!-- CENTRO / CRONÓMETRO + TABLERO -->
    <section class="panel">
      <div class="sub">Evento #<?= (int)$info['evento_id'] ?> · Rondas: <?= (int)$rondasEsperadas ?></div>
      <div id="timer" class="timer-face">03:00</div>
      <div class="sub">Round <span id="round" class="num">1</span></div>

      <div class="controls">
        <button id="btnStart" class="btn btn-primary">▶️ Iniciar</button>
        <button id="btnPause" class="btn btn-warn">⏸️ Pausar</button>
        <button id="btnReset" class="btn btn-danger">⟲ Reiniciar</button>
        <button id="btnNext"  class="btn">⏭️ Siguiente round</button>
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

      <!-- ==== TARJETAS EN VIVO ==== -->
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

    <!-- AZUL -->
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

  let duration = parseInt(selDur.value,10);   // segundos
  let rest     = parseInt(selRest.value,10);  // segundos
  let remain   = duration;
  let round    = 1;
  let t        = null;
  let inRest   = false;

  function fmt(s){ const m = Math.floor(s/60); const ss = (s%60).toString().padStart(2,'0'); return `${m}:${ss}`; }
  function paint(){ timerEl.textContent = fmt(remain); roundEl.textContent = round; }

  function tick(){
    if (remain > 0){ remain--; paint(); return; }
    if (!inRest){
      // terminó round -> descanso
      inRest = true;
      remain = rest;
      timerEl.style.color = '#ffb300';
    } else {
      // terminó descanso -> nuevo round
      inRest = false;
      round++;
      remain = duration;
      timerEl.style.color = '#fff';
    }
    paint();
  }

  function start(){ if (!t) t = setInterval(tick, 1000); }
  function pause(){ if (t){ clearInterval(t); t = null; } }
  function reset(){
    pause();
    inRest = false;
    duration = parseInt(selDur.value,10);
    rest     = parseInt(selRest.value,10);
    remain   = duration;
    timerEl.style.color = '#fff';
    paint();
  }
  function nextRound(){
    pause();
    inRest = false;
    round++;
    duration = parseInt(selDur.value,10);
    remain   = duration;
    timerEl.style.color = '#fff';
    paint();
  }

  selDur.addEventListener('change', ()=>{ duration=parseInt(selDur.value,10); if(!t){ remain=duration; paint(); }});
  selRest.addEventListener('change', ()=>{ rest=parseInt(selRest.value,10); });

  btnStart.addEventListener('click', start);
  btnPause.addEventListener('click', pause);
  btnReset.addEventListener('click', reset);
  btnNext .addEventListener('click', nextRound);

  paint();

  /* ===== Tablero de tarjetas (no expone puntos, sólo ganador por round) ===== */
  const tabla = document.getElementById('scores');
  const hint  = document.getElementById('scoreHint');
  const peleaId = <?= (int)$pelea_id ?>;

  function icon(g){
    if(g==='rojo') return '<span class="winR">🔴</span>';
    if(g==='azul') return '<span class="winA">🔵</span>';
    return '<span class="draw">⚖️</span>';
  }

  function renderBoard(data){
    if(!data || !data.ok){ tabla.innerHTML = '<tr><td>Error al cargar tarjetas</td></tr>'; hint.textContent=''; return; }
    const jueces = data.jueces || [];
    const rounds = data.rounds || [];
    let html = '<thead><tr><th>Round</th>';
    jueces.forEach(j=>{ html += `<th>${j.nombre}</th>`; });
    html += '<th>Rojo (Rds)</th><th>Azul (Rds)</th></tr></thead><tbody>';

    let sumR=0, sumA=0;
    rounds.forEach(r=>{
      let rR=0, rA=0;
      html += `<tr><td>${r.round}</td>`;
      r.judges.forEach(j=>{
        if (j.ganador==null){
          html += '<td class="pending">—</td>';
        } else {
          if (j.ganador==='rojo') rR++;
          if (j.ganador==='azul') rA++;
          const tag = j.metodo ? ` <span class="badge">${j.metodo}</span>` : '';
          html += `<td>${icon(j.ganador)}${tag}</td>`;
        }
      });
      sumR += rR; sumA += rA;
      html += `<td class="total-cell">${rR}</td><td class="total-cell">${rA}</td></tr>`;
    });

    html += `<tr><td><b>Σ</b></td>`;
    jueces.forEach(()=>{ html += `<td></td>`; });
    html += `<td class="winR">${sumR}</td><td class="winA">${sumA}</td></tr>`;
    html += '</tbody>';
    tabla.innerHTML = html;

    hint.textContent = data.proyeccion ? ('Proyección: ' + data.proyeccion) : '';
  }

  function loadBoard(){
    fetch('get_tablero_tarjetas.php?pelea_id='+peleaId, {cache:'no-store'})
      .then(r=>r.json())
      .then(renderBoard)
      .catch(()=>{ tabla.innerHTML='<tr><td>Error de conexión</td></tr>'; hint.textContent=''; });
  }

  loadBoard();
  setInterval(loadBoard, 3000); // refresco cada 3s
})();
</script>
</body>
</html>
