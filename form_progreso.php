<?php
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

// ==== helpers DB (introspección segura) ====
function db_has_table(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $res = $db->query("SHOW TABLES LIKE '{$t}'");
  return ($res && $res->num_rows > 0);
}
function db_has_col(mysqli $db, string $t, string $c): bool {
  $t = $db->real_escape_string($t);
  $c = $db->real_escape_string($c);
  $res = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return ($res && $res->num_rows > 0);
}
function pick_col(mysqli $db, string $t, array $candidates): ?string {
  foreach ($candidates as $c) if (db_has_col($db, $t, $c)) return $c;
  return null;
}

// === Prefill desde datos_fisicos (último registro) ===
$peso_prefill = '';
$altura_prefill = '';
if (db_has_table($conexion, 'datos_fisicos')) {
  $pesoCol   = pick_col($conexion, 'datos_fisicos', ['peso','peso_kg']);
  $alturaCol = pick_col($conexion, 'datos_fisicos', ['altura_cm','altura']);
  $fechaCol  = pick_col($conexion, 'datos_fisicos', ['fecha','created_at','fecha_registro']);
  if ($pesoCol && $alturaCol && $fechaCol) {
    $sqlPF = "SELECT `$pesoCol` AS peso, `$alturaCol` AS altura
              FROM `datos_fisicos`
              WHERE cliente_id=? AND gimnasio_id=?
              ORDER BY `$fechaCol` DESC
              LIMIT 1";
    if ($stPF = @$conexion->prepare($sqlPF)) {
      $stPF->bind_param("ii", $cliente_id, $gimnasio_id);
      if ($stPF->execute()) {
        if ($row = $stPF->get_result()->fetch_assoc()) {
          $peso_prefill   = trim((string)($row['peso'] ?? ''));
          $altura_prefill = trim((string)($row['altura'] ?? ''));
        }
      }
      $stPF->close();
    }
  }
}

// === Historial robusto (detecta tabla/columnas y evita fatales) ===
$historial = [];
$tables = ['progreso','progreso_fisico','progresos'];
$t = null;
foreach ($tables as $tb) { if (db_has_table($conexion, $tb)) { $t = $tb; break; } }

