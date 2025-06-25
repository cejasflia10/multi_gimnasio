<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$gimnasio_nombre = 'Gimnasio';
$proximo_vencimiento = '';
$cliente_activo = '';
if ($gimnasio_id > 0) {
    $res = $conexion->query("SELECT nombre, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id LIMIT 1");
    if ($fila = $res->fetch_assoc()) {
        $gimnasio_nombre = $fila['nombre'];
        $proximo_vencimiento = $fila['fecha_vencimiento'];
    }
    $res_cli = $conexion->query("SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
        FROM clientes 
        JOIN membresias ON clientes.id = membresias.cliente_id 
        WHERE clientes.gimnasio_id = $gimnasio_id 
        ORDER BY membresias.fecha_vencimiento ASC LIMIT 1");
    if ($c = $res_cli->fetch_assoc()) {
        $cliente_activo = $c['nombre'] . ' ' . $c['apellido'] . ' – Vence: ' . date('d/m/Y', strtotime($c['fecha_vencimiento']));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Principal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #111; color: gold; font-family: Arial, sans-serif; margin: 0; padding-bottom: 60px; }
    header {
      display: flex; justify-content: space-between; align-items: center;
      background-color: #1a1a1a; padding: 15px 20px; flex-wrap: wrap;
    }
    header h1 { font-size: 22px; color: gold; margin: 0; }
    .info-header { text-align: right; font-size: 14px; color: #ccc; }
    .container { padding: 20px; }
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px; text-align: center; margin-bottom: 30px;
    }
    .card {
      background-color: #1f1f1f; padding: 20px; border-radius: 12px;
      box-shadow: 0 0 8px #000;
    }
    .bar-section { margin-bottom: 25px; }
    .bar-title { font-weight: bold; margin-bottom: 10px; color: gold; }
    .bar-row {
      display: flex; gap: 10px; justify-content: space-between;
    }
    .bar {
      background-color: #444; border-radius: 5px; height: 18px;
      width: 100%; overflow: hidden;
    }
    .bar-inner-yellow { background-color: gold; height: 100%; width: 70%; }
    .bar-inner-orange { background-color: orange; height: 100%; width: 40%; }
    footer {
      text-align: center; background-color: #222; color: gold;
      padding: 10px; position: fixed; bottom: 0; width: 100%; font-size: 14px;
    }
    .bottom-bar {
      display: none;
    }
    @media (max-width: 768px) {
      .bottom-bar {
        display: flex; justify-content: space-around;
        background-color: #222; padding: 10px 0;
        position: fixed; bottom: 0; width: 100%; z-index: 999;
      }
      .bottom-bar a {
        color: gold; text-align: center; text-decoration: none; font-size: 13px;
      }
    }
  </style>
</head>
<body>
<header>
  <h1><?= $gimnasio_nombre ?></h1>
  <div class="info-header">
    <strong>Próximo vencimiento del gimnasio:</strong> <?= date('d/m/Y', strtotime($proximo_vencimiento)) ?><br>
    Cliente activo: <?= $cliente_activo ?>
  </div>
</header>
<?php include 'menu.php'; ?>
<div class="container">
  <div class="stats-grid">
    <div class="card"><h3>Ingresos del Día</h3><p>$4,800</p></div>
    <div class="card"><h3>Pagos del Día</h3><p>$3,500</p></div>
    <div class="card"><h3>Pagos del Mes</h3><p>$27,400</p></div>
    <div class="card"><h3>Ventas Totales</h3><p>$15,000</p></div>
  </div>
  <div class="bar-section">
    <div class="bar-title">Estadísticas por Disciplina</div>
    <div class="bar-row">
      <div class="bar"><div class="bar-inner-yellow"></div></div>
      <div class="bar"><div class="bar-inner-orange"></div></div>
    </div>
  </div>
  <div class="bar-section">
    <div class="bar-title">Ventas Mensuales</div>
    <div class="bar-row">
      <div class="bar"><div class="bar-inner-yellow" style="width:80%"></div></div>
      <div class="bar"><div class="bar-inner-orange" style="width:30%"></div></div>
    </div>
  </div>
  <div class="bar-section">
    <div class="bar-title">Próximos Vencimientos</div>
    <ul>
      <li>Lucia Ramírez - 28/06/2025</li>
      <li>Diego Martínez - 03/07/2025</li>
    </ul>
    <div class="bar-title">Próximos Cumpleaños</div>
    <ul>
      <li>Lucas Gómez - 25/06</li>
      <li>María Suárez - 28/06</li>
    </ul>
  </div>
</div>
<div class="bottom-bar">
  <a href="index.php"><i class="fas fa-home"></i><br>Inicio</a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><br>Clientes</a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><br>Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><br>QR</a>
  <a href="registrar_asistencia.php"><i class="fas fa-calendar-check"></i><br>Asistencias</a>
  <a href="ver_ventas.php"><i class="fas fa-shopping-cart"></i><br>Ventas</a>
</div>
<footer>
  Sistema de Gestión Multi-Gimnasio - Versión App Profesional
</footer>
</body>
</html>
