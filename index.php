<?php
include 'menu.php';
include 'conexion.php';
include_once 'funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$usuario = $_SESSION['usuario'] ?? 'Usuario';

// DATOS
$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$membresias_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$membresias_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');

$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$clientes_dia = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$graf_disciplinas = obtenerDisciplinas($conexion, $gimnasio_id);
$graf_metodos_pago = obtenerPagosPorMetodo($conexion, $gimnasio_id);
?>

<!<?php
include 'menu.php';
include 'conexion.php';
include_once 'funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];
$usuario = $_SESSION['usuario'] ?? 'Usuario';

$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$membresias_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$membresias_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');

$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$clientes_dia = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$graf_disciplinas = obtenerDisciplinas($conexion, $gimnasio_id);
$graf_metodos_pago = obtenerPagosPorMetodo($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
        background-color: #111;
        color: gold;
        font-family: Arial, sans-serif;
        margin: 0;
    }

    .contenido {
        padding: 20px;
        margin-left: 260px;
        max-width: 1000px;
        margin-right: auto;
        margin-bottom: 80px; /* espacio para menú móvil */
    }

    h2 {
        margin-top: 30px;
        text-align: center;
    }

    .tabla-responsive {
        overflow-x: auto;
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: #111;
        min-width: 600px;
    }

    th, td {
        border: 1px solid #333;
        padding: 8px;
        color: white;
        text-align: left;
    }

    th {
        background: #444;
    }

    .panel {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
    }

    .card {
        background: #222;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
        width: 280px;
    }

    .graficos-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        margin: 20px 0;
    }

    .grafico-box {
        width: 220px;
        max-width: 90%;
        background: #222;
        padding: 10px;
        border-radius: 10px;
    }

    ul {
        padding-left: 20px;
    }

    @media (max-width: 768px) {
        .contenido {
            margin-left: 0 !important;
            padding: 10px;
        }

        .card {
            width: 100%;
        }

        table {
            min-width: 100%;
            font-size: 14px;
        }
    }

    /* Menú inferior visible solo en móviles */
    @media (max-width: 768px) {
      .mobile-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background-color: #000;
        border-top: 2px solid gold;
        display: flex;
        justify-content: space-around;
        padding: 4px 0;
        z-index: 1000;
      }

      .mobile-footer a {
        color: gold;
        text-decoration: none;
        font-size: 12px;
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .mobile-footer i {
        font-size: 18px;
        margin-bottom: 2px;
      }
    }
  </style>
</head>
<body>

