<?php
session_start();
include 'conexion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['cliente', 'admin', 'profesor'])) {
    die("Acceso denegado.");
}

$cliente_id = $_SESSION['cliente_id'] ?? ($_GET['id'] ?? null);
if (!$cliente_id) {
    die("ID de cliente no especificado.");
}

$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id")->fetch_assoc();
$seguimientos = $conexion->query("SELECT * FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha ASC");

// Datos para gráfica
$fechas = [];
$peso = [];
while ($s = $seguimientos->fetch_assoc()) {
    $fechas[] = $s['fecha'];
    $peso[] = $s['peso'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mi Seguimiento Nutricional</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { background-color: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
    .container { max-width: 800px; margin: auto; background: #222; padding: 20px; border-radius: 10px; }
    h2 { text-align: center; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    th, td { border: 1px solid gold; padding: 8px; text-align: center; }
    th { background-color: #333; }
    a.volver { background: gold; color: black; padding: 10px; border-radius: 5px; font-weight: bold; text-decoration: none; display: inline-block; margin-top: 10px; }
    .acciones { text-align: center; margin-top: 15px; }
  </style>
</head>
<body>
<div class="container">
  <h2>📋 Seguimiento Nutricional de <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

  <div class="acciones">
    <a class="volver" href="panel_cliente.php">← Volver al Panel</a>
  </div>

  <canvas id="graficoPeso" height="100"></canvas>

  <table>
    <tr><th>Fecha</th><th>Peso</th><th>Recomendaciones</th><th>Observaciones</th></tr>
    <?php
    $seguimientos = $conexion->query("SELECT * FROM seguimiento_nutricional WHERE cliente_id = $cliente_id ORDER BY fecha DESC");
    while ($s = $seguimientos->fetch_assoc()): ?>
      <tr>
        <td><?= $s['fecha'] ?></td>
        <td><?= $s['peso'] ?> kg</td>
        <td><?= htmlspecialchars($s['recomendaciones']) ?></td>
        <td><?= htmlspecialchars($s['observaciones']) ?></td>
      </tr>
    <?php endwhile; ?>
  </table>
</div>

<script>
const ctx = document.getElementById('graficoPeso').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [{
            label: 'Peso (kg)',
            data: <?= json_encode($peso) ?>,
            borderColor: 'gold',
            backgroundColor: 'rgba(255, 215, 0, 0.1)',
            borderWidth: 2,
            tension: 0.3,
            fill: true,
            pointRadius: 4,
            pointBackgroundColor: 'gold'
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: false,
                ticks: { color: 'gold' },
                grid: { color: '#444' }
            },
            x: {
                ticks: { color: 'gold' },
                grid: { color: '#333' }
            }
        },
        plugins: {
            legend: { labels: { color: 'gold' } }
        }
    }
});
</script>
</body>
</html>
