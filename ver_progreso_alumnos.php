<?php
// progreso_alumnos.php — Panel de evolución (versión pro con buscador en vivo)
// Requisitos: clientes, asistencias, reservas_clientes, turnos_disponibles, progreso_cliente

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Argentina/San_Luis');

require __DIR__ . '/conexion.php';

$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) {
  http_response_code(403);
  echo "<div style='color:#fca5a5;padding:14px;text-align:center'>Acceso denegado.</div>";
  exit;
}

// ========= Helpers =========
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function full_name($ap, $nom){ $t = trim("$ap $nom"); return $t!==''? $t : '—'; }
if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

// ========= AJAX: buscador en vivo de clientes =========
// Uso: GET ?ajax=buscar_clientes&term=algo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar_clientes') {
  header('Content-Type: application/json; charset=utf-8');
  $term = trim($_GET['term'] ?? '');
  $rows = [];
  if ($term !== '') {
    $like = '%' . $conexion->real_escape_string($term) . '%';
    $sql = "
      SELECT id, apellido, nombre, dni, telefono
      FROM clientes
      WHERE gimnasio_id = {$gimnasio_id}
        AND (
          apellido LIKE '{$like}' OR nombre LIKE '{$like}' OR
          CONCAT(apellido,' ',nombre) LIKE '{$like}' OR
          dni LIKE '{$like}' OR telefono LIKE '{$like}'
        )
      ORDER BY apellido, nombre
      LIMIT 15
    ";
    if ($q = $conexion->query($sql)) {
      while($r = $q->fetch_assoc()){
        $rows[] = [
          'id'   => (int)$r['id'],
          'text' => full_name($r['apellido'] ?? '', $r['nombre'] ?? '') .
                   (empty($r['dni']) ? '' : ' · DNI ' . $r['dni'])
        ];
      }
    }
  }
  echo json_encode(['ok'=>true, 'items'=>$rows]);
  exit;
}

// ========= Filtros =========
$filtro     = $_GET['filtro'] ?? 'mensual';
$alumno_id  = (int)($_GET['alumno_id'] ?? 0);
$hoy        = date('Y-m-d');

switch ($filtro) {
  case 'semanal': $fecha_inicio = date('Y-m-d', strtotime('-7 days')); break;
  case 'anual':   $fecha_inicio = date('Y-01-01'); break;
  case 'mensual':
  default:        $fecha_inicio = date('Y-m-01'); break;
}

