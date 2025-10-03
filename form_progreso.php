<?php
// registrar_progreso.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';
include __DIR__ . '/menu_cliente.php';

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
  $sqlPF = "SELECT peso AS p, altura_cm AS a
            FROM datos_fisicos
            WHERE cliente_id=? AND gimnasio_id=?
            ORDER BY fecha DESC, id DESC
            LIMIT 1";
  // si tus columnas son otras (peso_kg / altura), cambia el SELECT de arriba
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

// === Historial (tabla fija: progreso) ===
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
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Progreso Físico</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="estilo_unificado.css">
  <style>
    :root{ --bg:#0b0b0b; --card:#12141a; --fg:#f1f5f9; --muted:#a0a7b4; --acc:#f5c542; --border:rgba(255,255,255,.12); }
    *{box-sizing:border-box}
    body{ margin:0; background:var(--bg); color:var(--fg); font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial }
    .contenedor{ max-width:1100px; margin:16px auto 48px; padding:0 16px; }
    h2{ text-align:center; margin:10px 0 18px; }
    .alert{ background:#112b1a; border:1px solid #1f6f3d; color:#a7f3d0; padding:10px 12px; border-radius:10px; margin:8px 0 18px; }
    .grid{ display:grid; gap:16px; grid-template-columns: 1fr; }
    @media (min-width:900px){ .grid{ grid-template-columns: 1fr 1fr; } }
    .card{ background:#111; border:1px solid var(--border); border-radius:16px; padding:16px; }
    .formulario label{ display:block; margin:12px 0 6px; font-weight:700; color:var(--acc); }
    .control{ position:relative }
    .control input, .control select, .control textarea{
      width:100%; padding:12px 42px 12px 12px; border:1px solid #333; border-radius:12px;
      background:#1a1d24; color:var(--fg); font-size:16px; resize:vertical;
    }
    .unit{
      position:absolute; right:10px; top:50%; transform:translateY(-50%);
      color:#d1d5db; font-size:14px; pointer-events:none;
    }
    .btn{
      width:100%; margin-top:14px; padding:12px; border:none; border-radius:12px;
      background:var(--acc); color:#111; font-weight:800; cursor:pointer; font-size:16px;
    }
    .outputs{ display:grid; gap:10px; grid-template-columns: 1fr; margin-top:10px }
    @media (min-width:520px){ .outputs{ grid-template-columns: repeat(2, 1fr); } }
    .out{ background:#1a1d24; border:1px solid var(--border); border-radius:12px; padding:12px; font-weight:700 }
    .muted{ color:var(--muted); font-weight:400 }
    table{ width:100%; border-collapse:collapse; font-size:14px; margin-top:8px }
    th,td{ padding:10px; border-bottom:1px solid rgba(255,255,255,.08); text-align:left }
    th{ color:var(--muted); font-weight:700 }
    .ok{ color:#22c55e } .warn{ color:#f59e0b } .bad{ color:#ef4444 }
    .nowrap{ white-space:nowrap }
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
  <div class="contenedor">
    <h2>📈 Registrar Progreso Físico</h2>

    <?php if (isset($_GET['ok'])): ?>
      <div class="alert">✅ Progreso guardado correctamente.</div>
    <?php endif; ?>

    <div class="grid">
      <!-- Formulario -->
      <form id="progresoForm" method="POST" action="guardar_progreso.php" oninput="calcAll()" class="card formulario" autocomplete="off" novalidate>
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

        <label for="enfermedades">Condiciones médicas (ej: diabetes, hipertensión) <span class="muted">(opcional)</span></label>
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
      <?php if (!empty($historial)): ?>
        <section class="card">
          <h3 style="margin:0 0 6px">📚 Historial (últimos 10)</h3>
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
        </section>
      <?php else: ?>
        <section class="card">
          <h3 style="margin:0 0 6px">📚 Historial</h3>
          <p class="muted" style="margin:0">Aún no hay registros para mostrar.</p>
        </section>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
