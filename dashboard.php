
<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo "<p style='color: yellow; font-family: Arial;'>⚠️ No has iniciado sesión correctamente.</p>";
    exit;
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Funciones para estadísticas
function obtenerSuma($conexion, $tabla, $campo, $condicion = "") {
    $query = "SELECT SUM($campo) as total FROM $tabla $condicion";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

$hoy = date('Y-m-d');
$mes = date('Y-m');

$ingresos_dia = obtenerSuma($conexion, 'pagos', 'monto', "WHERE fecha = '$hoy' AND gimnasio_id = $gimnasio_id");
$ingresos_mes = obtenerSuma($conexion, 'pagos', 'monto', "WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id");

$ventas_dia = obtenerSuma($conexion, 'ventas', 'monto_total', "WHERE fecha = '$hoy' AND gimnasio_id = $gimnasio_id");
$ventas_mes = obtenerSuma($conexion, 'ventas', 'monto_total', "WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel de Control</title>
  <style>
    body {
        background-color: #111;
        color: #FFD700;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
    }
    .card {
        background-color: #222;
        padding: 20px;
        margin: 10px 0;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
    }
    h1 {
        text-align: center;
        color: #FFD700;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
  </style>
</head>
<body>
  <h1>Dashboard - Estadísticas</h1>
  <div class="grid">
    <div class="card"><strong>Pagos del día:</strong> $<?= number_format($ingresos_dia, 2) ?></div>
    <div class="card"><strong>Pagos del mes:</strong> $<?= number_format($ingresos_mes, 2) ?></div>
    <div class="card"><strong>Ventas del día:</strong> $<?= number_format($ventas_dia, 2) ?></div>
    <div class="card"><strong>Ventas del mes:</strong> $<?= number_format($ventas_mes, 2) ?></div>
  </div>
</body>
</html>
