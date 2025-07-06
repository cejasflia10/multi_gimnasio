<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Función para obtener montos
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    switch ($tabla) {
        case 'ventas': $columna = 'monto_total'; break;
        case 'pagos': $columna = 'monto'; break;
        case 'membresias': $columna = 'total_pagado'; break;
        default: $columna = 'monto';
    }

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $res = $conexion->query($query);
    if ($res && $fila = $res->fetch_assoc()) {
        return $fila['total'] ?? 0;
    }
    return 0;
}

$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$total_ventas = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel Principal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background: #000; color: gold; font-family: Arial; margin: 0; padding: 20px; }
    .box { background: #1c1c1c; padding: 20px; margin: 10px 0; border-radius: 10px; }
    .bottom-bar {
        display: flex; justify-content: space-around; background: #222;
        position: fixed; bottom: 0; width: 100%; padding: 10px;
    }
    .bottom-bar a {
        color: gold; text-decoration: none; text-align: center; font-size: 13px;
    }
  </style>
</head>
<script>
function actualizarContadorMensajes() {
    fetch('contador_mensajes.php')
        .then(response => response.text())
        .then(numero => {
            const contenedor = document.getElementById('contador-mensajes');
            if (contenedor) {
                if (parseInt(numero) > 0) {
                    contenedor.innerText = '🔔 ' + numero;
                    contenedor.style.display = 'inline-block';
                } else {
                    contenedor.innerText = '';
                    contenedor.style.display = 'none';
                }
            }
        });
}

setInterval(actualizarContadorMensajes, 30000); // cada 30 segundos
actualizarContadorMensajes(); // al cargar
</script>

<body>
<h2>🏠 Panel General</h2>

<div class="box">
  <h3>📊 Ingresos del Día</h3>
  <p>$<?= number_format($pagos_dia, 0, ',', '.') ?></p>
</div>
<div class="box">
  <h3>📆 Ingresos del Mes</h3>
  <p>$<?= number_format($pagos_mes, 0, ',', '.') ?></p>
</div>
<div class="box">
  <h3>🛒 Ventas del Mes</h3>
  <p>$<?= number_format($ventas_mes, 0, ',', '.') ?></p>
</div>
<div class="box">
  <h3>🧾 Ventas del Día</h3>
  <p>$<?= number_format($total_ventas, 0, ',', '.') ?></p>
</div>
<?php include 'notificacion_mensajes.php'; ?>
<?php include 'notificacion_mensajes.php'; ?>
<?php include 'resumen_mensajes.php'; ?>
<span id="contador-mensajes" class="badge-mensajes" style="margin-left: 8px;">0</span>

<!-- Disciplina Chart -->
<div class="box">
    <h3>📈 Gráfico de Disciplinas</h3>
    <canvas id="grafico_disciplinas"></canvas>
    <?php
    $disciplinas = $conexion->query("SELECT disciplina, COUNT(*) as cantidad FROM clientes WHERE gimnasio_id = $gimnasio_id GROUP BY disciplina");
    $labels = [];
    $valores = [];
    while ($d = $disciplinas->fetch_assoc()) {
        $labels[] = $d['disciplina'];
        $valores[] = $d['cantidad'];
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('grafico_disciplinas').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labels) ?>,
        datasets: [{
            label: 'Cantidad de alumnos',
            data: <?= json_encode($valores) ?>,
            backgroundColor: 'gold',
            borderColor: 'white',
            borderWidth: 1
        }]
    },
    options: {
        scales: { y: { beginAtZero: true }},
        plugins: { legend: { display: false }}
    }
});
</script>

<!-- Barra inferior -->
<div class="bottom-bar">
  <a href="index.php"><i class="fas fa-home"></i><br>Inicio</a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><br>Clientes</a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><br>Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><br>QR</a>
  <a href="registrar_asistencia.php"><i class="fas fa-calendar-check"></i><br>Asistencias</a>
  <a href="ver_ventas.php"><i class="fas fa-shopping-cart"></i><br>Ventas</a>
</div>
</body>
</html>
