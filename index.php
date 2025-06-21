<?php
session_start();
include 'conexion.php';
include 'menu.php';

// ID del gimnasio actual
$gimnasio_id = $_SESSION['gimnasio_id'];

// Fechas actuales
$hoy = date('Y-m-d');
$mesActual = date('m');
$anioActual = date('Y');

// Función para obtener montos
function getMonto($conexion, $tabla, $campoFecha, $gimnasio_id, $tipo = 'DIA') {
    $fechaCondicion = $tipo === 'DIA' ? "DATE($campoFecha) = CURDATE()" : "MONTH($campoFecha) = MONTH(CURDATE()) AND YEAR($campoFecha) = YEAR(CURDATE())";
    $query = "SELECT SUM(monto) AS total FROM $tabla WHERE gimnasio_id = $gimnasio_id AND $fechaCondicion";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

// Función para cumpleaños
function getCumpleanios($conexion, $gimnasio_id) {
    $hoy = date('m-d');
    $query = "SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = $gimnasio_id AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= '$hoy' ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d') ASC LIMIT 5";
    return $conexion->query($query);
}

// Vencimientos
function getVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $limite = date('Y-m-d', strtotime('+10 days'));
    $query = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
              FROM membresias m 
              JOIN clientes c ON m.cliente_id = c.id 
              WHERE m.fecha_vencimiento BETWEEN '$hoy' AND '$limite' AND c.gimnasio_id = $gimnasio_id 
              ORDER BY m.fecha_vencimiento ASC";
    return $conexion->query($query);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel de Control - Fight Academy</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #111;
      color: #FFD700;
      margin: 0;
      padding: 0;
    }
    .contenedor {
      margin-left: 220px;
      padding: 20px;
    }
    h1 {
      text-align: center;
      color: #FFD700;
    }
    .tarjeta {
      background-color: #222;
      border: 1px solid #FFD700;
      border-radius: 10px;
      padding: 15px;
      margin: 10px 0;
    }
    .tarjeta h3 {
      margin: 0;
      font-size: 20px;
    }
    .tarjeta span {
      font-size: 24px;
      font-weight: bold;
    }
    .tarjeta-lista ul {
      margin: 0;
      padding: 0 20px;
    }
    .tarjeta-lista li {
      padding: 5px 0;
    }
    .icono {
      margin-right: 10px;
    }
    @media screen and (max-width: 768px) {
      .contenedor {
        margin-left: 0;
        padding: 10px;
      }
    }
  </style>
</head>
<body>
  <div class="contenedor">
    <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h1>
    <h2>Panel de control de Fight Academy</h2>

    <div class="tarjeta">
      <h3>💵 Pagos del día: <span>$<?php echo getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA'); ?></span></h3>
    </div>

    <div class="tarjeta">
      <h3>📅 Pagos del mes: <span>$<?php echo getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES'); ?></span></h3>
    </div>

    <div class="tarjeta">
      <h3>🛒 Ventas del día: <span>$<?php echo getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA'); ?></span></h3>
    </div>

    <div class="tarjeta">
      <h3>📦 Ventas del mes: <span>$<?php echo getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES'); ?></span></h3>
    </div>

    <div class="tarjeta tarjeta-lista">
      <h3>🎉 Próximos cumpleaños</h3>
      <ul>
        <?php
        $cumples = getCumpleanios($conexion, $gimnasio_id);
        while ($c = $cumples->fetch_assoc()) {
          echo "<li>🎂 {$c['nombre']} {$c['apellido']} - " . date('d/m', strtotime($c['fecha_nacimiento'])) . "</li>";
        }
        ?>
      </ul>
    </div>

    <div class="tarjeta tarjeta-lista">
      <h3>⏳ Próximos vencimientos</h3>
      <ul>
        <?php
        $vencimientos = getVencimientos($conexion, $gimnasio_id);
        while ($v = $vencimientos->fetch_assoc()) {
          echo "<li>📌 {$v['nombre']} {$v['apellido']} - " . date('d/m/Y', strtotime($v['fecha_vencimiento'])) . "</li>";
        }
        ?>
      </ul>
    </div>

    <div class="tarjeta tarjeta-lista">
      <h3>📋 Asistencias del día - <?php echo date('d/m/Y'); ?></h3>
      <ul>
        <li><span class="icono">👥</span> <a href="asistencias_index.php">Clientes</a></li>
        <li><span class="icono">🧑‍🏫</span> <a href="asistencias_profesores.php">Profesores</a></li>
      </ul>
    </div>
  </div>
</body>
</html>
