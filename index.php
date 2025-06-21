<?php
include 'menu.php'; // Asegurate de tener el menú cargado

// Simulamos totales
$ventasDia = 5000.00;
$ventasMes = 42000.50;
$pagosDia = 3200.00;
$pagosMes = 28700.00;

// Simulamos próximos cumpleaños
$cumples = [
  ['nombre' => 'Juan Pérez', 'fecha' => '2025-06-23'],
  ['nombre' => 'María López', 'fecha' => '2025-06-26'],
  ['nombre' => 'Carlos Ruiz', 'fecha' => '2025-06-29'],
];

// Simulamos vencimientos
$vencimientos = [
  ['cliente' => 'Ana Torres', 'fecha' => '2025-06-24'],
  ['cliente' => 'Luis Gómez', 'fecha' => '2025-06-26'],
  ['cliente' => 'Pedro Álvarez', 'fecha' => '2025-07-01'],
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control - Fight Academy</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', sans-serif;
      background-color: #111;
      color: #f1f1f1;
    }
    .contenido {
      margin-left: 260px;
      padding: 20px;
    }
    .tarjetas {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .tarjeta {
      background-color: #222;
      border-left: 5px solid #f7d774;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 0 10px #000;
    }
    .tarjeta h3 {
      margin: 0 0 10px;
      font-size: 1.1em;
      color: #f7d774;
    }
    .tarjeta p {
      font-size: 1.4em;
      font-weight: bold;
      margin: 0;
    }

    .lista {
      margin-top: 40px;
    }

    .lista h2 {
      color: #f7d774;
    }

    table {
      width: 100%;
      background-color: #1a1a1a;
      color: #fff;
      border-collapse: collapse;
    }

    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #333;
    }

    @media (max-width: 768px) {
      .contenido {
        margin-left: 0;
        padding: 10px;
      }
    }
  </style>
</head>
<body>

<div class="contenido">
  <h1>Panel de Control</h1>

  <div class="tarjetas">
    <div class="tarjeta">
      <h3>Ventas del Día</h3>
      <p>$<?= number_format($ventasDia, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
      <h3>Ventas del Mes</h3>
      <p>$<?= number_format($ventasMes, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
      <h3>Pagos del Día</h3>
      <p>$<?= number_format($pagosDia, 2, ',', '.') ?></p>
    </div>
    <div class="tarjeta">
      <h3>Pagos del Mes</h3>
      <p>$<?= number_format($pagosMes, 2, ',', '.') ?></p>
    </div>
  </div>

  <div class="lista">
    <h2>🎂 Próximos Cumpleaños</h2>
    <table>
      <tr><th>Nombre</th><th>Fecha</th></tr>
      <?php foreach ($cumples as $cumple): ?>
        <tr>
          <td><?= $cumple['nombre'] ?></td>
          <td><?= $cumple['fecha'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="lista">
    <h2>📅 Próximos Vencimientos</h2>
    <table>
      <tr><th>Cliente</th><th>Fecha de Vencimiento</th></tr>
      <?php foreach ($vencimientos as $v): ?>
        <tr>
          <td><?= $v['cliente'] ?></td>
          <td><?= $v['fecha'] ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

</body>
</html>
