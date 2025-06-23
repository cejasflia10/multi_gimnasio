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

function obtenerPagosPorMetodo($conexion, $gimnasio_id) {
    $metodos = ['Efectivo', 'Transferencia', 'Débito', 'Crédito'];
    $resultados = [];
    foreach ($metodos as $metodo) {
        $stmt = $conexion->prepare("SELECT SUM(monto) AS total FROM pagos WHERE metodo_pago = ? AND fecha = CURDATE() AND id_gimnasio = ?");
        $stmt->bind_param("si", $metodo, $gimnasio_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $resultados[$metodo] = $res['total'] ?? 0;
        $stmt->close();
    }
    return $resultados;
}

function obtenerAlumnosPorDisciplina($conexion, $gimnasio_id) {
    $query = "SELECT disciplina, COUNT(*) as cantidad FROM clientes WHERE gimnasio_id = $gimnasio_id GROUP BY disciplina";
    $resultado = $conexion->query($query);
    $datos = [];
    while ($fila = $resultado->fetch_assoc()) {
        $datos[$fila['disciplina']] = $fila['cantidad'];
    }
    return $datos;
}

$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$membresias_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$membresias_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$pagos_por_metodo = obtenerPagosPorMetodo($conexion, $gimnasio_id);
$alumnos_por_disciplina = obtenerAlumnosPorDisciplina($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel General</title>
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
    h2 {
      color: gold;
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
    .grafico {
      background: #222;
      padding: 20px;
      border-radius: 10px;
      margin-top: 30px;
      width: 100%;
      max-width: 400px;
    }
    @media (max-width: 768px) {
      body {
        padding-left: 10px;
        padding-right: 10px;
      }
    }
  </style>
</head>
<body>

<h2>¡Buenos días, <?= htmlspecialchars($usuario) ?>!</h2>

<div class="panel">
  <div class="card"><h3>Pagos del Día</h3><p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p></div>
  <div class="card"><h3>Pagos del Mes</h3><p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p></div>
  <div class="card"><h3>Ventas del Día</h3><p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p></div>
  <div class="card"><h3>Ventas del Mes</h3><p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p></div>
  <div class="card"><h3>Membresías del Día</h3><p>$<?= number_format($membresias_dia, 2, ',', '.') ?></p></div>
  <div class="card"><h3>Membresías del Mes</h3><p>$<?= number_format($membresias_mes, 2, ',', '.') ?></p></div>
</div>

<!-- GRÁFICOS -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center;">
  <div class="grafico">
    <canvas id="graficoDisciplina"></canvas>
  </div>
  <div class="grafico">
    <canvas id="graficoPagos"></canvas>
  </div>
</div>

<script>
const ctxDisciplina = document.getElementById('graficoDisciplina');
new Chart(ctxDisciplina, {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_keys($alumnos_por_disciplina)) ?>,
    datasets: [{
      label: 'Alumnos',
      data: <?= json_encode(array_values($alumnos_por_disciplina)) ?>,
      backgroundColor: ['#FFD700', '#DAA520', '#FF8C00', '#ADFF2F', '#20B2AA']
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Alumnos por Disciplina', color: 'gold' },
      legend: { labels: { color: 'white' } }
    }
  }
});

const ctxPagos = document.getElementById('graficoPagos');
new Chart(ctxPagos, {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($pagos_por_metodo)) ?>,
    datasets: [{
      label: 'Pagos ($)',
      data: <?= json_encode(array_values($pagos_por_metodo)) ?>,
      backgroundColor: ['#FFD700', '#DAA520', '#FF8C00', '#B8860B']
    }]
  },
  options: {
    plugins: {
      title: { display: true, text: 'Pagos por Método (Hoy)', color: 'gold' },
      legend: { labels: { color: 'white' } }
    },
    scales: {
      y: { ticks: { color: 'white' }, beginAtZero: true },
      x: { ticks: { color: 'white' } }
    }
  }
});
</script>

</body>
</html>
