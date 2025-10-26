<?php
// registrar_progreso.php — Registrar progreso con MENÚ REUSABLE (cliente)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id  = (int)($_SESSION['cliente_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

if ($cliente_id === 0 || $gimnasio_id === 0) {
  echo "<div style='color:red; text-align:center;'>❌ Acceso denegado</div>";
  exit;
}

// helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}

// === Prefill desde datos_fisicos (último registro) ===
$peso_prefill = '';
$altura_prefill = '';
if (db_has_table($conexion, 'datos_fisicos')) {
  // ajustá nombres de columnas si en tu BD difieren:
  $sqlPF = "SELECT peso AS p, altura_cm AS a
            FROM datos_fisicos
            WHERE cliente_id=? AND gimnasio_id=?
            ORDER BY fecha DESC, id DESC
            LIMIT 1";
  if ($stPF = @$conexion->prepare($sqlPF)) {
    $stPF->bind_param("ii", $cliente_id, $gimnasio_id);
    if ($stPF->execute()) {
      if ($row = $stPF->get_result()->fetch_assoc()) {
        $peso_prefill   = trim((string)($row['p'] ?? ''));
        $altura_prefill = trim((string)($row['a'] ?? ''));
      }
    }
    $stPF->close();
  }
}

// === Historial (tabla progreso) ===
$historial = [];
if (db_has_table($conexion, 'progreso')) {
  $sqlH = "SELECT fecha, objetivo, notas, peso_antes, peso_despues, altura_cm, duracion_min, calorias_quemadas
           FROM progreso
           WHERE cliente_id=? AND gimnasio_id=?
           ORDER BY fecha DESC, id DESC
           LIMIT 10";
  if ($st = @$conexion->prepare($sqlH)) {
    $st->bind_param("ii", $cliente_id, $gimnasio_id);
    if ($st->execute()) {
      $res = $st->get_result();
      while ($r = $res->fetch_assoc()) $historial[] = $r;
    }
    $st->close();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <title>📈 Registrar Progreso</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    /* ================== MENÚ UNIFICADO (idéntico al panel) ================== */
    :root{
      --mnu-bg-bar: rgba(15,19,32,.78);
      --mnu-bg-drawer: rgba(10,12,20,.94);
      --mnu-fg: #fff;
      --mnu-fg-dim: #cbd5e1;
      --mnu-accent: #ffd600;
      --mnu-border: rgba(255,255,255,.16);
      --mnu-shadow: 0 10px 30px rgba(0,0,0,.45);

      /* Base panel */
      --bg:#0b0b0b; --surface:#0f1115; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12);
    }
    .mnu-bar{ position:sticky; top:0; z-index:1000; display:flex; align-items:center; gap:12px; padding:10px 14px; background:var(--mnu-bg-bar); -webkit-backdrop-filter: blur(10px) saturate(1.05); backdrop-filter: blur(10px) saturate(1.05); border-bottom:1px solid var(--mnu-border); }
    .mnu-title{ font-weight:800; color:var(--mnu-accent); }
    .mnu-spacer{ flex:1; }
    .mnu-btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 14px; border-radius:999px; cursor:pointer; background:var(--mnu-accent); color:#111; border:none; font-weight:700; }
    .mnu-btn--ghost{ background:transparent; color:var(--mnu-fg); border:1px solid var(--mnu-border); }
    .mnu-inline{ display:flex; gap:10px; flex-wrap:wrap; padding:10px 14px; background:transparent; border-bottom:1px solid var(--mnu-border); }
    .mnu-tab{ padding:10px 14px; border-radius:14px; border:1px solid var(--mnu-border); color:var(--mnu-fg); text-decoration:none; }
    .mnu-tab:hover{ background:rgba(255,255,255,.06); }
    @media (max-width:920px){ .mnu-inline{ display:none !important; } }
    .mnu-backdrop{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:10005; display:none; }
    .mnu-drawer{ position:fixed; top:0; bottom:0; left:0; width:86vw; max-width:360px; background:var(--mnu-bg-drawer); border-right:1px solid var(--mnu-border); box-shadow:var(--mnu-shadow); transform:translateX(-100%); transition:transform .25s ease; z-index:10010; padding:14px; display:flex; flex-direction:column; gap:12px; }
    .mnu-drawer.open{ transform:translateX(0); }
    .mnu-backdrop.show{ display:block; }
    .mnu-head{ display:flex; align-items:center; gap:10px; margin-bottom:6px; }
    .mnu-close{ width:44px; height:44px; border-radius:50%; display:grid; place-items:center; cursor:pointer; background:var(--mnu-accent); color:#111; font-weight:900; border:none; }
    .mnu-list{ display:flex; flex-direction:column; gap:12px; margin:0; padding:0; list-style:none; }
    .mnu-item{ display:flex; align-items:center; gap:12px; padding:14px; border-radius:14px; border:1px solid var(--mnu-border); color:#fff; text-decoration:none; background:transparent; }
    .mnu-item:hover{ background:rgba(255,255,255,.10); border-color:rgba(255,255,255,.30); }
    .mnu-item__icon{ width:24px; display:inline-grid; place-items:center; color:#fff; }
    .mnu-item__text{ font-size:18px; }
    .mnu-bar *, .mnu-drawer *, .mnu-inline *, .mnu-item, .mnu-item *{ color:#fff !important; -webkit-text-fill-color:#fff !important; text-shadow:none !important; background-clip:initial !important; -webkit-background-clip:initial !important; }

    /* ================== BASE / GLASS ================== */
    *{box-sizing:border-box}
    html,body{height:100%}
    body{ margin:0; background: radial-gradient(1000px 600px at 20% -10%, #1c1f28 0%, #0b0b0b 60%), var(--bg); color:var(--fg); font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
    .container{ max-width:1100px; margin:0 auto; padding:16px 16px 48px; }
    .glass{ background: rgba(255,255,255,.05); border:1px solid var(--border); border-radius:20px; backdrop-filter: blur(10px); box-shadow: 0 8px 30px rgba(0,0,0,.35); }
    .grid{ display:grid; gap:16px; grid-template-columns: 1fr; }
    @media (min-width:900px){ .grid{ grid-template-columns: 1fr 1fr; } }
    .card{ padding:18px }
    h2{ text-align:center; margin:10px 0 18px; }
    .muted{ color:var(--muted); }

    /* ================== FORM / HISTORIAL ================== */
    .formulario label{ display:block; margin:12px 0 6px; font-weight:700; color:var(--acc); }
    .control{ position:relative }
    .control input, .control select, .control textarea{
      width:100%; padding:12px 42px 12px 12px; border:1px solid #333; border-radius:12px;
      background:#0f1115; color:var(--fg); font-size:16px; resize:vertical;
    }
    .unit{ position:absolute; right:10px; top:50%; transform:translateY(-50%); color:#d1d5db; font-size:14px; pointer-events:none; }
    .btn{ width:100%; margin-top:14px; padding:12px; border:none; border-radius:12px; background:var(--acc); color:#111; font-weight:800; cursor:pointer; font-size:16px; }
    .outputs{ display:grid; gap:10px; grid-template-columns: 1fr; margin-top:10px }
    @media (min-width:520px){ .outputs{ grid-template-columns: repeat(2, 1fr); } }
    .out{ background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:12px; font-weight:700 }
    .ok{ color:#22c55e } .warn{ color:#f59e0b } .bad{ color:#ef4444 }
    table{ width:100%; border-collapse:collapse; font-size:14px; margin-top:8px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left }
    th{ color:var(--muted); font-weight:700 }
    .nowrap{ white-space:nowrap }
    .alert{ background:#112b1a; border:1px solid #1f6f3d; color:#a7f3d0; padding:10px 12px; border-radius:10px; margin:8px 0 18px; }
  </style>
  <script>
    function calcAll() {
      const n = v => parseFloat(String(v).replace(',', '.')) || 0;

      const pesoAntes   = n(document.getElementById('peso_antes').value);
      const pesoDespues = n(document.getElementById('peso_despues').value);
      const alturaCm    = n(document.getElementById('altura').value);
      const duracion    = n(document.getElementById('duracion').value);
      const esfuerzo    = document.getElementById('esfuerzo').value;

      let calorias = 0;
      if (esfuerzo === 'bajo')  calorias = duracion * 4;
      if (esfuerzo === 'medio') calorias = duracion * 7;
      if (esfuerzo === 'alto')  calorias = duracion * 10;

      const delta = (pesoDespues - pesoAntes);
      const deltaAbs = Math.abs(delta);
      const pct = (pesoAntes > 0) ? (delta / pesoAntes) * 100 : 0;

      const alturaM = alturaCm / 100;
      const imcAntes = (alturaM > 0) ? (pesoAntes / (alturaM*alturaM)) : 0;
      const imcDesp  = (alturaM > 0) ? (pesoDespues / (alturaM*alturaM)) : 0;

      let hidrClass = 'ok', hidrMsg = 'Hidratación OK';
      if (pct <= -1 && pct > -2) { hidrClass = 'warn'; hidrMsg = 'Posible deshidratación leve'; }
      if (pct <= -2)             { hidrClass = 'bad';  hidrMsg = 'Riesgo de deshidratación'; }

      document.getElementById('resultado_calorias').innerText = calorias.toFixed(0) + ' kcal';
      document.getElementById('calorias_quemadas').value = calorias.toFixed(0);

      document.getElementById('peso_delta').innerText = (delta >= 0 ? '+' : '−') + deltaAbs.toFixed(2) + ' kg';
      document.getElementById('peso_pct').innerText   = (pct >= 0 ? '+' : '') + pct.toFixed(2) + ' %';

      document.getElementById('imc_antes').innerText = (imcAntes || 0).toFixed(1);
      document.getElementById('imc_desp').innerText  = (imcDesp  || 0).toFixed(1);

      const hidr = document.getElementById('hidr_msg');
      hidr.className = 'out ' + (hidrClass === 'ok' ? 'ok' : (hidrClass === 'warn' ? 'warn' : 'bad'));
      hidr.innerText = hidrMsg;
    }
    document.addEventListener('DOMContentLoaded', calcAll);
  </script>
</head>
<body>

  <?php
    // ===== Menú REUSABLE =====
    require_once __DIR__.'/menu_cliente.php';
    render_menu_cliente('form_progreso'); // pestaña activa
  ?>

  <div class="container">
    <h2>📈 Registrar Progreso Físico</h2>

    <?php if (isset($_GET['ok'])): ?>
      <div class="alert glass">✅ Progreso guardado correctamente.</div>
    <?php endif; ?>

    <div class="grid">
      <!-- Formulario -->
      <form id="progresoForm" method="POST" action="guardar_progreso.php" oninput="calcAll()" class="glass card formulario" autocomplete="off" novalidate>
        <label for="peso_antes">Peso antes del entrenamiento</label>
        <div class="control">
          <input form="progresoForm" type="number" name="peso_antes" id="peso_antes" step="0.1" min="0.1" inputmode="decimal" placeholder="Ej: 78.5" value="<?= h($peso_prefill) ?>" required>
          <span class="unit">kg</span>
        </div>

        <label for="peso_despues">Peso después del entrenamiento</label>
        <div class="control">
          <input form="progresoForm" type="number" name="peso_despues" id="peso_despues" step="0.1" min="0.1" inputmode="decimal" placeholder="Ej: 78.0" required>
          <span class="unit">kg</span>
        </div>

        <label for="altura">Altura</label>
        <div class="control">
          <input form="progresoForm" type="number" name="altura" id="altura" step="0.1" min="30" inputmode="decimal" placeholder="Ej: 175" value="<?= h($altura_prefill) ?>" required>
          <span class="unit">cm</span>
        </div>

        <label for="duracion">Duración del entrenamiento</label>
        <div class="control">
          <input form="progresoForm" type="number" name="duracion" id="duracion" step="1" min="0" inputmode="numeric" placeholder="Ej: 60" required>
          <span class="unit">min</span>
        </div>

        <label for="esfuerzo">Nivel de esfuerzo físico</label>
        <div class="control">
          <select form="progresoForm" name="esfuerzo" id="esfuerzo" required>
            <option value="bajo">Bajo</option>
            <option value="medio" selected>Medio</option>
            <option value="alto">Alto</option>
          </select>
        </div>

        <label for="objetivo">Objetivo</label>
        <div class="control">
          <select form="progresoForm" name="objetivo" id="objetivo" required>
            <option value="mantener" selected>Mantener</option>
            <option value="bajar">Bajar de peso</option>
            <option value="subir">Subir de peso</option>
          </select>
        </div>

        <label for="enfermedades">Condiciones médicas (opcional)</label>
        <div class="control">
          <input form="progresoForm" type="text" name="enfermedades" id="enfermedades" placeholder="Si aplica">
        </div>

        <label for="notas">Notas / Observaciones <span class="muted">(opcional)</span></label>
        <div class="control">
          <textarea form="progresoForm" name="notas" id="notas" rows="3" placeholder="Anota sensaciones, ejercicios clave, carga, etc."></textarea>
        </div>

        <!-- Outputs en vivo -->
        <div class="outputs">
          <div class="out">🔥 Calorías: <span id="resultado_calorias">0 kcal</span></div>
          <div class="out">⚖️ Variación: <span id="peso_delta">0.00 kg</span> <span class="muted">(<span id="peso_pct">0.00 %</span>)</span></div>
          <div class="out">📏 IMC antes: <span id="imc_antes">0.0</span></div>
          <div class="out">📏 IMC después: <span id="imc_desp">0.0</span></div>
          <div class="out ok" id="hidr_msg">Hidratación OK</div>
        </div>

        <input form="progresoForm" type="hidden" name="calorias_quemadas" id="calorias_quemadas" value="0">
        <button form="progresoForm" type="submit" class="btn">Guardar Progreso</button>
      </form>

      <!-- Historial -->
      <section class="glass card">
        <h3 style="margin:0 0 6px">📚 Historial <?= empty($historial) ? '' : '(últimos 10)' ?></h3>
        <?php if (!empty($historial)): ?>
          <table>
            <thead>
              <tr>
                <th class="nowrap">Fecha</th>
                <th class="nowrap">Objetivo</th>
                <th>Peso antes</th>
                <th>Peso después</th>
                <th>Δ kg</th>
                <th>Altura</th>
                <th>IMC↓</th>
                <th>Dur.</th>
                <th>Kcal</th>
                <th>Notas</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historial as $r):
                $pa  = (float)($r['peso_antes'] ?? 0);
                $pd  = (float)($r['peso_despues'] ?? 0);
                $acm = (float)($r['altura_cm'] ?? 0);
                $delta = $pd - $pa;
                $imc = ($acm>0) ? $pd / pow($acm/100,2) : 0;
                $obj = (string)($r['objetivo'] ?? '');
              ?>
              <tr>
                <td><?= h($r['fecha'] ?? '') ?></td>
                <td><?= h($obj ?: '—') ?></td>
                <td><?= number_format($pa,1,',','.') ?> kg</td>
                <td><?= number_format($pd,1,',','.') ?> kg</td>
                <td><?= ($delta>=0?'+':'−').number_format(abs($delta),2,',','.') ?> kg</td>
                <td><?= number_format($acm,0,',','.') ?> cm</td>
                <td><?= number_format($imc,1,',','.') ?></td>
                <td><?= (int)($r['duracion_min'] ?? 0) ?> min</td>
                <td><?= (int)($r['calorias_quemadas'] ?? 0) ?> kcal</td>
                <td><?= h((string)($r['notas'] ?? '')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="muted" style="margin:0">Aún no hay registros para mostrar.</p>
        <?php endif; ?>
      </section>
    </div>
  </div>

  <script>
  // ===== Menú (abrir/cerrar + bloquear scroll) =====
  (function(){
    const drawer   = document.getElementById('mnu-drawer');
    const backdrop = document.getElementById('mnu-backdrop');
    const openBtn  = document.querySelector('.mnu-open');
    const closeBtn = document.getElementById('mnu-close');
    const lock = (on)=>{ document.documentElement.style.overflow = document.body.style.overflow = on?'hidden':''; }
    function open(){ drawer.classList.add('open'); backdrop.classList.add('show'); lock(true); }
    function close(){ drawer.classList.remove('open'); backdrop.classList.remove('show'); lock(false); }
    openBtn?.addEventListener('click', open);
    closeBtn?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);
    window.addEventListener('keydown', e=>{ if(e.key==='Escape') close(); });
  })();
  </script>
</body>
</html>
