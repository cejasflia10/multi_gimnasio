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

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

$gimnasio = $conexion->query("SELECT nombre, logo, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gym = $gimnasio['nombre'] ?? 'Gimnasio';
$logo = $gimnasio['logo'] ?? '';
$fecha_venc = $gimnasio['fecha_vencimiento'] ?? '---';

$cumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = $gimnasio_id AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d') ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d') LIMIT 5");

$vencimientos = $conexion->query("
    SELECT c.nombre, c.apellido, m.fecha_vencimiento 
    FROM membresias m 
    JOIN clientes c ON m.cliente_id = c.id 
    WHERE m.gimnasio_id = $gimnasio_id AND m.fecha_vencimiento >= CURDATE() 
    ORDER BY m.fecha_vencimiento ASC LIMIT 5
");

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');


// PAGOS PENDIENTES
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

// CUENTAS CORRIENTES
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
$nuevos = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE gimnasio_id = $gimnasio_id AND nuevo_online = 1");
if ($nuevos && $nuevos->num_rows > 0) {
    echo "<div style='background:#fff3cd;border:1px solid #ffeeba;padding:12px;border-radius:8px;color:#856404;'>";
    echo "<strong>📢 Nuevos registros online:</strong><br>";
    while ($n = $nuevos->fetch_assoc()) {
        echo htmlspecialchars($n['nombre'].' '.$n['apellido']) . " — <a href='marcar_visto.php?id={$n['id']}'>Marcar como visto</a><br>";
    }
    echo "</div>";
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
    .encabezado { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .logo-gym {
        max-height: 60px;
        max-width: 180px;
        border-radius: 8px;
        background: white;
        padding: 5px;
        object-fit: contain;
    }
    .btn-logo-mini {
        margin-top: 8px;
        padding: 5px 10px;
        background: gold;
        color: black;
        font-weight: bold;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .alerta-pagos {
        background-color: darkred;
        color: white;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 10px;
        font-size: 18px;
    }
    .alerta-pagos a {
        color: yellow;
        text-decoration: underline;
        margin-left: 10px;
    }
    .alerta-ok {
        background-color: green;
        color: white;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 10px;
        font-size: 18px;
    }
  </style>
  <script>
    function toggleMontos() {
      const montos = document.querySelectorAll('.bloque-monto');
      const icono = document.getElementById('icono-ojo');
      let visible = montos[0].style.display !== 'none';

      montos.forEach(div => {
        div.style.display = visible ? 'none' : 'block';
      });

      icono.textContent = visible ? '👁️' : '👁️‍🗨️';
    }

    function cargarDatos() {
      fetch('ajax_ingresos.php')
        .then(r => r.text())
        .then(html => document.getElementById('contenedor-ingresos').innerHTML = html);

      const fecha = document.getElementById('fecha').value;
      fetch('ajax_reservas.php?fecha=' + fecha)
        .then(r => r.text())
        .then(html => document.getElementById('contenedor-reservas').innerHTML = html);

      fetch('ajax_alumnos_hoy.php')
        .then(r => r.text())
        .then(html => document.getElementById('contenedor-alumnos').innerHTML = html);
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
      <p>🗓 Vencimiento del sistema: <strong style="color:orange;"><?= date('d/m/Y', strtotime($fecha_venc)) ?></strong></p>
    </div>
  </div>
</div>

<?php if ($cuentas_corrientes > 0): ?>
  <div class="alerta-pagos">
    ⚠️ Hay <strong><?= $cuentas_corrientes ?></strong> cliente(s) con saldo negativo.
    <a href="ver_cuentas_corrientes.php">Ver cuentas corrientes</a>
  </div>
<?php endif; ?>

<?php if ($pagos_pendientes > 0): ?>
  <div class="alerta-pagos">
    💸 Hay <strong><?= $pagos_pendientes ?></strong> pago(s) pendiente(s) de clientes.
    <a href="ver_pagos_pendientes.php">Ver pagos</a>
  </div>
<?php endif; ?>

<div style="text-align:right; margin-bottom:10px;">
  <span id="icono-ojo" class="toggle-icon" onclick="toggleMontos()" style="cursor:pointer; font-size:22px;">👁️‍🔮</span>
</div>

<div class="grid">
  <div id="contenedor-ingresos" class="box bloque-monto">Cargando ingresos...</div>

  <div class="box">
    <h2>🎂 Próximos Cumpleaños</h2>
    <ul>
      <?php while($c = $cumples->fetch_assoc()): ?>
        <li><?= $c['apellido'] . ', ' . $c['nombre'] ?> (<?= date('d/m', strtotime($c['fecha_nacimiento'])) ?>)</li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div class="box">
    <h2>🗓 Vencimientos</h2>
    <ul>
      <?php while($v = $vencimientos->fetch_assoc()): ?>
        <li><?= $v['apellido'] . ', ' . $v['nombre'] ?> (<?= date('d/m', strtotime($v['fecha_vencimiento'])) ?>)</li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div class="box">
    <form method="GET" style="margin-bottom: 10px;">
      <label for="fecha" style="color: gold;">🗓 Ver reservas del día: </label>
      <input type="date" id="fecha" name="fecha" value="<?= $fecha_filtro ?>" onchange="this.form.submit()">
    </form>
    <h2>📋 Reservas del día</h2>
    <ul id="contenedor-reservas">Cargando reservas...</ul>
  </div>

  <div class="cuadro">
    <h3>🧟 Alumnos que ingresaron Hoy</h3>
    <div id="contenedor-alumnos">Cargando alumnos...</div>
  </div>
</div>

</body>
</html>
