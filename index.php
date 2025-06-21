<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

// Validación
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];

// Función total por fecha
function getMonto($conexion, $tabla, $columnaFecha, $gimnasio_id, $periodo = 'DIA') {
    $hoy = date('Y-m-d');
    $inicioMes = date('Y-m-01');
    $fechaFiltro = $periodo == 'DIA' ? $hoy : $inicioMes;

    $stmt = $conexion->prepare("SELECT SUM(monto) as total FROM $tabla WHERE DATE($columnaFecha) >= ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $fechaFiltro, $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result()->fetch_assoc();
    return $resultado['total'] ?? 0;
}

// Función cumpleaños
function obtenerCumpleaños($conexion, $gimnasio_id) {
    $hoy = date('m-d');
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = ? AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= ? ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d') LIMIT 10";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("is", $gimnasio_id, $hoy);
    $stmt->execute();
    return $stmt->get_result();
}

// Función vencimientos
function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $diezDias = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
            FROM membresias m 
            JOIN clientes c ON m.cliente_id = c.id 
            WHERE m.fecha_vencimiento BETWEEN ? AND ? AND m.gimnasio_id = ? 
            ORDER BY m.fecha_vencimiento ASC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("ssi", $hoy, $diezDias, $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Función asistencias profesores
function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT p.apellido, rp.ingreso, rp.salida
            FROM registro_profesores rp
            JOIN profesores p ON rp.profesor_id = p.id
            WHERE DATE(rp.ingreso) = ? AND rp.gimnasio_id = ?";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("si", $fecha, $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

$pagosHoy = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagosMes = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventasHoy = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventasMes = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$cumples = obtenerCumpleaños($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$asistencias = obtenerAsistenciasProfesores($conexion, $gimnasio_id, date('Y-m-d'));
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel - Fight Academy</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #111;
      color: #f1f1f1;
      margin: 0;
      padding: 10px;
    }

    h1, h2 {
      color: gold;
      margin-top: 20px;
    }

    .tarjetas {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 15px;
    }

    .card {
      flex: 1 1 calc(50% - 20px);
      background: #222;
      padding: 15px;
      border-radius: 10px;
      min-width: 180px;
      box-shadow: 0 0 5px #000;
    }

    .card strong {
      color: gold;
    }

    ul {
      list-style: none;
      padding: 0;
    }

    @media (max-width: 768px) {
      .tarjetas {
        flex-direction: column;
      }
      .card {
        width: 100%;
      }
    }
  </style>
</head>
<body>

  <h1>Bienvenido al Panel</h1>

  <div class="tarjetas">
    <div class="card">
      <h2>Pagos del Día</h2>
      <p><strong>$<?= number_format($pagosHoy, 0, '', '.') ?></strong></p>
    </div>
    <div class="card">
      <h2>Pagos del Mes</h2>
      <p><strong>$<?= number_format($pagosMes, 0, '', '.') ?></strong></p>
    </div>
    <div class="card">
      <h2>Ventas del Día</h2>
      <p><strong>$<?= number_format($ventasHoy, 0, '', '.') ?></strong></p>
    </div>
    <div class="card">
      <h2>Ventas del Mes</h2>
      <p><strong>$<?= number_format($ventasMes, 0, '', '.') ?></strong></p>
    </div>
  </div>

  <h2>🎂 Próximos Cumpleaños</h2>
  <ul>
    <?php while($c = $cumples->fetch_assoc()): ?>
      <li><?= $c['nombre'] . ' ' . $c['apellido'] . ' - ' . date('d/m', strtotime($c['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>📆 Próximos Vencimientos</h2>
  <ul>
    <?php while($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] . ' - Vence: ' . date('d/m', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>🧑‍🏫 Asistencias Profesores (Hoy)</h2>
  <ul>
    <?php while($a = $asistencias->fetch_assoc()): ?>
      <li><?= $a['apellido'] ?> - Ingreso: <?= date('H:i', strtotime($a['ingreso'])) ?> 
        <?php 
          if ($a['salida']) {
            $ingreso = new DateTime($a['ingreso']);
            $salida = new DateTime($a['salida']);
            $intervalo = $ingreso->diff($salida);
            echo " / Salida: " . $salida->format('H:i') . " (Trabajó: " . $intervalo->format('%h:%I') . " hs)";
          } else {
            echo " / Aún presente";
          }
        ?>
      </li>
    <?php endwhile; ?>
  </ul>

</body>
</html>
