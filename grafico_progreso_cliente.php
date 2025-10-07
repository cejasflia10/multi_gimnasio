<?php
// grafico_progreso_cliente.php — Gráfico de evolución física (unificado)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/conexion.php';

$cliente_id = (int)($_SESSION['cliente_id'] ?? 0);
if ($cliente_id === 0) { die('Acceso denegado.'); }

if (function_exists('mysqli_report')) { mysqli_report(MYSQLI_REPORT_OFF); }
@$conexion->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* Datos */
$fechas = $pesos = $alturas = [];
if ($st = $conexion->prepare("
  SELECT fecha, peso, altura
  FROM progreso_fisico
  WHERE cliente_id = ?
  ORDER BY fecha ASC
")){
  $st->bind_param('i', $cliente_id);
  $st->execute();
  $res = $st->get_result();
  while ($fila = $res->fetch_assoc()) {
    $fechas[]  = (string)$fila['fecha'];
    $pesos[]   = is_numeric($fila['peso'])   ? (float)$fila['peso']   : null;
    $alturas[] = is_numeric($fila['altura']) ? (float)$fila['altura'] : null;
  }
  $st->close();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>📊 Gráfico de Evolución Física</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Estilo unificado (mismo que el resto) -->
  <link rel="stylesheet" href="/multi_gimnasio/estilo_unificado.css?v=20251006">
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <style>
    /* Overrides para visibilidad del menú (texto siempre visible) */
    .mc-top, .mc-top *, .mc-drawer *, .mc-tabs *, .mc-item, .mc-item *{
      -webkit-text-fill-color: currentColor !important;
      background: none !important;
      -webkit-background-clip: initial !important;
      background-clip: initial !important;
    }
    .mc-top{ background:#111 !important; border-bottom:1px solid #444 !important; }
    .mc-bar .mc-title{ color: gold !important; font-weight:800 !important; }
    .mc-bar .mc-link{ color: gold !important; }
    .mc-bar .mc-btn{ background:#ffd600 !important; color:#000 !important; }
    .mc-item{ background:#222 !important; border:1px solid #444 !important; color:gold !important; }
    .mc-item:hover{ background:#333 !important; }

    /* Página */
    .contenedor{ max-width: 900px; margin: 20px auto; }
    canvas{ background:#111; border:1px solid #444; border-radius:12px; padding:8px; }
    .msg{ background:#111; color:gold; border:1px solid gold; border-radius:8px; padding:10px; text-align:center; }
  </style>
</head>
<body>

<?php include __DIR__ . '/menu_cliente.php'; ?>

<div class="contenedor">
  <h1>📊 Gráfico de Evolución Física</h1>

  <?php if (empty($fechas)): ?>
    <p class="msg">Aún no tenés registros de progreso para graficar.</p>
  <?php else: ?>
    <canvas id="grafico" height="220"></canvas>
  <?php endif; ?>
</div>

<?php if (!empty($fechas)): ?>
<script>
const labels = <?= json_encode($fechas, JSON_UNESCAPED_UNICODE) ?>;
const pesos  = <?= json_encode($pesos) ?>;
const alturas= <?= json_encode($alturas) ?>;

const ctx = document.getElementById('grafico').getContext('2d');
new Chart(ctx, {
  type: 'line',
  data: {
    labels,
    datasets: [
      {
        label: 'Peso (kg)',
        data: pesos,
        borderColor: 'gold',
        backgroundColor: 'rgba(255,215,0,0.12)',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.25
      },
      {
        label: 'Altura (cm)',
        data: alturas,
        borderColor: 'lightblue',
        backgroundColor: 'rgba(173,216,230,0.12)',
        borderWidth: 2,
        pointRadius: 2,
        tension: 0.25
      }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        ticks: { color: '#ffd600' },
        grid:  { color: 'rgba(255,255,255,.08)' }
      },
      y: {
        beginAtZero: false,
        ticks: { color: '#ffd600' },
        grid:  { color: 'rgba(255,255,255,.08)' }
      }
    },
    plugins: {
      legend: {
        labels: { color: '#ffd600' }
      },
      tooltip: {
        mode: 'index',
        intersect: false
      }
    },
    interaction: { mode: 'nearest', intersect: false }
  }
});
</script>
<?php endif; ?>
</body>
</html>
