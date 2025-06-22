<?php
include 'menu.php';
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// FUNCIONES DE CONSULTA

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES' ? "MONTH($campo_fecha) = MONTH(CURDATE())" : "$campo_fecha = CURDATE()";

    switch ($tabla) {
        case 'ventas':
            $columna = 'monto_total';
            break;
        case 'pagos':
            $columna = 'monto';
            break;
        case 'membresias':
            $columna = 'total';
            break;
        default:
            $columna = 'monto';
    }

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    $query = "SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id ORDER BY DAY(fecha_nacimiento)";
    return $conexion->query($query);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $fecha_limite = date('Y-m-d', strtotime('+10 days'));
    $query = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
              FROM membresias m 
              INNER JOIN clientes c ON m.cliente_id = c.id 
              WHERE m.fecha_vencimiento BETWEEN CURDATE() AND '$fecha_limite' 
              AND m.id_gimnasio = $gimnasio_id 
              ORDER BY m.fecha_vencimiento";
    return $conexion->query($query);
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $query = "SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, a.hora 
              FROM asistencias a 
              INNER JOIN clientes c ON a.cliente_id = c.id 
              LEFT JOIN membresias m ON c.id = m.cliente_id 
              WHERE a.fecha = CURDATE() AND a.tipo = 'cliente' AND c.gimnasio_id = $gimnasio_id 
              ORDER BY a.hora DESC";
    return $conexion->query($query);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $query = "SELECT p.apellido, r.fecha, r.hora_entrada, r.hora_salida 
              FROM registro_asistencias_profesores r 
              INNER JOIN profesores p ON r.profesor_id = p.id 
              WHERE r.fecha = CURDATE() AND p.gimnasio_id = $gimnasio_id 
              ORDER BY r.hora_entrada DESC";
    return $conexion->query($query);
}

// DATOS
$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');

$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

$membresias_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$membresias_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');

$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$clientes_dia = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$profesores_dia = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <style>
    body {
        background-color: #111;
        color: gold;
        font-family: Arial, sans-serif;
        margin: 0;
        padding: 20px;
    }
    .panel {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-around;
        gap: 15px;
    }
    .card {
        background: #222;
        padding: 15px;
        border-radius: 10px;
        box-shadow: 0 0 10px #000;
        width: 300px;
    }
    h2 {
        color: gold;
        margin-top: 0;
    }
    ul {
        padding-left: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: #111;
    }
    th, td {
        border: 1px solid #333;
        padding: 5px;
        color: white;
    }
    th {
        background: #444;
    }
    @media (max-width: 600px) {
        .card { width: 100%; }
    }
  </style>
</head>
<body>
  <h2>Resumen Económico</h2>
  <div class="panel">
    <div class="card">
      <h3>Pagos del Día</h3>
      <p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p>
    </div>
    <div class="card">
      <h3>Pagos del Mes</h3>
      <p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p>
    </div>
    <div class="card">
      <h3>Ventas del Día</h3>
      <p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p>
    </div>
    <div class="card">
      <h3>Ventas del Mes</h3>
      <p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p>
    </div>
    <div class="card">
      <h3>Membresías del Día</h3>
      <p>$<?= number_format($membresias_dia, 2, ',', '.') ?></p>
    </div>
    <div class="card">
      <h3>Membresías del Mes</h3>
      <p>$<?= number_format($membresias_mes, 2, ',', '.') ?></p>
    </div>
  </div>

  <h2>Ingresos del Día - Clientes</h2>
  <table>
    <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Vencimiento</th><th>Hora</th></tr>
    <?php while ($c = $clientes_dia->fetch_assoc()): ?>
      <tr>
        <td><?= $c['nombre'] . ' ' . $c['apellido'] ?></td>
        <td><?= $c['dni'] ?></td>
        <td><?= $c['disciplina'] ?></td>
        <td><?= $c['fecha_vencimiento'] ?></td>
        <td><?= $c['hora'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>

  <h2>Ingresos del Día - Profesores</h2>
  <table>
    <tr><th>Apellido</th><th>Fecha</th><th>Ingreso</th><th>Salida</th></tr>
    <?php while ($p = $profesores_dia->fetch_assoc()): ?>
      <tr>
        <td><?= $p['apellido'] ?></td>
        <td><?= $p['fecha'] ?></td>
        <td><?= $p['hora_entrada'] ?></td>
        <td><?= $p['hora_salida'] ?></td>
      </tr>
    <?php endwhile; ?>
  </table>

  <h2>Próximos Cumpleaños</h2>
  <ul>
    <?php while ($cumple = $cumples->fetch_assoc()): ?>
      <li><?= $cumple['nombre'] . ' ' . $cumple['apellido'] ?> - <?= date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>

  <h2>Próximos Vencimientos</h2>
  <ul>
    <?php while ($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] ?> - <?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>
</body>
</html>
