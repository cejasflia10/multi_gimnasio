<?php
// panel_profesor.php — versión elegante/pro
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['profesor_id']) || empty($_SESSION['gimnasio_id'])) {
    echo "Acceso denegado. Por favor inicie sesión.";
    exit;
}

require __DIR__ . '/conexion.php';
require __DIR__ . '/menu_profesor.php';

$profesor_id = (int)($_SESSION['profesor_id']);
$gimnasio_id = (int)($_SESSION['gimnasio_id']);

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function full_name($apellido, $nombre){
    $t = trim((string)$apellido.' '.(string)$nombre);
    return $t !== '' ? $t : '—';
}

// ===== Datos del profesor =====
$prof = ['apellido'=>'', 'nombre'=>''];
if ($q = $conexion->query("SELECT apellido, nombre FROM profesores WHERE id = {$profesor_id} LIMIT 1")) {
    $prof = $q->fetch_assoc() ?: $prof;
}

// ===== Asistencia del día (profesor) =====
$asistencias_hoy = $conexion->query("
    SELECT hora_ingreso, hora_salida
    FROM asistencias_profesores
    WHERE profesor_id = {$profesor_id}
      AND fecha = CURDATE()
    ORDER BY hora_ingreso
");
$asist_hoy_count = $asistencias_hoy ? $asistencias_hoy->num_rows : 0;

// ===== Alumnos que ingresaron hoy (todo el gimnasio) =====
$alumnos_hoy = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = CURDATE()
      AND c.gimnasio_id = {$gimnasio_id}
    ORDER BY a.hora
");
$alumnos_hoy_count = $alumnos_hoy ? $alumnos_hoy->num_rows : 0;

// ===== Reservas del día (todo el gimnasio) =====
$reservas_q = $conexion->query("
    SELECT r.id, r.hora_inicio,
           td.hora_fin AS turno_hora_fin,
           r.turno_id,
           c.apellido AS cliente_apellido, c.nombre AS cliente_nombre,
           p.apellido AS prof_apellido, p.nombre AS prof_nombre
    FROM reservas_clientes r
    LEFT JOIN clientes c   ON r.cliente_id  = c.id
    LEFT JOIN profesores p ON r.profesor_id = p.id
    LEFT JOIN turnos_disponibles td ON r.turno_id = td.id
    WHERE r.fecha_reserva = CURDATE()
      AND r.gimnasio_id   = {$gimnasio_id}
    ORDER BY r.hora_inicio, cliente_apellido
");
$reservas_count = $reservas_q ? $reservas_q->num_rows : 0;

// ===== Mis clases de hoy (reservas asignadas a este profesor) =====
$mis_turnos_hoy = $conexion->query("
    SELECT r.hora_inicio, td.hora_fin AS turno_hora_fin,
           c.apellido, c.nombre
    FROM reservas_clientes r
    LEFT JOIN turnos_disponibles td ON r.turno_id = td.id
    LEFT JOIN clientes c            ON r.cliente_id = c.id
    WHERE r.fecha_reserva = CURDATE()
      AND r.gimnasio_id   = {$gimnasio_id}
      AND r.profesor_id   = {$profesor_id}
    ORDER BY r.hora_inicio
");
$mis_turnos_hoy_count = $mis_turnos_hoy ? $mis_turnos_hoy->num_rows : 0;

// ===== Próximas 10 clases (desde hoy) del profesor =====
$proximas = $conexion->query("
    SELECT r.fecha_reserva, r.hora_inicio, td.hora_fin AS turno_hora_fin,
           c.apellido, c.nombre
    FROM reservas_clientes r
    LEFT JOIN turnos_disponibles td ON r.turno_id = td.id
    LEFT JOIN clientes c            ON r.cliente_id = c.id
    WHERE r.gimnasio_id = {$gimnasio_id}
      AND r.profesor_id = {$profesor_id}
      AND r.fecha_reserva >= CURDATE()
    ORDER BY r.fecha_reserva, r.hora_inicio
    LIMIT 10
");

// ===== Total horas trabajadas del mes =====
$turnos_mes = $conexion->query("
    SELECT hora_ingreso, hora_salida
    FROM asistencias_profesores
    WHERE profesor_id = {$profesor_id}
      AND MONTH(fecha) = MONTH(CURDATE())
      AND YEAR(fecha)  = YEAR(CURDATE())
");
$total_horas = 0;
if ($turnos_mes) {
    while ($fila = $turnos_mes->fetch_assoc()) {
        $hi = $fila['hora_ingreso'] ?? '';
        $hs = $fila['hora_salida']  ?? '';
        if ($hi && $hs) {
            $ini = strtotime($hi);
            $fin = strtotime($hs);
            if ($fin > $ini) $total_horas += ($fin - $ini) / 3600;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Panel del Profesor</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="estilo_unificado.css">
<style>
:root{
  --bg:#0b0d12; --surface:#11141b; --card:#151926; --muted:#95a3b8;
  --text:#e8eef6; --accent:#f5b301; --ring:#26324a; --ok:#22c55e; --warn:#f59e0b;
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
.header .meta{opacity:.9; font-size:.95rem}
.badge{
  display:inline-flex; align-items:center; gap:8px; padding:8px 12px; border-radius:999px;
  background:rgba(245,179,1,.1); border:1px solid rgba(245,179,1,.25); color:var(--accent);
  font-weight:600; letter-spacing:.2px
}

/* KPIs */
.kpi-grid{
  display:grid; grid-template-columns: repeat(4, minmax(180px, 1fr)); gap:16px; margin-top:18px
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

/* Layout principal */
.grid{
  display:grid; grid-template-columns: 1.2fr 1fr; gap:18px; margin-top:18px
}
@media (max-width: 1100px){ .grid{ grid-template-columns: 1fr; } }

.card{
  background:var(--card); border:1px solid #1f2a44; border-radius:16px; padding:16px;
  box-shadow: 0 6px 20px rgba(0,0,0,.25);
}
.card h2{margin:0 0 10px 0; font-size:1.05rem; color:var(--muted); letter-spacing:.2px}

/* Listas */
.list{display:flex; flex-direction:column; gap:10px; margin:0; padding:0; list-style:none}
.item{
  padding:10px 12px; border:1px solid #1e2740; border-radius:12px; background:#101628;
  display:flex; align-items:center; justify-content:space-between; gap:10px;
}
.item .left{display:flex; flex-direction:column; gap:2px}
.item .title{font-weight:700; color:#f0f4ff}
.item .sub{font-size:.9rem; color:var(--muted)}
.empty{color:var(--muted); padding:6px 2px}

/* Tabla simple */
.table{width:100%; border-collapse:separate; border-spacing:0 10px}
.table th{font-size:.9rem; color:var(--muted); text-align:left; padding:6px 10px}
.table td{
  background:#101628; border:1px solid #1e2740; padding:10px; border-radius:10px;
  vertical-align:top
}

/* Utilidades */
.row{display:flex; gap:10px; align-items:center}
.mono{font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;}
.right{margin-left:auto}
.small{font-size:.9rem; color:var(--muted)}
.accent{color:var(--accent)}
.ok{color:var(--ok)}
</style>
</head>
<body>
<div class="container">

  <!-- Encabezado -->
  <div class="header">
    <div>
      <div class="badge">👨‍🏫 Profesor</div>
      <h1>Bienvenido, <?= h(full_name($prof['apellido'] ?? '', $prof['nombre'] ?? '')) ?></h1>
      <div class="meta small">Gimnasio ID: <span class="mono"><?= $gimnasio_id ?></span> · Profesor ID: <span class="mono"><?= $profesor_id ?></span></div>
    </div>
    <div class="right small">
      <div id="clock" class="mono"></div>
      <script>
        const el = document.getElementById('clock');
        const fmt = (n)=> String(n).padStart(2,'0');
        function tick(){
          const d = new Date();
          const y=d.getFullYear(), m=fmt(d.getMonth()+1), dd=fmt(d.getDate()),
                hh=fmt(d.getHours()), mm=fmt(d.getMinutes()), ss=fmt(d.getSeconds());
          el.textContent = `Hoy: ${y}-${m}-${dd} ${hh}:${mm}:${ss}`;
        }
        tick(); setInterval(tick, 1000);
      </script>
    </div>
  </div>

  <!-- KPIs -->
  <div class="kpi-grid">
    <div class="kpi">
      <div class="emoji">🕘</div>
      <h3>Asistencias registradas (hoy)</h3>
      <div class="value"><?= (int)$asist_hoy_count ?></div>
      <div class="hint">Tus ingresos/salidas del día</div>
    </div>
    <div class="kpi">
      <div class="emoji">🧍</div>
      <h3>Alumnos ingresados (hoy · gimnasio)</h3>
      <div class="value"><?= (int)$alumnos_hoy_count ?></div>
      <div class="hint">Control de acceso general</div>
    </div>
    <div class="kpi">
      <div class="emoji">📋</div>
      <h3>Reservas del día (gimnasio)</h3>
      <div class="value"><?= (int)$reservas_count ?></div>
      <div class="hint">Todas las reservas de hoy</div>
    </div>
    <div class="kpi">
      <div class="emoji">⏱️</div>
      <h3>Horas trabajadas (mes)</h3>
      <div class="value"><?= number_format($total_horas, 2, ',', '.') ?> <span class="small">hs</span></div>
      <div class="hint">Según asistencias registradas</div>
    </div>
  </div>

  <!-- Layout principal -->
  <div class="grid">

    <!-- Columna izquierda -->
    <div class="col">
      <div class="card">
        <h2>📆 Tu asistencia (hoy)</h2>
        <?php if ($asistencias_hoy && $asistencias_hoy->num_rows > 0): ?>
          <ul class="list">
            <?php while($a = $asistencias_hoy->fetch_assoc()): ?>
              <li class="item">
                <div class="left">
                  <div class="title">Ingreso: <span class="mono ok"><?= h($a['hora_ingreso']) ?></span></div>
                  <div class="sub">Salida: <span class="mono"><?= h($a['hora_salida'] ?: '—') ?></span></div>
                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <div class="empty">No registraste asistencia hoy.</div>
        <?php endif; ?>
      </div>

      <div class="card" style="margin-top:18px">
        <h2>📚 Mis clases de hoy</h2>
        <?php if ($mis_turnos_hoy && $mis_turnos_hoy->num_rows > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Cliente</th>
                <th>Fin</th>
              </tr>
            </thead>
            <tbody>
              <?php while($t = $mis_turnos_hoy->fetch_assoc()): ?>
                <tr>
                  <td><span class="mono"><?= h($t['hora_inicio'] ?? '') ?></span></td>
                  <td><?= h(full_name($t['apellido'] ?? '', $t['nombre'] ?? '')) ?></td>
                  <td><span class="mono"><?= h($t['turno_hora_fin'] ?? '—') ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No tenés clases asignadas para hoy.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Columna derecha -->
    <div class="col">
      <div class="card">
        <h2>🧍 Alumnos que ingresaron hoy (gimnasio)</h2>
        <?php if ($alumnos_hoy && $alumnos_hoy->num_rows > 0): ?>
          <ul class="list">
            <?php while($al = $alumnos_hoy->fetch_assoc()): ?>
              <li class="item">
                <div class="left">
                  <div class="title"><?= h(full_name($al['apellido'] ?? '', $al['nombre'] ?? '')) ?></div>
                  <div class="sub">⏰ <span class="mono"><?= h($al['hora'] ?? '') ?></span></div>
                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <div class="empty">No se registraron ingresos de alumnos hoy.</div>
        <?php endif; ?>
      </div>

      <div class="card" style="margin-top:18px">
        <h2>📋 Reservas del día (gimnasio)</h2>
        <?php if ($reservas_q === false): ?>
          <div class="empty" style="color:#fca5a5">Error al consultar reservas: <?= h($conexion->error) ?></div>
        <?php elseif ($reservas_q->num_rows > 0): ?>
          <ul class="list">
            <?php while($r = $reservas_q->fetch_assoc()):
              $hora_i  = h($r['hora_inicio'] ?? '');
              $hora_f  = h($r['turno_hora_fin'] ?? '');
              $cliente = h(full_name($r['cliente_apellido'] ?? '', $r['cliente_nombre'] ?? ''));
              $profe   = h(full_name($r['prof_apellido'] ?? '', $r['prof_nombre'] ?? ''));
            ?>
              <li class="item">
                <div class="left">
                  <div class="title">🕒 <?= $hora_i ?> <?= $hora_f ? ' - '.$hora_f : '' ?></div>
                  <div class="sub">👤 <?= $cliente ?> · 👨‍🏫 <?= $profe ?></div>
                </div>
              </li>
            <?php endwhile; ?>
          </ul>
        <?php else: ?>
          <div class="empty">No hay reservas registradas para hoy.</div>
        <?php endif; ?>
      </div>

      <div class="card" style="margin-top:18px">
        <h2>📅 Próximas clases (tus próximas 10)</h2>
        <?php if ($proximas && $proximas->num_rows > 0): ?>
          <table class="table">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Inicio</th>
                <th>Fin</th>
                <th>Cliente</th>
              </tr>
            </thead>
            <tbody>
              <?php while($px = $proximas->fetch_assoc()): ?>
                <tr>
                  <td><span class="mono"><?= h($px['fecha_reserva'] ?? '') ?></span></td>
                  <td><span class="mono"><?= h($px['hora_inicio'] ?? '') ?></span></td>
                  <td><span class="mono"><?= h($px['turno_hora_fin'] ?? '—') ?></span></td>
                  <td><?= h(full_name($px['apellido'] ?? '', $px['nombre'] ?? '')) ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div class="empty">No hay próximas clases programadas.</div>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /grid -->

</div><!-- /container -->
</body>
</html>