// ========= Listado base (alumnos que tuvieron asistencias con este profe) =========
$alumnos = [];
if ($q = $conexion->query("
  SELECT DISTINCT
    c.id,
    c.apellido,
    c.nombre,
    CONCAT(c.apellido,' ',c.nombre) AS nombre
  FROM asistencias a
  JOIN clientes c ON a.cliente_id = c.id
  WHERE a.profesor_id = {$profesor_id}
    AND a.gimnasio_id = {$gimnasio_id}
  ORDER BY c.apellido, c.nombre
")) {
  while($r = $q->fetch_assoc()){ $alumnos[] = $r; }
}

// ========= Datos de evolución del cliente seleccionado =========
$progresos = [];
$kpis = [
  'asistencias' => 0,
  'prom_esfuerzo' => 0.0,
  'calorias_total' => 0.0,
  'duracion_total' => 0,   // minutos
  'peso_delta' => null,    // kg (último - primero)
  'primera_fecha' => null,
  'ultima_fecha' => null,
];
$cliente = null;
$series = [
  'labels'        => [],
  'pesoAntes'     => [],
  'pesoDespues'   => [],
  'esfuerzo'      => [],
  'calorias'      => [],
];

if ($alumno_id > 0) {
  // Cliente (validar gimnasio)
  if ($qc = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE id={$alumno_id} AND gimnasio_id={$gimnasio_id} LIMIT 1")) {
    $cliente = $qc->fetch_assoc();
  }

  // KPIs: Asistencias en el período
  if ($qa = $conexion->query("
      SELECT COUNT(*) AS c
      FROM asistencias
      WHERE cliente_id = {$alumno_id}
        AND gimnasio_id = {$gimnasio_id}
        AND fecha BETWEEN '{$fecha_inicio}' AND '{$hoy}'
    ")) {
    if ($ra = $qa->fetch_assoc()) $kpis['asistencias'] = (int)$ra['c'];
  }

  // Progreso (orden ASC para calcular bien deltas)
  if ($res = $conexion->query("
      SELECT fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades
      FROM progreso_cliente
      WHERE cliente_id = {$alumno_id}
        AND gimnasio_id = {$gimnasio_id}
        AND fecha BETWEEN '{$fecha_inicio}' AND '{$hoy}'
      ORDER BY fecha ASC
    ")) {
    $firstPeso = null;
    $lastPeso = null;

    while ($row = $res->fetch_assoc()) {
      $progresos[] = $row;

      $fecha = (string)$row['fecha'];
      $series['labels'][] = $fecha;
      $series['pesoAntes'][]   = is_numeric($row['peso_antes']) ? (float)$row['peso_antes'] : null;
      $series['pesoDespues'][] = is_numeric($row['peso_despues']) ? (float)$row['peso_despues'] : null;
      $series['esfuerzo'][]    = is_numeric($row['esfuerzo']) ? (float)$row['esfuerzo'] : null;
      $series['calorias'][]    = is_numeric($row['calorias_estimadas']) ? (float)$row['calorias_estimadas'] : null;

      // KPIs adicionales
      if (is_numeric($row['duracion_entrenamiento'])) $kpis['duracion_total'] += (int)$row['duracion_entrenamiento'];
      if (is_numeric($row['calorias_estimadas']))     $kpis['calorias_total'] += (float)$row['calorias_estimadas'];

      if ($kpis['primera_fecha'] === null) $kpis['primera_fecha'] = $fecha;
      $kpis['ultima_fecha'] = $fecha;

      // Delta de peso (primero vs último del período)
      if ($firstPeso === null && is_numeric($row['peso_antes'])) $firstPeso = (float)$row['peso_antes'];
      if (is_numeric($row['peso_despues'])) $lastPeso = (float)$row['peso_despues'];
    }

    // Promedio esfuerzo
    $esf_validos = array_values(array_filter($series['esfuerzo'], fn($v)=>$v!==null));
    $kpis['prom_esfuerzo'] = count($esf_validos) ? (array_sum($esf_validos)/count($esf_validos)) : 0.0;

    // Delta de peso
    if ($firstPeso !== null && $lastPeso !== null) $kpis['peso_delta'] = $lastPeso - $firstPeso;
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Progreso de Alumnos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<!-- ✅ Carga el estilo unificado ANTES de imprimir el menú -->
<link rel="stylesheet" href="/multi_gimnasio/estilo_unificado.css?v=20251006">
<!-- Chart.js para gráficos (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#0b0d12; --surface:#11141b; --card:#151926; --muted:#95a3b8;
  --text:#e8eef6; --accent:#f5b301; --ring:#26324a; --ok:#22c55e; --warn:#f59e0b; --danger:#ef4444;
}
*{box-sizing:border-box}
html,body{height:100%}
body{
  margin:0; font-family: Inter, ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Arial;
  background: radial-gradient(1200px 800px at 80% -10%, #1c2235 0%, #0b0d12 45%) no-repeat, var(--bg);
  color:var(--text);
}
.container{max-width:1200px; margin-inline:auto; padding:20px}
.header{
  display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between;
  gap:12px; padding:16px 20px; background:linear-gradient(180deg, #1a2031, #141a27);
  border:1px solid #1f2a44; border-radius:16px; box-shadow:0 10px 30px rgba(0,0,0,.25);
}
.header h1{font-size:1.25rem; margin:0; letter-spacing:.3px}
.badge{
  display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
  background:rgba(245,179,1,.1); border:1px solid rgba(245,179,1,.25); color:var(--accent);
  font-weight:600; letter-spacing:.2px
}

/* Buscador y filtros */
.filters{
  margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center
}
.input{
  background:#101628; border:1px solid #1e2740; color:var(--text); padding:10px 12px; border-radius:12px;
  outline:none; min-width:260px
}
.select{ composes: input; } /* (solo documental) */
.button{
  background:var(--accent); color:#1b1200; font-weight:800; border:none; padding:10px 14px; border-radius:12px;
  cursor:pointer
}
.suggest{
  position:relative; min-width:280px
}
.suggest-list{
  position:absolute; top:46px; left:0; right:0; background:#0f1527; border:1px solid #1e2740; border-radius:12px;
  box-shadow:0 20px 40px rgba(0,0,0,.4); max-height:280px; overflow:auto; z-index:50
}
.suggest-item{
  padding:10px 12px; cursor:pointer; border-bottom:1px solid #1a2340; color:#e6ecff
}
.suggest-item:hover{ background:#0d1322 }

/* KPIs */
.kpi-grid{
  display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-top:16px
}
@media (max-width: 960px){ .kpi-grid{ grid-template-columns: repeat(2, 1fr);} }
@media (max-width: 520px){ .kpi-grid{ grid-template-columns: 1fr;} }
.kpi{
  background:var(--card); border:1px solid #1f2a44; border-radius:16px; padding:16px;
  box-shadow: 0 6px 20px rgba(0,0,0,.25); position:relative; overflow:hidden
}
.kpi h3{margin:0 0 8px 0; font-size:.95rem; color:var(--muted); font-weight:600}
.kpi .value{font-size:1.8rem; font-weight:800; letter-spacing:.3px}
.kpi .hint{font-size:.85rem; color:var(--muted)}
.kpi .emoji{position:absolute; right:10px; top:8px; font-size:1.4rem; opacity:.8}

/* Grid principal */
.grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap:18px; margin-top:18px }
@media (max-width: 1100px){ .grid{ grid-template-columns: 1fr; } }
.card{
  background:var(--card); border:1px solid #1f2a44; border-radius:16px; padding:16px;
  box-shadow: 0 6px 20px rgba(0,0,0,.25);
}
.card h2{margin:0 0 10px 0; font-size:1.05rem; color:var(--muted); letter-spacing:.2px}

/* Tabla */
.table{width:100%; border-collapse:separate; border-spacing:0 10px}
.table th{font-size:.9rem; color:var(--muted); text-align:left; padding:6px 10px}
.table td{
  background:#101628; border:1px solid #1e2740; padding:10px; border-radius:10px;
  vertical-align:top; text-align:left
}
.mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;}
.small{font-size:.9rem; color:var(--muted)}
.ok{color:var(--ok)} .warn{color:var(--warn)} .danger{color:var(--danger)}
</style>
</head>
<body>

<?php
// ✅ Incluir menú compartido DESPUÉS de cargar el CSS global
require_once __DIR__ . '/menu_profesor.php';
?>

<div class="container">

  <div class="header">
    <div>
      <div class="badge">📈 Progreso de Alumnos</div>
      <h1>Evolución y métricas</h1>
    </div>
  </div>

  <!-- Buscador en vivo + filtros -->
  <form class="filters" method="GET" id="filtrosForm">
    <div class="suggest">
      <input type="hidden" name="alumno_id" id="alumno_id" value="<?= (int)$alumno_id ?>">
      <input type="text" id="buscar" class="input" placeholder="🔎 Buscar cliente (nombre, DNI, teléfono)" autocomplete="off">
      <div id="suggestList" class="suggest-list" style="display:none"></div>
    </div>

    <select name="filtro" class="input" onchange="document.getElementById('filtrosForm').submit()">
      <option value="semanal" <?= $filtro==='semanal'?'selected':'' ?>>Semanal</option>
      <option value="mensual" <?= $filtro==='mensual'?'selected':'' ?>>Mensual</option>
      <option value="anual"   <?= $filtro==='anual'?'selected':'' ?>>Anual</option>
    </select>

    <button class="button" type="submit">Aplicar</button>

    <!-- Atajo: lista clásica de alumnos del profe -->
    <select class="input" onchange="location.href='?alumno_id='+this.value+'&filtro=<?= h($filtro) ?>'">
      <option value="">Tus alumnos recientes</option>
      <?php foreach($alumnos as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$alumno_id)?'selected':'' ?>>
          <?= h($a['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($alumno_id && $cliente): ?>
    <!-- KPIs -->
    <div class="kpi-grid">
      <div class="kpi">
        <div class="emoji">🧍</div>
        <h3>Alumno</h3>
        <div class="value" style="font-size:1.2rem"><?= h(full_name($cliente['apellido']??'', $cliente['nombre']??'')) ?></div>
        <div class="hint">ID: <span class="mono"><?= (int)$alumno_id ?></span></div>
      </div>
      <div class="kpi">
        <div class="emoji">⏳</div>
        <h3>Asistencias</h3>
        <div class="value"><?= (int)$kpis['asistencias'] ?></div>
        <div class="hint"><?= h($fecha_inicio) ?> → <?= h($hoy) ?></div>
      </div>
      <div class="kpi">
        <div class="emoji">🔥</div>
        <h3>Calorías estimadas</h3>
        <div class="value"><?= number_format((float)$kpis['calorias_total'], 0, ',', '.') ?></div>
        <div class="hint">Suma en el período</div>
      </div>
      <div class="kpi">
        <div class="emoji">💪</div>
        <h3>Esfuerzo promedio</h3>
        <div class="value"><?= number_format((float)$kpis['prom_esfuerzo'], 1, ',', '.') ?></div>
        <div class="hint">Escala según registro</div>
      </div>
      <div class="kpi">
        <div class="emoji">🏁</div>
        <h3>Duración total</h3>
        <div class="value"><?= (int)$kpis['duracion_total'] ?> <span class="small">min</span></div>
        <div class="hint"><?= $kpis['primera_fecha'] ? h($kpis['primera_fecha']).' → '.h($kpis['ultima_fecha']) : '—' ?></div>
      </div>
      <div class="kpi">
        <div class="emoji">⚖️</div>
        <h3>Δ Peso</h3>
        <?php
          $delta = $kpis['peso_delta'];
          $deltaTxt = $delta===null ? '—' : number_format($delta, 1, ',', '.').' kg';
          $deltaCls = $delta===null ? '' : ($delta<0 ? 'ok' : ($delta>0 ? 'danger' : ''));
        ?>
        <div class="value <?= $deltaCls ?>"><?= $deltaTxt ?></div>
        <div class="hint"><?= $delta===null?'Sin suficientes registros':'Último vs. primero del período' ?></div>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <h2>📉 Evolución (peso, esfuerzo y calorías)</h2>
        <div style="padding:8px 0">
          <canvas id="chartPeso" height="140"></canvas>
        </div>
        <div style="padding:8px 0">
          <canvas id="chartEsfuerzo" height="120"></canvas>
        </div>
        <div style="padding:8px 0">
          <canvas id="chartCalorias" height="120"></canvas>
        </div>
      </div>

      <div class="card">
        <h2>📅 Reservas próximas (7 días)</h2>
        <?php
          $prox = $conexion->query("
            SELECT r.fecha_reserva, r.hora_inicio, td.hora_fin AS turno_hora_fin
            FROM reservas_clientes r
            LEFT JOIN turnos_disponibles td ON r.turno_id = td.id
            WHERE r.gimnasio_id = {$gimnasio_id}
              AND r.cliente_id = {$alumno_id}
              AND r.fecha_reserva BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY r.fecha_reserva, r.hora_inicio
            LIMIT 10
          ");
        ?>
        <?php if ($prox && $prox->num_rows > 0): ?>
          <table class="table">
            <thead><tr><th>Fecha</th><th>Inicio</th><th>Fin</th></tr></thead>
            <tbody>
              <?php while($px = $prox->fetch_assoc()): ?>
                <tr>
                  <td><span class="mono"><?= h($px['fecha_reserva'] ?? '') ?></span></td>
                  <td><span class="mono"><?= h($px['hora_inicio'] ?? '') ?></span></td>
                  <td><span class="mono"><?= h($px['turno_hora_fin'] ?? '—') ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="small">No hay reservas próximas.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <h2>📋 Registros del período</h2>
      <?php if (!empty($progresos)): ?>
        <table class="table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Peso antes</th>
              <th>Peso después</th>
              <th>Esfuerzo</th>
              <th>Duración (min)</th>
              <th>Calorías</th>
              <th>Enfermedades</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($progresos as $p): ?>
              <tr>
                <td><span class="mono"><?= h($p['fecha']) ?></span></td>
                <td><?= is_numeric($p['peso_antes'])   ? number_format((float)$p['peso_antes'], 1, ',', '.')   : '—' ?> kg</td>
                <td><?= is_numeric($p['peso_despues']) ? number_format((float)$p['peso_despues'],1, ',', '.') : '—' ?> kg</td>
                <td><?= is_numeric($p['esfuerzo'])     ? number_format((float)$p['esfuerzo'], 1, ',', '.')     : '—' ?></td>
                <td><?= is_numeric($p['duracion_entrenamiento']) ? (int)$p['duracion_entrenamiento'] : '—' ?></td>
                <td><?= is_numeric($p['calorias_estimadas']) ? number_format((float)$p['calorias_estimadas'],0, ',', '.') : '—' ?></td>
                <td><?= h($p['enfermedades'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="small" style="color:#fca5a5">No hay datos registrados para este período.</div>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="card" style="margin-top:16px">
      <h2>👆 Buscá un alumno para ver su evolución</h2>
      <p class="small">Tip: empezá a escribir nombre, apellido, DNI o teléfono y elegí de la lista.</p>
    </div>
  <?php endif; ?>

</div><!-- /container -->

<script>
// ===== Buscador en vivo (mismo PHP responde con JSON) =====
const buscar = document.getElementById('buscar');
const list = document.getElementById('suggestList');
const alumnoInput = document.getElementById('alumno_id');

let tId;
buscar.addEventListener('input', ()=>{
  const term = buscar.value.trim();
  if (tId) clearTimeout(tId);
  if (term.length < 2) { list.style.display='none'; list.innerHTML=''; return; }
  tId = setTimeout(async ()=>{
    const res = await fetch(`?ajax=buscar_clientes&term=${encodeURIComponent(term)}`, {headers:{'Accept':'application/json'}});
    const data = await res.json();
    list.innerHTML = '';
    if (data && data.items && data.items.length){
      data.items.forEach(it=>{
        const div = document.createElement('div');
        div.className = 'suggest-item';
        div.textContent = it.text;
        div.onclick = ()=>{
          alumnoInput.value = it.id;
          // enviamos con filtro actual
          const params = new URLSearchParams(window.location.search);
          const filtro = params.get('filtro') || '<?= h($filtro) ?>';
          window.location.href = `?alumno_id=${it.id}&filtro=${filtro}`;
        };
        list.appendChild(div);
      });
      list.style.display = 'block';
    } else {
      list.style.display = 'none';
    }
  }, 250);
});
document.addEventListener('click', (e)=>{ if(!list.contains(e.target) && e.target!==buscar){ list.style.display='none'; } });

// ===== Charts =====
<?php if ($alumno_id && $cliente): ?>
const labels      = <?= json_encode($series['labels'], JSON_UNESCAPED_UNICODE) ?>;
const pesoAntes   = <?= json_encode($series['pesoAntes']) ?>;
const pesoDespues = <?= json_encode($series['pesoDespues']) ?>;
const esfuerzo    = <?= json_encode($series['esfuerzo']) ?>;
const calorias    = <?= json_encode($series['calorias']) ?>;

function makeLine(elId, label, data){
  const ctx = document.getElementById(elId);
  if (!ctx || !labels.length) return;
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [{ label, data, tension:.3, fill:false }] },
    options: {
      plugins:{ legend:{ labels:{ color:'#cfd8e3' } } },
      scales: {
        x: { ticks:{ color:'#9fb0c7' }, grid:{ color:'rgba(255,255,255,.06)' } },
        y: { ticks:{ color:'#9fb0c7' }, grid:{ color:'rgba(255,255,255,.06)' } },
      }
    }
  });
}
makeLine('chartPeso', 'Peso (kg)', pesoDespues.map((v,i)=> v ?? pesoAntes[i] ?? null));
makeLine('chartEsfuerzo', 'Esfuerzo', esfuerzo);
makeLine('chartCalorias', 'Calorías', calorias);
<?php endif; ?>
</script>
</body>
</html>
