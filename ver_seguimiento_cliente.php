<?php
session_start();
include 'conexion.php';

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['cliente', 'admin', 'profesor'])) {
    die("Acceso denegado.");
}

// Mostrar menú del cliente si aplica
if ($rol === 'cliente') {
    include 'menu_cliente.php';
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
    <link rel="stylesheet" href="estilo_unificado.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="contenedor">

    <h2>📋 Seguimiento Nutricional de <?= htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']) ?></h2>

    <div class="acciones" style="text-align:center; margin: 10px 0;">
        <a class="volver" href="panel_cliente.php">← Volver al Panel</a>
    </div>

    <canvas id="graficoPeso" height="100"></canvas>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Peso</th>
                <th>Recomendaciones</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
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
        </tbody>
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