<div class="contenido">

  <h2><?= date('H') < 12 ? '¡Buenos días' : '¡Buenas tardes' ?>, <?= $usuario ?>!</h2>

  <h2>Ingresos del Día - Clientes</h2>
  <div class="tabla-responsive">
    <table>
      <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Vencimiento</th><th>Hora</th></tr>
      <?php if ($clientes_dia->num_rows === 0): ?>
        <tr><td colspan="5" style="text-align:center; color: orange;">Sin ingresos registrados hoy.</td></tr>
      <?php else: ?>
        <?php while ($c = $clientes_dia->fetch_assoc()): ?>
        <tr>
          <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
          <td><?= $c['dni'] ?></td>
          <td><?= $c['disciplina'] ?></td>
          <td><?= $c['fecha_vencimiento'] ?? '---' ?></td>
          <td><?= $c['hora'] ?></td>
        </tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>

  <h2>Próximos Vencimientos</h2>
  <ul>
    <?php while ($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] ?> - <?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Estadísticas Visuales</h2>
  <div class="graficos-container">
    <div class="grafico-box"><canvas id="disciplinasChart"></canvas></div>
    <div class="grafico-box"><canvas id="pagosChart"></canvas></div>
  </div>

  <h2>Próximos Cumpleaños</h2>
  <ul>
    <?php while ($cumple = $cumples->fetch_assoc()): ?>
      <li><?= $cumple['nombre'] . ' ' . $cumple['apellido'] ?> - <?= date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Resumen Económico</h2>
  <div class="panel">
    <div class="card"><h3>Pagos del Día</h3><p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Pagos del Mes</h3><p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Día</h3><p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Mes</h3><p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Día</h3><p>$<?= number_format($membresias_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Mes</h3><p>$<?= number_format($membresias_mes, 2, ',', '.') ?></p></div>
  </div>

</div>

<!-- Menú inferior móvil -->
<div class="mobile-footer">
  <a href="index.php"><i class="fas fa-home"></i>Inicio</a>
  <a href="clientes.php"><i class="fas fa-users"></i>Clientes</a>
  <a href="membresias.php"><i class="fas fa-id-card-alt"></i>Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i>QR</a>
  <a href="asistencias.php"><i class="fas fa-calendar-check"></i>Asistencias</a>
</div>

<script>
  const ctx1 = document.getElementById('disciplinasChart').getContext('2d');
  const ctx2 = document.getElementById('pagosChart').getContext('2d');

  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: [<?php while ($row = $graf_disciplinas->fetch_assoc()) echo "'{$row['disciplina']}',"; ?>],
      datasets: [{
        label: 'Cantidad de alumnos',
        data: [<?php mysqli_data_seek($graf_disciplinas, 0); while ($row = $graf_disciplinas->fetch_assoc()) echo "{$row['cantidad']},"; ?>],
        backgroundColor: ['gold', 'orange', 'white', 'gray', 'red', 'blue', 'green'],
        borderRadius: 6
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });

  new Chart(ctx2, {
    type: 'pie',
    data: {
      labels: [<?php while ($pago = $graf_metodos_pago->fetch_assoc()) echo "'{$pago['metodo_pago']}',"; ?>],
      datasets: [{
        data: [<?php mysqli_data_seek($graf_metodos_pago, 0); while ($pago = $graf_metodos_pago->fetch_assoc()) echo "{$pago['cantidad']},"; ?>],
        backgroundColor: ['gold', 'orange', 'white', 'gray', 'red']
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
</script>

</body>
</html>
 html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
        background-color: #111;
        color: gold;
        font-family: Arial, sans-serif;
        margin: 0;
    }

    .contenido {
        padding: 20px;
        margin-left: 260px;
        max-width: 1000px;
        margin-right: auto;
    }

    h2 {
        margin-top: 30px;
        text-align: center;
    }

    .tabla-responsive {
        overflow-x: auto;
        margin-bottom: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: #111;
        min-width: 600px;
    }

    th, td {
        border: 1px solid #333;
        padding: 8px;
        color: white;
        text-align: left;
    }

    th {
        background: #444;
    }

    .panel {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
    }

    .card {
        background: #222;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
        width: 280px;
    }

    .graficos-container {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        margin: 20px 0;
    }

    .grafico-box {
        width: 260px;
        max-width: 90%;
        background: #222;
        padding: 10px;
        border-radius: 10px;
    }

    ul {
        padding-left: 20px;
    }

    .menu-inferior {
        display: none;
    }

    @media (max-width: 768px) {
        .contenido {
            margin-left: 0 !important;
            padding: 10px;
        }

        .card {
            width: 100%;
        }

        table {
            min-width: 100%;
            font-size: 14px;
        }

        .sidebar {
            display: none !important;
        }

        .menu-inferior {
            display: flex;
            justify-content: space-around;
            background-color: #111;
            color: gold;
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            padding: 10px 0;
            border-top: 2px solid gold;
            z-index: 9999;
        }

        .menu-inferior a {
            text-align: center;
            flex: 1;
            font-size: 12px;
            color: gold;
            text-decoration: none;
        }

        .menu-inferior i {
            display: block;
            font-size: 18px;
        }
    }
  </style>
</head>
<body>

<div class="contenido">

  <h2><?= date('H') < 12 ? '¡Buenos días' : '¡Buenas tardes' ?>, <?= $usuario ?>!</h2>

  <h2>Ingresos del Día - Clientes</h2>
  <div class="tabla-responsive">
    <table>
      <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Vencimiento</th><th>Hora</th></tr>
      <?php if ($clientes_dia->num_rows === 0): ?>
        <tr><td colspan="5" style="text-align:center; color: orange;">Sin ingresos registrados hoy.</td></tr>
      <?php else: ?>
        <?php while ($c = $clientes_dia->fetch_assoc()): ?>
        <tr>
          <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
          <td><?= $c['dni'] ?></td>
          <td><?= $c['disciplina'] ?></td>
          <td><?= $c['fecha_vencimiento'] ?? '---' ?></td>
          <td><?= $c['hora'] ?></td>
        </tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>

  <h2>Próximos Vencimientos</h2>
  <ul>
    <?php while ($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] ?> - <?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Estadísticas Visuales</h2>
  <div class="graficos-container">
    <div class="grafico-box"><canvas id="disciplinasChart"></canvas></div>
    <div class="grafico-box"><canvas id="pagosChart"></canvas></div>
  </div>

  <h2>Próximos Cumpleaños</h2>
  <ul>
    <?php while ($cumple = $cumples->fetch_assoc()): ?>
      <li><?= $cumple['nombre'] . ' ' . $cumple['apellido'] ?> - <?= date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Resumen Económico</h2>
  <div class="panel">
    <div class="card"><h3>Pagos del Día</h3><p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Pagos del Mes</h3><p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Día</h3><p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Mes</h3><p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Día</h3><p>$<?= number_format($membresias_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Mes</h3><p>$<?= number_format($membresias_mes, 2, ',', '.') ?></p></div>
  </div>
</div>

<!-- Menú inferior solo para móviles -->
<div class="menu-inferior">
  <a href="index.php"><i class="fas fa-home"></i><span>Inicio</span></a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><span>Clientes</span></a>
  <a href="ver_membresias.php"><i class="fas fa-id-card-alt"></i><span>Membresías</span></a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><span>QR</span></a>
  <a href="ver_asistencias.php"><i class="fas fa-calendar-check"></i><span>Asistencias</span></a>
</div>

<script>
  const ctx1 = document.getElementById('disciplinasChart').getContext('2d');
  const ctx2 = document.getElementById('pagosChart').getContext('2d');

  new Chart(ctx1, {
    type: 'bar',
    data: {
      labels: [<?php while ($row = $graf_disciplinas->fetch_assoc()) echo "'{$row['disciplina']}',"; ?>],
      datasets: [{
        label: 'Cantidad de alumnos',
        data: [<?php mysqli_data_seek($graf_disciplinas, 0); while ($row = $graf_disciplinas->fetch_assoc()) echo "{$row['cantidad']},"; ?>],
        backgroundColor: ['#f1c40f', '#e67e22', '#ecf0f1', '#2ecc71', '#3498db'],
        borderRadius: 4,
        barThickness: 40
      }]
    },
    options: { responsive: true, plugins: { legend: { display: false } } }
  });

  new Chart(ctx2, {
    type: 'pie',
    data: {
      labels: [<?php while ($pago = $graf_metodos_pago->fetch_assoc()) echo "'{$pago['metodo_pago']}',"; ?>],
      datasets: [{
        data: [<?php mysqli_data_seek($graf_metodos_pago, 0); while ($pago = $graf_metodos_pago->fetch_assoc()) echo "{$pago['cantidad']},"; ?>],
        backgroundColor: ['gold', 'orange', 'white', 'gray', 'red']
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
</script>

</body>
</html>
