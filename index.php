<?php
include 'menu.php';
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$usuario = $_SESSION['usuario'] ?? 'Usuario';

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES' ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())" : "$campo_fecha = CURDATE()";
    $columna = match($tabla) {
        'ventas' => 'monto_total',
        'pagos' => 'monto',
        'membresias' => 'total',
        default => 'monto'
    };
    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    return $conexion->query("SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, a.hora
        FROM asistencias a
        INNER JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN membresias m ON m.cliente_id = c.id
        WHERE a.fecha = CURDATE() AND a.id_gimnasio = $gimnasio_id
        ORDER BY a.hora DESC");
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    return $conexion->query("SELECT p.apellido, r.fecha, r.hora_entrada, r.hora_salida 
        FROM registro_asistencias_profesores r 
        INNER JOIN profesores p ON r.profesor_id = p.id 
        WHERE r.fecha = CURDATE() AND r.gimnasio_id = $gimnasio_id 
        ORDER BY r.hora_entrada DESC");
}

function obtenerDisciplinas($conexion, $gimnasio_id) {
    return $conexion->query("SELECT disciplina, COUNT(*) as cantidad FROM clientes WHERE gimnasio_id = $gimnasio_id GROUP BY disciplina");
}

function obtenerPagosPorMetodo($conexion, $gimnasio_id) {
    return $conexion->query("SELECT metodo_pago, COUNT(*) AS cantidad FROM pagos WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND id_gimnasio = $gimnasio_id GROUP BY metodo_pago");
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    return $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id ORDER BY DAY(fecha_nacimiento)");
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $fecha_limite = date('Y-m-d', strtotime('+10 days'));
    return $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento 
        FROM membresias m 
        INNER JOIN clientes c ON m.cliente_id = c.id 
        WHERE m.fecha_vencimiento BETWEEN CURDATE() AND '$fecha_limite' 
        AND m.id_gimnasio = $gimnasio_id 
        ORDER BY m.fecha_vencimiento");
}

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
$profesores_dia = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
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
  <style>
    body {
        background-color: #111;
        color: gold;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
        padding-left: 260px;
    }
    h2 { margin-top: 30px; text-align: center; }
    .tabla-responsive { overflow-x: auto; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; background: #111; min-width: 600px; }
    th, td { border: 1px solid #333; padding: 8px; color: white; text-align: left; }
    th { background: #444; }
    .panel { display: flex; flex-wrap: wrap; gap: 15px; justify-content: center; }
    .card { background: #222; padding: 15px; border-radius: 10px; box-shadow: 0 0 10px #000; width: 280px; }
    .graficos-container { display: flex; flex-wrap: wrap; justify-content: center; gap: 20px; margin: 20px 0; }
    .grafico-box { width: 260px; max-width: 90%; background: #222; padding: 10px; border-radius: 10px; }
    ul { padding-left: 20px; }
    @media (max-width: 768px) {
        body { padding-left: 10px !important; padding-right: 10px; }
        .card { width: 100%; }
        table { min-width: 100%; font-size: 14px; }
    }
  </style>
</head>
<body>

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

  <h2>Ingresos del Día - Profesores</h2>
  <div class="tabla-responsive">
    <table>
      <tr><th>Apellido</th><th>Fecha</th><th>Ingreso</th><th>Salida</th></tr>
      <?php if ($profesores_dia->num_rows === 0): ?>
        <tr><td colspan="4" style="text-align:center; color: orange;">Sin asistencias de profesores hoy.</td></tr>
      <?php else: ?>
        <?php while ($p = $profesores_dia->fetch_assoc()): ?>
        <tr>
          <td><?= $p['apellido'] ?></td>
          <td><?= $p['fecha'] ?></td>
          <td><?= $p['hora_entrada'] ?></td>
          <td><?= $p['hora_salida'] ?></td>
        </tr>
        <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>

  <h2>Estadísticas Visuales</h2>
  <div class="graficos-container">
    <div class="grafico-box"><canvas id="disciplinasChart"></canvas></div>
    <div class="grafico-box"><canvas id="pagosChart"></canvas></div>
  </div>

  <h2>Resumen Económico</h2>
  <div class="panel">
    <div class="card"><h3>Pagos del Día</h3><p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Pagos del Mes</h3><p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Día</h3><p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Ventas del Mes</h3><p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Día</h3><p>$<?= number_format($membresias_dia, 2, ',', '.') ?></p></div>
    <div class="card"><h3>Membresías del Mes</h3><p>$<?= number_format($membresias_mes, 2, ',', '.') ?></p></div>
  </div>

  <h2>Próximos Cumpleaños</h2>
  <ul>
    <?php while ($cumple = $cumples->fetch_assoc()): ?>
      <li><?= $cumple['nombre'] . ' ' . $cumple['apellido'] ?> - <?= date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Próximos Vencimientos</h2>
  <ul>
    <?php while ($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] ?> - <?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

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
          backgroundColor: 'gold',
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
