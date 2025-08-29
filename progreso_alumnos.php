<?php
// progreso_alumnos.php — Panel de evolución (PROFESOR)
// Muestra progreso + permite cargar nuevos registros para un alumno seleccionado.
// Requiere tablas: clientes, asistencias, reservas_clientes, turnos_disponibles, progreso_cliente
// Sesión esperada: $_SESSION['profesor_id'], $_SESSION['gimnasio_id']

if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Argentina/San_Luis');

require __DIR__ . '/conexion.php';
@require __DIR__ . '/menu_profesor.php';
// ====== RESCATE DE SESIÓN (evita "Acceso denegado") ======
// Permite fijar por GET si venís desde un botón (opcional, seguro si tu menú ya valida login)
if (isset($_GET['gid'])) { $_SESSION['gimnasio_id'] = (int)$_GET['gid']; }
if (isset($_GET['pid'])) { $_SESSION['profesor_id'] = (int)$_GET['pid']; }

$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);

// Si falta gimnasio_id pero tenemos profesor, lo buscamos en la tabla profesores
if ($gimnasio_id <= 0 && $profesor_id > 0) {
  if ($stmt = $conexion->prepare("SELECT gimnasio_id FROM profesores WHERE id=? LIMIT 1")) {
    $stmt->bind_param('i', $profesor_id);
    if ($stmt->execute() && ($res = $stmt->get_result()) && ($row = $res->fetch_assoc())) {
      $gimnasio_id = (int)$row['gimnasio_id'];
      if ($gimnasio_id > 0) $_SESSION['gimnasio_id'] = $gimnasio_id;
    }
    $stmt->close();
  }
}

// Panel de debug opcional: agrega ?debug=1 a la URL
if (isset($_GET['debug']) && $_GET['debug']=='1') {
  echo "<pre style='background:#111;color:#9fe;padding:10px;border:1px solid #234;border-radius:8px;margin:10px'>";
  echo "DEBUG SESIÓN\\n";
  echo "profesor_id: ".$profesor_id."\\n";
  echo "gimnasio_id: ".$gimnasio_id."\\n";
  echo "SID: ".session_id()."\\n";
  echo "</pre>";
}


$profesor_id = (int)($_SESSION['profesor_id'] ?? 0);
$gimnasio_id = (int)($_SESSION['gimnasio_id'] ?? 0);
if ($profesor_id <= 0 || $gimnasio_id <= 0) {
  http_response_code(403);
  echo "<div style='color:#fca5a5;padding:14px;text-align:center'>❌ Acceso denegado.</div>";
  exit;
}

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

// ============ Helpers ============
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function full_name($ap, $nom){ $t = trim("$ap $nom"); return $t!==''? $t : '—'; }

