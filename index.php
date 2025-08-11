<?php
// --- INICIO: validación de sesión e inactividad ---
if (session_status() === PHP_SESSION_NONE) session_start();

$timeout_minutos = 30;
$timeout_seg = $timeout_minutos * 60;

if (!isset($_SESSION['gimnasio_id'])) {
    if (session_status() !== PHP_SESSION_NONE) {
        session_unset();
        session_destroy();
    }
    header('Location: login.php');
    exit;
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_seg) {
    session_unset();
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}

$_SESSION['last_activity'] = time();

if (!isset($_SESSION['session_regenerated_time'])) {
    session_regenerate_id(true);
    $_SESSION['session_regenerated_time'] = time();
} else {
    if (time() - $_SESSION['session_regenerated_time'] > 15 * 60) {
        session_regenerate_id(true);
        $_SESSION['session_regenerated_time'] = time();
    }
}
// --- FIN: validación de sesión e inactividad ---

include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = isset($_SESSION['gimnasio_id']) ? (int)$_SESSION['gimnasio_id'] : 0;
$rol = $_SESSION['rol'] ?? '';

$gimnasio = $conexion->query("SELECT nombre, logo, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gym = $gimnasio['nombre'] ?? 'Gimnasio';
$logo = $gimnasio['logo'] ?? '';
$fecha_venc = $gimnasio['fecha_vencimiento'] ?? '---';

// ===== KPIs Activos vs Inactivos (última membresía por cliente) =====
$estado = $conexion->query("
  SELECT
    SUM(CASE WHEN u.fv IS NOT NULL AND u.fv >= CURDATE() THEN 1 ELSE 0 END) AS activos,
    SUM(CASE WHEN u.fv IS NULL OR u.fv < CURDATE() THEN 1 ELSE 0 END) AS inactivos
  FROM clientes c
  LEFT JOIN (
    SELECT cliente_id, MAX(fecha_vencimiento) AS fv
    FROM membresias
    WHERE gimnasio_id = $gimnasio_id
      AND fecha_vencimiento IS NOT NULL
      AND fecha_vencimiento >= '1000-01-01'
    GROUP BY cliente_id
  ) u ON u.cliente_id = c.id
  WHERE c.gimnasio_id = $gimnasio_id
")->fetch_assoc();

$activos   = (int)($estado['activos'] ?? 0);
$inactivos = (int)($estado['inactivos'] ?? 0);

// ===== Cumpleaños (filtro seguro) =====
$cumples = $conexion->query("
  SELECT nombre, apellido, fecha_nacimiento
  FROM clientes
  WHERE gimnasio_id = $gimnasio_id
    AND fecha_nacimiento IS NOT NULL
    AND fecha_nacimiento >= '1000-01-01'
    AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d')
  ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d')
  LIMIT 5
");

// ===== Vencimientos (filtro seguro) =====
$vencimientos = $conexion->query("
    SELECT c.nombre, c.apellido, m.fecha_vencimiento
    FROM membresias m
    JOIN clientes c ON m.cliente_id = c.id
    WHERE m.gimnasio_id = $gimnasio_id
      AND m.fecha_vencimiento IS NOT NULL
      AND m.fecha_vencimiento >= '1000-01-01'
      AND m.fecha_vencimiento >= CURDATE()
    ORDER BY m.fecha_vencimiento ASC LIMIT 5
");

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_filtro)) {
    $fecha_filtro = date('Y-m-d');
}

// ===== PAGOS PENDIENTES =====
$pagos_pendientes = 0;
$consulta = $conexion->query("
    SELECT COUNT(*) AS total 
    FROM pagos_pendientes 
    JOIN clientes ON pagos_pendientes.cliente_id = clientes.id 
    WHERE pagos_pendientes.estado = 'pendiente' 
      AND clientes.gimnasio_id = $gimnasio_id
");
if ($consulta && $r = $consulta->fetch_assoc()) {
    $pagos_pendientes = (int)$r['total'];
}

// ===== CUENTAS CORRIENTES =====
$cuentas_corrientes = 0;
$consulta_cc = $conexion->query("
    SELECT COUNT(*) AS total FROM (
        SELECT cliente_id
        FROM cuentas_corrientes
        WHERE gimnasio_id = $gimnasio_id
        GROUP BY cliente_id
        HAVING SUM(monto) < 0
    ) AS sub
");
if ($consulta_cc && $r = $consulta_cc->fetch_assoc()) {
    $cuentas_corrientes = (int)$r['total'];
}

// ===== Avisos de nuevos online =====
$nuevos = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = $gimnasio_id AND nuevo_online = 1");
$avisos_html = '';
if ($nuevos && $nuevos->num_rows > 0) {
    ob_start();
    echo "<div style='background:#fff3cd;border:1px solid #ffeeba;padding:12px;border-radius:10px;color:#856404;margin-top:10px;'>";
    echo "<strong>📢 Nuevos registros online:</strong><br>";
    while ($n = $nuevos->fetch_assoc()) {
        echo htmlspecialchars($n['nombre'].' '.$n['apellido']) . " — <a href='marcar_visto.php?id={$n['id']}'>Marcar como visto</a><br>";
    }
    echo "</div>";
    $avisos_html = ob_get_clean();
}

// ===== Disciplinas TOP para gráfico =====
$disciplinas_top_q = $conexion->query("
    SELECT
      UPPER(TRIM(COALESCE(d.nombre, c.disciplina))) AS nombre_norm,
      COUNT(*) AS total
    FROM clientes c
    LEFT JOIN disciplinas d ON d.id = c.disciplina_id
    WHERE c.gimnasio_id = $gimnasio_id
      AND COALESCE(d.nombre, c.disciplina) IS NOT NULL
      AND TRIM(COALESCE(d.nombre, c.disciplina)) <> ''
    GROUP BY UPPER(TRIM(COALESCE(d.nombre, c.disciplina)))
    ORDER BY total DESC
    LIMIT 6
");
$disciplinas_rows = [];
if ($disciplinas_top_q) {
    while ($row = $disciplinas_top_q->fetch_assoc()) {
        $disciplinas_rows[] = [
          'nombre' => ucwords(strtolower($row['nombre_norm'])),
          'total'  => (int)$row['total']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel General - <?= htmlspecialchars($nombre_gym) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <style>
    body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
    .grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .box, .cuadro { background: #111; padding: 15px; border-radius: 10px; flex: 1 1 300px; }
    h1, h2, h3 { color: gold; margin-top: 0; }
    ul { padding-left: 20px; }
    .monto { font-size: 24px; color: lime; }
    .encabezado { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
    .logo-gym { max-height: 60px; max-width: 180px; border-radius: 8px; background: white; padding: 5px; object-fit: contain; }
    .btn-logo-mini { margin-top: 8px; padding: 5px 10px; background: gold; color: black; font-weight: bold; border: none; border-radius: 5px; cursor: pointer; }
    .alerta-pagos { box-shadow: none; border: 1px solid rgba(255,255,255,.15); padding:10px; border-radius:10px; }
    .alerta-pagos a { color: yellow; text-decoration: underline; margin-left: 10px; }
    .toggle-icon { cursor: pointer; font-size: 22px; }

    /* KPIs Activos/Inactivos */
    .kpis { display:flex; gap:12px; flex-wrap:wrap; margin:10px 0 16px; }
    .kpi { background:#111; border:1px solid rgba(255,215,0,.15); border-radius:12px; padding:10px 14px; min-width:140px; }
    .kpi-label { color:#ffdf6b; font-size:12px; opacity:.85; letter-spacing:.3px; }
    .kpi-value { color:#fff; font-weight:800; font-size:26px; line-height:1.1; }

    /* Tamaño y sombra del gráfico de disciplinas */
    .chart-wrap { display:flex; justify-content:center; }
    #disciplinasChart { width:100%; max-width:560px; height:300px; filter: drop-shadow(0 8px 18px rgba(0,0,0,.45)); }
  </style>

  <!-- Solo cargamos Chart.js para el gráfico de disciplinas -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <script>
    function toggleMontos() {
      const montos = document.querySelectorAll('.bloque-monto');
      const icono = document.getElementById('icono-ojo');
      let visible = montos.length && montos[0].style.display !== 'none';
      montos.forEach(div => div.style.display = visible ? 'none' : 'block');
      if (icono) icono.textContent = visible ? '👁️' : '👁️‍🗨️';
    }
    function cargarDatos() {
      fetch('ajax_ingresos.php').then(r => r.text()).then(html => document.getElementById('contenedor-ingresos').innerHTML = html).catch(()=>{});
      const fecha = document.getElementById('fecha')?.value;
      if (fecha) fetch('ajax_reservas.php?fecha=' + fecha).then(r => r.text()).then(html => document.getElementById('contenedor-reservas').innerHTML = html).catch(()=>{});
      fetch('ajax_alumnos_hoy.php').then(r => r.text()).then(html => document.getElementById('contenedor-alumnos').innerHTML = html).catch(()=>{});
    }
    setInterval(cargarDatos, 10000);
    window.onload = cargarDatos;
  </script>
</head>
<body>

<div class="encabezado">
  <div style="display:flex; align-items:center; gap:15px;">
    <?php if ($logo): ?>
      <div style="position:relative; display:flex; flex-direction:column; align-items:flex-start;">
        <img src="<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo" class="logo-gym" id="logoGym">
        <?php if ($gimnasio_id > 0): ?>
          <button onclick="document.getElementById('formLogo').style.display='block'" class="btn-logo-mini">🖋</button>
          <form method="POST" action="subir_logo.php" enctype="multipart/form-data" id="formLogo" style="display:none; margin-top:3px;">
            <input type="file" name="logo" accept="image/*" required onchange="this.form.submit()">
          </form>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div>
      <h1>🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
      <p>🗓 Vencimiento del sistema: <strong style="color:orange;">
        <?= (is_string($fecha_venc) && $fecha_venc !== '0000-00-00' && strtotime($fecha_venc)) ? date('d/m/Y', strtotime($fecha_venc)) : '---' ?>
      </strong></p>
    </div>
  </div>
</div>

<!-- KPIs: Activos / Inactivos (sin gráfico) -->
<div class="kpis">
  <div class="kpi">
    <div class="kpi-label">Activos</div>
    <div class="kpi-value"><?= (int)$activos ?></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Inactivos</div>
    <div class="kpi-value"><?= (int)$inactivos ?></div>
  </div>
</div>

<?= $avisos_html ?>

<?php if ($cuentas_corrientes > 0): ?>
  <div class="alerta-pagos" style="margin:10px 0;">
    ⚠️ Hay <strong><?= $cuentas_corrientes ?></strong> cliente(s) con saldo negativo.
    <a href="ver_cuentas_corrientes.php">Ver cuentas corrientes</a>
  </div>
<?php endif; ?>

<?php if ($pagos_pendientes > 0): ?>
  <div class="alerta-pagos" style="margin:10px 0;">
    💸 Hay <strong><?= $pagos_pendientes ?></strong> pago(s) pendiente(s) de clientes.
    <a href="ver_pagos_pendientes.php">Ver pagos</a>
  </div>
<?php endif; ?>

<div style="text-align:right; margin-bottom:10px;">
  <span id="icono-ojo" class="toggle-icon" onclick="toggleMontos()">👁️‍🗨️</span>
</div>

<div class="grid">
  <div id="contenedor-ingresos" class="box bloque-monto">Cargando ingresos...</div>

  <div class="box">
    <h2>🎂 Próximos Cumpleaños</h2>
    <ul>
      <?php while($c = $cumples->fetch_assoc()): ?>
        <li>
          <?= htmlspecialchars($c['apellido'] . ', ' . $c['nombre']) ?>
          (
            <?= ($c['fecha_nacimiento'] && strtotime($c['fecha_nacimiento']))
                  ? date('d/m', strtotime($c['fecha_nacimiento']))
                  : '--' ?>
          )
        </li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div class="box">
    <h2>🗓 Vencimientos</h2>
    <ul>
      <?php while($v = $vencimientos->fetch_assoc()): ?>
        <li>
          <?= htmlspecialchars($v['apellido'] . ', ' . $v['nombre']) ?>
          (
            <?= ($v['fecha_vencimiento'] && strtotime($v['fecha_vencimiento']))
                  ? date('d/m', strtotime($v['fecha_vencimiento']))
                  : '--' ?>
          )
        </li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div class="box">
    <form method="GET" style="margin-bottom: 10px;">
      <label for="fecha" style="color: gold;">🗓 Ver reservas del día: </label>
      <input type="date" id="fecha" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
    </form>
    <h2>📋 Reservas del día</h2>
    <ul id="contenedor-reservas">Cargando reservas...</ul>
  </div>

  <!-- ÚNICO GRÁFICO: Disciplinas más registradas -->
  <div class="box">
    <h2>📊 Disciplinas más registradas</h2>
    <div class="chart-wrap">
      <canvas id="disciplinasChart"></canvas>
    </div>
    <?php if (count($disciplinas_rows) === 0): ?>
      <small>No hay datos para mostrar.</small>
    <?php endif; ?>
  </div>
</div>

<script>
  // Render del único gráfico (disciplinas)
  const disciplinas = <?= json_encode($disciplinas_rows, JSON_UNESCAPED_UNICODE) ?>;
  if (Array.isArray(disciplinas) && disciplinas.length) {
    const canvas = document.getElementById('disciplinasChart');
    if (canvas) {
      const ctxD = canvas.getContext('2d');
      const grad = ctxD.createLinearGradient(0, 0, 0, canvas.height);
      grad.addColorStop(0, 'rgba(255, 193, 7, 0.9)');
      grad.addColorStop(1, 'rgba(230, 81, 0, 0.6)');

      new Chart(ctxD, {
        type: 'bar',
        data: {
          labels: disciplinas.map(d => d.nombre),
          datasets: [{
            label: 'Registros de disciplinas',
            data: disciplinas.map(d => Number(d.total)),
            backgroundColor: grad,
            borderColor: 'rgba(255,160,0,1)',
            borderWidth: 2,
            borderRadius: 10
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { labels: { color: '#FFD54F' } },
            tooltip: {
              backgroundColor: 'rgba(20,20,20,.95)',
              titleColor: '#FFD54F',
              bodyColor: '#EEE',
              borderWidth: 1,
              borderColor: 'rgba(255,215,0,.35)'
            }
          },
          scales: {
            x: { ticks: { color: '#FFD54F' }, grid: { color: 'rgba(255,215,0,.10)' } },
            y: { beginAtZero: true, ticks: { color: '#FFD54F', precision: 0 }, grid: { color: 'rgba(255,215,0,.08)' } }
          }
        }
      });
    }
  }
</script>

</body>
</html>
