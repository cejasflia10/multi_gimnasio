
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");
include 'menu_cliente.php';

// Obtener progresos para tabla y gráfico
$progresos = $conexion->query("
    SELECT fecha, peso, altura, observaciones
    FROM seguimiento_nutricional
    WHERE cliente_id = $cliente_id
    ORDER BY fecha ASC
");

$fechas = [];
$pesos = [];
$progreso_data = [];

while ($p = $progresos->fetch_assoc()) {
    $altura_m = $p['altura'] / 100;
    $imc = ($altura_m > 0) ? round($p['peso'] / ($altura_m * $altura_m), 1) : 'N/A';

    $fechas[] = $p['fecha'];
    $pesos[] = $p['peso'];
    $progreso_data[] = [
        'fecha' => $p['fecha'],
        'peso' => $p['peso'],
        'altura' => $p['altura'],
        'imc' => $imc,
        'observaciones' => $p['observaciones']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📊 Progreso Físico</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 { text-align: center; }
        .grafico-container {
            max-width: 800px;
            margin: auto;
            background: #111;
            border: 1px solid gold;
            padding: 20px;
            border-radius: 10px;
        }
        canvas {
            background-color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>

<h1>📊 Progreso Físico</h1>

<?php if (count($progreso_data) > 0): ?>
<div class="grafico-container">
    <canvas id="graficoPeso"></canvas>
</div>

<table>
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Peso (kg)</th>
            <th>Altura (cm)</th>
            <th>IMC</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($progreso_data as $p): ?>
            <tr>
                <td><?= $p['fecha'] ?></td>
                <td><?= $p['peso'] ?></td>
                <td><?= $p['altura'] ?></td>
                <td><?= $p['imc'] ?></td>
                <td><?= $p['observaciones'] ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<script>
    const ctx = document.getElementById('graficoPeso').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($fechas) ?>,
            datasets: [{
                label: 'Peso (kg)',
                data: <?= json_encode($pesos) ?>,
                borderColor: 'gold',
                backgroundColor: 'rgba(255, 215, 0, 0.2)',
                fill: true,
                tension: 0.2,
                pointRadius: 5,
                pointHoverRadius: 7
            }]
        },
        options: {
            scales: {
                x: { ticks: { color: 'gold' } },
                y: { ticks: { color: 'gold' }, beginAtZero: true }
            },
            plugins: {
                legend: { labels: { color: 'gold' } }
            }
        }
    });
</script>
<?php else: ?>
    <p style="text-align: center;">No hay registros de progreso físico aún.</p>
<?php endif; ?>

</body>
</html>