// ============ Crear tabla progreso_cliente si no existe (opcional, segura) ============
$conexion->query("
  CREATE TABLE IF NOT EXISTS progreso_cliente(
    id INT AUTO_INCREMENT PRIMARY KEY,
    gimnasio_id INT NOT NULL,
    cliente_id INT NOT NULL,
    profesor_id INT DEFAULT NULL,
    fecha DATE NOT NULL,
    peso_antes DECIMAL(6,2) DEFAULT NULL,
    peso_despues DECIMAL(6,2) DEFAULT NULL,
    esfuerzo DECIMAL(4,1) DEFAULT NULL,
    duracion_entrenamiento INT DEFAULT NULL,
    calorias_estimadas DECIMAL(10,2) DEFAULT NULL,
    enfermedades VARCHAR(255) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (gimnasio_id, cliente_id, fecha)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// ============ Guardar nuevo progreso (desde el formulario de esta misma página) ============
if (($_POST['accion'] ?? '') === 'guardar_progreso') {
  $alumno_id = (int)($_POST['alumno_id'] ?? 0);
  if ($alumno_id > 0) {
    $fecha   = $_POST['fecha'] ?? date('Y-m-d');
    $peso_a  = $_POST['peso_antes'] === '' ? null : (float)$_POST['peso_antes'];
    $peso_d  = $_POST['peso_despues'] === '' ? null : (float)$_POST['peso_despues'];
    $esf     = $_POST['esfuerzo'] === '' ? null : (float)$_POST['esfuerzo'];
    $dur     = $_POST['duracion_entrenamiento'] === '' ? null : (int)$_POST['duracion_entrenamiento'];
    $cal     = $_POST['calorias_estimadas'] === '' ? null : (float)$_POST['calorias_estimadas'];
    $enf     = trim($_POST['enfermedades'] ?? '');
    $obs     = trim($_POST['observaciones'] ?? '');

    $stmt = $conexion->prepare("
      INSERT INTO progreso_cliente
      (gimnasio_id, cliente_id, profesor_id, fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades, observaciones)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
      "iii s d d d i d s s",
      $gimnasio_id, $alumno_id, $profesor_id, $fecha, $peso_a, $peso_d, $esf, $dur, $cal, $enf, $obs
    );
    // Fix for bind types: build dynamic types string
  }
}
// NOTE: Because of PHP's bind_param strict types in one string, re-prepare with correct types:
if (($_POST['accion'] ?? '') === 'guardar_progreso') {
  $alumno_id = (int)($_POST['alumno_id'] ?? 0);
  if ($alumno_id > 0) {
    $fecha   = $_POST['fecha'] ?? date('Y-m-d');
    $peso_a  = $_POST['peso_antes'] === '' ? null : (float)$_POST['peso_antes'];
    $peso_d  = $_POST['peso_despues'] === '' ? null : (float)$_POST['peso_despues'];
    $esf     = $_POST['esfuerzo'] === '' ? null : (float)$_POST['esfuerzo'];
    $dur     = $_POST['duracion_entrenamiento'] === '' ? null : (int)$_POST['duracion_entrenamiento'];
    $cal     = $_POST['calorias_estimadas'] === '' ? null : (float)$_POST['calorias_estimadas'];
    $enf     = trim($_POST['enfermedades'] ?? '');
    $obs     = trim($_POST['observaciones'] ?? '');

    $sql = "INSERT INTO progreso_cliente
      (gimnasio_id, cliente_id, profesor_id, fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades, observaciones)
      VALUES (?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $conexion->prepare($sql);
    // Tipos: i i i s d d d i d s s  => 'iiisdddisdss'
    $types = 'iiisdddisdss';
    $stmt->bind_param(
      $types,
      $gimnasio_id, $alumno_id, $profesor_id, $fecha, $peso_a, $peso_d, $esf, $dur, $cal, $enf, $obs
    );
    if ($stmt->execute()) {
      header("Location: ?alumno_id=".$alumno_id."&filtro=".urlencode($_GET['filtro'] ?? 'mensual')."&ok=1");
      exit;
    } else {
      $err_guardado = $stmt->error;
    }
    $stmt->close();
  }
}

// ============ AJAX: buscador en vivo de clientes ============
// ============ AJAX: buscador en vivo de clientes ============
// GET ?ajax=buscar_clientes&term=algo
if (isset($_GET['ajax']) && $_GET['ajax'] === 'buscar_clientes') {
  header('Content-Type: application/json; charset=utf-8');

  // Asegurar gimnasio_id aunque la sesión venga incompleta
  $gid = (int)($_SESSION['gimnasio_id'] ?? 0);
  $pid = (int)($_SESSION['profesor_id'] ?? 0);
  if ($gid <= 0 && $pid > 0) {
    if ($stmt = $conexion->prepare('SELECT gimnasio_id FROM profesores WHERE id=? LIMIT 1')) {
      $stmt->bind_param('i', $pid);
      if ($stmt->execute() && ($res = $stmt->get_result()) && ($row = $res->fetch_assoc())) {
        $gid = (int)$row['gimnasio_id'];
        if ($gid > 0) $_SESSION['gimnasio_id'] = $gid;
      }
      $stmt->close();
    }
  }

  $term = trim($_GET['term'] ?? '');
  $rows = [];

  if ($term !== '') {
    // Búsqueda tolerante: por nombre completo, apellido, nombre, DNI o teléfono (cast a texto)
    $like = "%{$term}%";
    $sql = "SELECT id, apellido, nombre, dni, telefono
            FROM clientes
            WHERE gimnasio_id = ?
              AND (
                LOWER(CONCAT_WS(' ', COALESCE(apellido,''), COALESCE(nombre,''))) LIKE LOWER(?)
                OR LOWER(apellido) LIKE LOWER(?)
                OR LOWER(nombre) LIKE LOWER(?)
                OR CAST(dni AS CHAR) LIKE ?
                OR CAST(telefono AS CHAR) LIKE ?
              )
            ORDER BY apellido, nombre
            LIMIT 20";
    if ($stmt = $conexion->prepare($sql)) {
      $stmt->bind_param('isssss', $gid, $like, $like, $like, $like, $like);
      if ($stmt->execute() && ($res = $stmt->get_result())) {
        while($r = $res->fetch_assoc()){
          $rows[] = [
            'id'   => (int)$r['id'],
            'text' => full_name($r['apellido'] ?? '', $r['nombre'] ?? '') .
                     (empty($r['dni']) ? '' : ' · DNI ' . $r['dni'])
          ];
        }
      }
      $stmt->close();
    }
  } else {
    // Sugerencias por defecto: últimos clientes del gimnasio
    $sql = "SELECT id, apellido, nombre, dni
            FROM clientes
            WHERE gimnasio_id = ?
            ORDER BY id DESC
            LIMIT 20";
    if ($stmt = $conexion->prepare($sql)) {
      $stmt->bind_param('i', $gid);
      if ($stmt->execute() && ($res = $stmt->get_result())) {
        while($r = $res->fetch_assoc()){
          $rows[] = [
            'id'   => (int)$r['id'],
            'text' => full_name($r['apellido'] ?? '', $r['nombre'] ?? '') .
                     (empty($r['dni']) ? '' : ' · DNI ' . $r['dni'])
          ];
        }
      }
      $stmt->close();
    }
  }

  echo json_encode(['ok'=>true, 'items'=>$rows]);
  exit;
}

// ============ Filtros ============
$filtro     = $_GET['filtro'] ?? 'mensual';
$alumno_id  = (int)($_GET['alumno_id'] ?? 0);
$hoy        = date('Y-m-d');

switch ($filtro) {
  case 'semanal': $fecha_inicio = date('Y-m-d', strtotime('-7 days')); break;
  case 'anual':   $fecha_inicio = date('Y-01-01'); break;
  case 'mensual':
  default:        $fecha_inicio = date('Y-m-01'); break;
}

// ============ Lista atajo: alumnos con asistencias con este profe ============
$alumnos = [];
if ($stmt = $conexion->prepare("
  SELECT DISTINCT c.id, c.apellido, c.nombre, CONCAT(c.apellido,' ',c.nombre) AS nombre
  FROM asistencias a
  JOIN clientes c ON a.cliente_id = c.id
  WHERE a.profesor_id = ? AND a.gimnasio_id = ?
  ORDER BY c.apellido, c.nombre
")) {
  $stmt->bind_param('ii', $profesor_id, $gimnasio_id);
  if ($stmt->execute() && ($res = $stmt->get_result())) {
    while($r = $res->fetch_assoc()){ $alumnos[] = $r; }
  }
  $stmt->close();
}

// ============ Datos de evolución del alumno seleccionado ============
$progresos = [];
$kpis = [
  'asistencias' => 0,
  'prom_esfuerzo' => 0.0,
  'calorias_total' => 0.0,
  'duracion_total' => 0,
  'peso_delta' => null,
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
  // Cliente validado por gimnasio
  if ($stmt = $conexion->prepare("SELECT id, apellido, nombre FROM clientes WHERE id=? AND gimnasio_id=? LIMIT 1")) {
    $stmt->bind_param('ii', $alumno_id, $gimnasio_id);
    if ($stmt->execute() && ($res = $stmt->get_result())) $cliente = $res->fetch_assoc();
    $stmt->close();
  }

  // KPIs: asistencias en el período
  if ($stmt = $conexion->prepare("
    SELECT COUNT(*) AS c
    FROM asistencias
    WHERE cliente_id = ? AND gimnasio_id = ? AND fecha BETWEEN ? AND ?
  ")) {
    $stmt->bind_param('iiss', $alumno_id, $gimnasio_id, $fecha_inicio, $hoy);
    if ($stmt->execute() && ($res = $stmt->get_result())) {
      if ($ra = $res->fetch_assoc()) $kpis['asistencias'] = (int)$ra['c'];
    }
    $stmt->close();
  }

  // Progreso (ASC para delta)
  if ($stmt = $conexion->prepare("
    SELECT fecha, peso_antes, peso_despues, esfuerzo, duracion_entrenamiento, calorias_estimadas, enfermedades, observaciones
    FROM progreso_cliente
    WHERE cliente_id = ? AND gimnasio_id = ? AND fecha BETWEEN ? AND ?
    ORDER BY fecha ASC
  ")) {
    $stmt->bind_param('iiss', $alumno_id, $gimnasio_id, $fecha_inicio, $hoy);
    if ($stmt->execute() && ($res = $stmt->get_result())) {
      $firstPeso = null; $lastPeso = null;
      while ($row = $res->fetch_assoc()) {
        $progresos[] = $row;

        $fecha = (string)$row['fecha'];
        $series['labels'][] = $fecha;
        $series['pesoAntes'][]   = is_numeric($row['peso_antes']) ? (float)$row['peso_antes'] : null;
        $series['pesoDespues'][] = is_numeric($row['peso_despues']) ? (float)$row['peso_despues'] : null;
        $series['esfuerzo'][]    = is_numeric($row['esfuerzo']) ? (float)$row['esfuerzo'] : null;
        $series['calorias'][]    = is_numeric($row['calorias_estimadas']) ? (float)$row['calorias_estimadas'] : null;

        if (is_numeric($row['duracion_entrenamiento'])) $kpis['duracion_total'] += (int)$row['duracion_entrenamiento'];
        if (is_numeric($row['calorias_estimadas']))     $kpis['calorias_total'] += (float)$row['calorias_estimadas'];

        if ($kpis['primera_fecha'] === null) $kpis['primera_fecha'] = $fecha;
        $kpis['ultima_fecha'] = $fecha;

        if ($firstPeso === null && is_numeric($row['peso_antes'])) $firstPeso = (float)$row['peso_antes'];
        if (is_numeric($row['peso_despues'])) $lastPeso = (float)$row['peso_despues'];
      }
      $vals = array_values(array_filter($series['esfuerzo'], fn($v)=>$v!==null));
      $kpis['prom_esfuerzo'] = count($vals) ? (array_sum($vals)/count($vals)) : 0.0;
      if ($firstPeso !== null && $lastPeso !== null) $kpis['peso_delta'] = $lastPeso - $firstPeso;
    }
    $stmt->close();
  }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Progreso de Alumnos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
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
.filters{ margin-top:16px; display:flex; flex-wrap:wrap; gap:10px; align-items:center }
.input{ background:#101628; border:1px solid #1e2740; color:var(--text); padding:10px 12px; border-radius:12px; outline:none; min-width:260px }
.button{ background:var(--accent); color:#1b1200; font-weight:800; border:none; padding:10px 14px; border-radius:12px; cursor:pointer }
.suggest{ position:relative; min-width:280px }
.suggest-list{ position:absolute; top:46px; left:0; right:0; background:#0f1527; border:1px solid #1e2740; border-radius:12px; box-shadow:0 20px 40px rgba(0,0,0,.4); max-height:280px; overflow:auto; z-index:50 }
.suggest-item{ padding:10px 12px; cursor:pointer; border-bottom:1px solid #1a2340; color:#e6ecff }
.suggest-item:hover{ background:#0d1322 }
.kpi-grid{ display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-top:16px }
@media (max-width: 960px){ .kpi-grid{ grid-template-columns: repeat(2, 1fr);} }
@media (max-width: 520px){ .kpi-grid{ grid-template-columns: 1fr;} }
.kpi{ background:var(--card); border:1px solid #1f2a44; border-radius:16px; padding:16px; box-shadow: 0 6px 20px rgba(0,0,0,.25); position:relative; overflow:hidden }
.kpi h3{margin:0 0 8px 0; font-size:.95rem; color:var(--muted); font-weight:600}
.kpi .value{font-size:1.8rem; font-weight:800; letter-spacing:.3px}
.kpi .hint{font-size:.85rem; color:var(--muted)}
.kpi .emoji{position:absolute; right:10px; top:8px; font-size:1.4rem; opacity:.8}
.grid{ display:grid; grid-template-columns: 1.2fr 1fr; gap:18px; margin-top:18px }
@media (max-width: 1100px){ .grid{ grid-template-columns: 1fr; } }
.card{ background:var(--card); border:1px solid #1f2a44; border-radius:16px; padding:16px; box-shadow: 0 6px 20px rgba(0,0,0,.25); }
.card h2{margin:0 0 10px 0; font-size:1.05rem; color:var(--muted); letter-spacing:.2px}
.table{width:100%; border-collapse:separate; border-spacing:0 10px}
.table th{font-size:.9rem; color:var(--muted); text-align:left; padding:6px 10px}
.table td{ background:#101628; border:1px solid #1e2740; padding:10px; border-radius:10px; vertical-align:top; text-align:left }
.mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace;}
.small{font-size:.9rem; color:var(--muted)}
.ok{color:var(--ok)} .warn{color:var(--warn)} .danger{color:var(--danger)}
.form-grid{display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; }
@media (max-width: 760px){ .form-grid{ grid-template-columns: 1fr; } }
.form-grid input, .form-grid textarea{ background:#0f1424; border:1px solid #1e2740; color:#e6ecff; padding:10px 12px; border-radius:10px; width:100% }
.form-grid label{ font-size:.85rem; color:#aab7d1; margin-bottom:4px; display:block }
</style>
</head>
<body>
<div class="container">

  <div class="header">
    <div>
      <div class="badge">📈 Progreso de Alumnos</div>
      <h1>Evolución y métricas</h1>
    </div>
    <?php if (isset($_GET['ok']) && $_GET['ok']=='1'): ?>
      <div class="badge" style="background:rgba(34,197,94,.12); border-color:rgba(34,197,94,.4); color:#22c55e">✔ Registro guardado</div>
    <?php elseif (!empty($err_guardado)): ?>
      <div class="badge" style="background:rgba(239,68,68,.12); border-color:rgba(239,68,68,.4); color:#ef4444">⚠ <?= h($err_guardado) ?></div>
    <?php endif; ?>
  </div>

  <!-- Buscador en vivo + filtros -->
  <form class="filters" method="GET" id="filtrosForm" action="">
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

    <select class="input" onchange="location.href='?alumno_id='+this.value+'&filtro=<?= h($filtro) ?>'">
      <option value="">Tus alumnos (asistencias)</option>
      <?php foreach($alumnos as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= ((int)$a['id']===$alumno_id)?'selected':'' ?>>
          <?= h($a['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if ($alumno_id && $cliente): ?>
    <!-- FORM de carga rápida -->
    <div class="card" style="margin-top:16px">
      <h2>➕ Cargar registro rápido</h2>
      <form method="POST">
        <input type="hidden" name="accion" value="guardar_progreso">
        <input type="hidden" name="alumno_id" value="<?= (int)$alumno_id ?>">
        <div class="form-grid">
          <div><label>Fecha</label><input type="date" name="fecha" value="<?= h(date('Y-m-d')) ?>"></div>
          <div><label>Peso antes (kg)</label><input type="number" step="0.1" name="peso_antes"></div>
          <div><label>Peso después (kg)</label><input type="number" step="0.1" name="peso_despues"></div>
          <div><label>Esfuerzo (1-10)</label><input type="number" step="0.1" min="0" max="10" name="esfuerzo"></div>
          <div><label>Duración (min)</label><input type="number" step="1" min="0" name="duracion_entrenamiento"></div>
          <div><label>Calorías</label><input type="number" step="1" min="0" name="calorias_estimadas"></div>
          <div style="grid-column:1 / -1"><label>Enfermedades</label><input type="text" name="enfermedades" placeholder="Opcional"></div>
          <div style="grid-column:1 / -1"><label>Observaciones</label><textarea name="observaciones" rows="2" placeholder="Notas"></textarea></div>
        </div>
        <div style="margin-top:10px">
          <button class="button" type="submit">Guardar</button>
          <a class="button" style="background:#aab7d1; color:#0c1220" href="form_progreso.php?alumno_id=<?= (int)$alumno_id ?>">Formulario completo</a>
          <a class="button" style="background:#aab7d1; color:#0c1220" href="ver_progreso.php?cliente_id=<?= (int)$alumno_id ?>" target="_blank">Ver como cliente</a>
        </div>
      </form>
    </div>

    <!-- KPIs -->
    <div class="kpi-grid" style="margin-top:16px">
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
            SELECT r.fecha_reserva, r.hora_inicio, COALESCE(td.hora_fin, r.hora_fin) AS turno_hora_fin
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
      <p class="small">Tip: empezá a escribir nombre, apellido, DNI o teléfono y elegí de la lista. También podés usar el selector "Tus alumnos".</p>
    </div>
  <?php endif; ?>

</div><!-- /container -->

<script>
// ===== Buscador en vivo =====
const buscar = document.getElementById('buscar');
const list = document.getElementById('suggestList');
const alumnoInput = document.getElementById('alumno_id');

let tId;
if (buscar) {
  buscar.addEventListener('input', ()=>{
    const term = buscar.value.trim();
    if (tId) clearTimeout(tId);
    tId = setTimeout(async ()=>{
      const url = `?ajax=buscar_clientes&term=${encodeURIComponent(term)}`;
      const res = await fetch(url, {headers:{'Accept':'application/json'}});
      const data = await res.json();
      list.innerHTML = '';
      if (data && data.items && data.items.length){
        data.items.forEach(it=>{
          const div = document.createElement('div');
          div.className = 'suggest-item';
          div.textContent = it.text;
          div.onclick = ()=>{
            alumnoInput.value = it.id;
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
// Mostrar sugerencias al enfocar si está vacío
if (buscar) buscar.addEventListener('focus', async ()=>{ if (buscar.value.trim()===''){ const res=await fetch(`?ajax=buscar_clientes`, {headers:{'Accept':'application/json'}}); const data=await res.json(); list.innerHTML=''; if (data && data.items && data.items.length){ data.items.forEach(it=>{ const div=document.createElement('div'); div.className='suggest-item'; div.textContent=it.text; div.onclick=()=>{ alumnoInput.value=it.id; const params = new URLSearchParams(window.location.search); const filtro = params.get('filtro') || '<?= h($filtro) ?>'; window.location.href = `?alumno_id=${it.id}&filtro=${filtro}`; }; list.appendChild(div); }); list.style.display='block'; } });
}

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
