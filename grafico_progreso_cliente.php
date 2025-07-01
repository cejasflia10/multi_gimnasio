<?php
session_start();
include 'conexion.php';
include 'menu_cliente.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
if ($cliente_id == 0) die("Acceso denegado.");

$datos = $conexion->query("
    SELECT fecha, peso, altura
    FROM progreso_fisico
    WHERE cliente_id = $cliente_id
    ORDER BY fecha ASC
");

$fechas = [];
$pesos = [];
$alturas = [];

while ($fila = $datos->fetch_assoc()) {
    $fechas[] = $fila['fecha'];
    $pesos[] = floatval($fila['peso']);
    $alturas[] = floatval($fila['altura']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gráfico de Evolución Física</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        canvas { background: #111; border: 1px solid gold; padding: 10px; border-radius: 10px; }
    </style>
</head>
<body>

<h1>📊 Gráfico de Evolución Física</h1>

<canvas id="grafico" width="400" height="200"></canvas>

<script>
const ctx = document.getElementById('grafico').getContext('2d');
const chart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($fechas) ?>,
        datasets: [
            {
                label: 'Peso (kg)',
                data: <?= json_encode($pesos) ?>,
                borderColor: 'gold',
                backgroundColor: 'rgba(255,215,0,0.1)',
                borderWidth: 2,
                tension: 0.2
            },
            {
                label: 'Altura (cm)',
                data: <?= json_encode($alturas) ?>,
                borderColor: 'lightblue',
                backgroundColor: 'rgba(173,216,230,0.1)',
                borderWidth: 2,
                tension: 0.2
            }
        ]
    },
    options: {
        scales: {
            y: {
                ticks: { color: 'gold' },
                beginAtZero: true
            },
            x: {
                ticks: { color: 'gold' }
            }
        },
        plugins: {
            legend: {
                labels: { color: 'gold' }
            }
        }
    }
});
</script>

</body>
</html>
