<?php
session_start();
if !isset($_SESSION["gimnasio_id"]) {
    die("⚠️ No has iniciado sesión correctamente.");
}
$gimnasio_id = $_SESSION["gimnasio_id"];
include 'conexion.php';

// Pagos del día
$pagos_dia = 0;
$pagos_mes = 0;
$ventas_dia = 0;
$ventas_mes = 0;
$cumples = [];
$vencimientos = [];

// Pagos del día
$res = $conexion->query("SELECT SUM(monto) as total FROM pagos WHERE gimnasio_id = $gimnasio_id AND DATE(fecha_pago) = CURDATE()");
if ($row = $res->fetch_assoc()) $pagos_dia = $row["total"];

// Pagos del mes
$res = $conexion->query("SELECT SUM(monto) as total FROM pagos WHERE gimnasio_id = $gimnasio_id AND MONTH(fecha_pago) = MONTH(CURDATE()) AND YEAR(fecha_pago) = YEAR(CURDATE())");
if ($row = $res->fetch_assoc()) $pagos_mes = $row["total"];

// Ventas del día
$res = $conexion->query("SELECT SUM(precio_venta) as total FROM ventas WHERE gimnasio_id = $gimnasio_id AND DATE(fecha) = CURDATE()");
if ($row = $res->fetch_assoc()) $ventas_dia = $row["total"];

// Ventas del mes
$res = $conexion->query("SELECT SUM(precio_venta) as total FROM ventas WHERE gimnasio_id = $gimnasio_id AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())");
if ($row = $res->fetch_assoc()) $ventas_mes = $row["total"];

// Cumpleaños del mes
$res = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = $gimnasio_id AND MONTH(fecha_nacimiento) = MONTH(CURDATE()) ORDER BY DAY(fecha_nacimiento)");
while ($row = $res->fetch_assoc()) {
    $cumples[] = $row;
}

// Próximos vencimientos
$res = $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m JOIN clientes c ON m.cliente_id = c.id WHERE m.gimnasio_id = $gimnasio_id AND fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY) ORDER BY m.fecha_vencimiento");
while ($row = $res->fetch_assoc()) {
    $vencimientos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Panel de Control</title>
  <style>
    body {
      background-color: #111;
      color: #f1c40f;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
    }
    .panel {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 20px;
    }
    .card {
      background-color: #222;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px #000;
    }
    .card h2 {
      margin-top: 0;
      font-size: 20px;
      color: #f1c40f;
    }
    .lista {
      list-style: none;
      padding-left: 0;
    }
    .lista li {
      padding: 5px 0;
    }
  </style>
</head>
<body>
  <h1>Bienvenido al Panel de Control</h1>
  <div class="panel">
    <div class="card">
      <h2>Pagos del Día</h2>
      <p><?php echo '$' . number_format($pagos_dia, 2); ?></p>
    </div>
    <div class="card">
      <h2>Pagos del Mes</h2>
      <p><?php echo '$' . number_format($pagos_mes, 2); ?></p>
    </div>
    <div class="card">
      <h2>Ventas del Día</h2>
      <p><?php echo '$' . number_format($ventas_dia, 2); ?></p>
    </div>
    <div class="card">
      <h2>Ventas del Mes</h2>
      <p><?php echo '$' . number_format($ventas_mes, 2); ?></p>
    </div>
  </div>

  <div class="panel">
    <div class="card">
      <h2>Cumpleaños del Mes</h2>
      <ul class="lista">
        <?php foreach($cumples as $c) {
          $f = date("d/m", strtotime($c["fecha_nacimiento"]));
          echo "<li>{$c['apellido']} {$c['nombre']} - {$f}</li>";
        } ?>
      </ul>
    </div>
    <div class="card">
      <h2>Próximos Vencimientos (10 días)</h2>
      <ul class="lista">
        <?php foreach($vencimientos as $v) {
          $f = date("d/m/Y", strtotime($v["fecha_vencimiento"]));
          echo "<li>{$v['apellido']} {$v['nombre']} - {$f}</li>";
        } ?>
      </ul>
    </div>
  </div>
</body>
</html>
