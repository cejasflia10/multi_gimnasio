<?php
session_start();
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Función para calcular totales
function getMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $rango = 'DIA', $columna = 'precio_venta') {
    $filtro_fecha = ($rango === 'DIA') ?
        "DATE($campo_fecha) = CURDATE()" :
        "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())";

    $sql = "SELECT SUM($columna) AS total FROM $tabla WHERE $filtro_fecha AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($sql);
    if ($fila = $resultado->fetch_assoc()) {
        return $fila['total'] ?? 0;
    }
    return 0;
}

// Totales
$ventasDia = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventasMes = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$pagosDia = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA', 'monto');
$pagosMes = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES', 'monto');

// Cumpleaños próximos (dentro del mes actual)
$cumples = [];
$sql_cumples = "SELECT CONCAT(nombre, ' ', apellido) AS nombre_completo, fecha_nacimiento 
                FROM clientes 
                WHERE id_gimnasio = $gimnasio_id AND MONTH(fecha_nacimiento) = MONTH(CURDATE()) 
                ORDER BY DAY(fecha_nacimiento)";
$res_cumples = $conexion->query($sql_cumples);
while ($row = $res_cumples->fetch_assoc()) {
    $cumples[] = $row;
}

// Vencimientos próximos (10 días)
$vencimientos = [];
$sql_venc = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
             FROM membresias m
             JOIN clientes c ON m.cliente_id = c.id
             WHERE m.id_gimnasio = $gimnasio_id AND m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
             ORDER BY m.fecha_vencimiento";
$res_venc = $conexion->query($sql_venc);
while ($row = $res_venc->fetch_assoc()) {
    $vencimientos[] = $row;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
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
      <?php foreach ($cumples as $c): ?>
        <tr>
          <td><?= $c['nombre_completo'] ?></td>
          <td><?= date('d/m', strtotime($c['fecha_nacimiento'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="lista">
    <h2>📅 Próximos Vencimientos</h2>
    <table>
      <tr><th>Cliente</th><th>Vence</th></tr>
      <?php foreach ($vencimientos as $v): ?>
        <tr>
          <td><?= $v['nombre'] . ' ' . $v['apellido'] ?></td>
          <td><?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>

</body>
</html>