if ($t) {
  // columnas posibles
  $cFecha = pick_col($conexion, $t, ['fecha','created_at','fecha_registro']);
  $cPA    = pick_col($conexion, $t, ['peso_antes','peso_inicio']);
  $cPD    = pick_col($conexion, $t, ['peso_despues','peso_fin','peso_post']);
  $cAlt   = pick_col($conexion, $t, ['altura_cm','altura']);
  $cDur   = pick_col($conexion, $t, ['duracion_min','duracion']);
  $cCal   = pick_col($conexion, $t, ['calorias_quemadas','calorias']);

  // filtros (por si alguna tabla no tuviera las dos columnas)
  $cCli   = pick_col($conexion, $t, ['cliente_id','id_cliente']);
  $cGym   = pick_col($conexion, $t, ['gimnasio_id','id_gimnasio']);

  // SELECT parts solo con columnas existentes; lo que no exista, lo suplimos con NULL/constante
  $parts = [];
  $parts[] = $cFecha ? "`$cFecha` AS fecha"           : "'0000-00-00' AS fecha";
  $parts[] = $cPA    ? "`$cPA` AS peso_antes"         : "NULL AS peso_antes";
  $parts[] = $cPD    ? "`$cPD` AS peso_despues"       : "NULL AS peso_despues";
  $parts[] = $cAlt   ? "`$cAlt` AS altura_cm"         : "NULL AS altura_cm";
  $parts[] = $cDur   ? "`$cDur` AS duracion_min"      : "NULL AS duracion_min";
  $parts[] = $cCal   ? "`$cCal` AS calorias_quemadas" : "NULL AS calorias_quemadas";

  $sql = "SELECT ".implode(", ", $parts)." FROM `{$t}` WHERE 1";
  $bindType = ''; $bindVals = [];

  if ($cCli) { $sql .= " AND `$cCli` = ?"; $bindType .= 'i'; $bindVals[] = $cliente_id; }
  if ($cGym) { $sql .= " AND `$cGym` = ?"; $bindType .= 'i'; $bindVals[] = $gimnasio_id; }

  $orderCol = $cFecha ?: (db_has_col($conexion,$t,'id') ? 'id' : null);
  if ($orderCol) $sql .= " ORDER BY `$orderCol` DESC";
  $sql .= " LIMIT 10";

  if ($st = @$conexion->prepare($sql)) {
    if ($bindType !== '') {
      // bindeo simple (dos casos) para evitar call_user_func_array
      if ($bindType === 'i')      { $st->bind_param('i', $bindVals[0]); }
      elseif ($bindType === 'ii') { $st->bind_param('ii', $bindVals[0], $bindVals[1]); }
    }
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
    .grid{ display:grid; gap:16px; grid-template-columns: 1fr; }
    @media (min-width:900px){ .grid{ grid-template-columns: 1fr 1fr; } }
    .card{ background:#111; border:1px solid var(--border); border-radius:16px; padding:16px; }
    .formulario label{ display:block; margin:12px 0 6px; font-weight:700; color:var(--acc); }
    .control{ position:relative }
    .control input, .control select{
      width:100%; padding:12px 42px 12px 12px; border:1px solid #333; border-radius:12px;
      background:#1a1d24; color:var(--fg); font-size:16px;
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

    <div class="grid">
      <!-- Formulario -->
      <form method="POST" action="guardar_progreso.php" oninput="calcAll()" class="card formulario" autocomplete="off" novalidate>
        <label for="peso_antes">Peso antes del entrenamiento</label>
        <div class="control">
          <input type="number" name="peso_antes" id="peso_antes" step="0.1" min="0" inputmode="decimal" placeholder="Ej: 78.5" value="<?= h($peso_prefill) ?>" required>
          <span class="unit">kg</span>
        </div>

        <label for="peso_despues">Peso después del entrenamiento</label>
        <div class="control">
          <input type="number" name="peso_despues" id="peso_despues" step="0.1" min="0" inputmode="decimal" placeholder="Ej: 78.0" required>
          <span class="unit">kg</span>
        </div>

        <label for="altura">Altura</label>
        <div class="control">
          <input type="number" name="altura" id="altura" step="0.1" min="0" inputmode="decimal" placeholder="Ej: 175" value="<?= h($altura_prefill) ?>" required>
          <span class="unit">cm</span>
        </div>

        <label for="duracion">Duración del entrenamiento</label>
        <div class="control">
          <input type="number" name="duracion" id="duracion" step="1" min="0" inputmode="numeric" placeholder="Ej: 60" required>
          <span class="unit">min</span>
        </div>

        <label for="esfuerzo">Nivel de esfuerzo físico</label>
        <div class="control">
          <select name="esfuerzo" id="esfuerzo" required>
            <option value="bajo">Bajo</option>
            <option value="medio" selected>Medio</option>
            <option value="alto">Alto</option>
          </select>
        </div>

        <label for="enfermedades">Condiciones médicas (ej: diabetes, hipertensión) <span class="muted">(opcional)</span></label>
        <div class="control">
          <input type="text" name="enfermedades" id="enfermedades" placeholder="Si aplica">
        </div>

        <!-- Outputs en vivo -->
        <div class="outputs">
          <div class="out">🔥 Calorías: <span id="resultado_calorias">0 kcal</span></div>
          <div class="out">⚖️ Variación: <span id="peso_delta">0.00 kg</span> <span class="muted">(<span id="peso_pct">0.00 %</span>)</span></div>
          <div class="out">📏 IMC antes: <span id="imc_antes">0.0</span></div>
          <div class="out">📏 IMC después: <span id="imc_desp">0.0</span></div>
          <div class="out ok" id="hidr_msg">Hidratación OK</div>
        </div>

        <input type="hidden" name="calorias_quemadas" id="calorias_quemadas" value="0">
        <button type="submit" class="btn">Guardar Progreso</button>
      </form>

      <!-- Historial -->
      <?php if (!empty($historial)): ?>
        <section class="card">
          <h3 style="margin:0 0 6px">📚 Historial (últimos 10)</h3>
          <table>
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Peso antes</th>
                <th>Peso después</th>
                <th>Δ kg</th>
                <th>Altura</th>
                <th>IMC↓</th>
                <th>Dur.</th>
                <th>Kcal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($historial as $r):
                $pa = (float)($r['peso_antes'] ?? 0);
                $pd = (float)($r['peso_despues'] ?? 0);
                $acm = (float)($r['altura_cm'] ?? 0);
                $delta = $pd - $pa;
                $imc = ($acm>0) ? $pd / pow($acm/100,2) : 0;
              ?>
              <tr>
                <td><?= h($r['fecha'] ?? '') ?></td>
                <td><?= number_format($pa,1,',','.') ?> kg</td>
                <td><?= number_format($pd,1,',','.') ?> kg</td>
                <td><?= ($delta>=0?'+':'−').number_format(abs($delta),2,',','.') ?> kg</td>
                <td><?= number_format($acm,0,',','.') ?> cm</td>
                <td><?= number_format($imc,1,',','.') ?></td>
                <td><?= (int)($r['duracion_min'] ?? 0) ?> min</td>
                <td><?= (int)($r['calorias_quemadas'] ?? 0) ?> kcal</td>
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
