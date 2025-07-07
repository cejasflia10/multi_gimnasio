<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Buscar datos del gimnasio
$gimnasio = $conexion->query("SELECT nombre, logo, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gym = $gimnasio['nombre'] ?? 'Gimnasio';
$logo = $gimnasio['logo'] ?? '';
$fecha_venc = $gimnasio['fecha_vencimiento'] ?? '---';

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $cond = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";
    $col = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'pagos') ? 'monto' : 'total_pagado');
    $q = "SELECT SUM($col) AS total FROM $tabla WHERE $cond AND gimnasio_id = $gimnasio_id";
    $res = $conexion->query($q);
    return $res && $res->num_rows > 0 ? ($res->fetch_assoc()['total'] ?? 0) : 0;
}

$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');

$cumples = $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE gimnasio_id = $gimnasio_id AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= DATE_FORMAT(CURDATE(), '%m-%d') ORDER BY DATE_FORMAT(fecha_nacimiento, '%m-%d') LIMIT 5");

$vencimientos = $conexion->query("
    SELECT c.nombre, c.apellido, m.fecha_vencimiento 
    FROM membresias m 
    JOIN clientes c ON m.cliente_id = c.id 
    WHERE m.gimnasio_id = $gimnasio_id AND m.fecha_vencimiento >= CURDATE() 
    ORDER BY m.fecha_vencimiento ASC LIMIT 5
");
$reservas = $conexion->query("
    SELECT c.nombre, c.apellido, t.horario_inicio 
    FROM reservas r 
    JOIN turnos t ON r.turno_id = t.id 
    JOIN clientes c ON r.cliente_id = c.id 
    WHERE r.fecha = CURDATE() AND t.gimnasio_id = $gimnasio_id
");


$ingresos_clientes = $conexion->query("
    SELECT c.nombre, c.apellido, a.hora 
    FROM asistencias_clientes a 
    JOIN clientes c ON a.cliente_id = c.id 
    WHERE a.fecha = CURDATE() AND a.gimnasio_id = $gimnasio_id 
    ORDER BY a.hora DESC LIMIT 5
");

$ingresos_profesores = $conexion->query("
    SELECT p.nombre, p.apellido, a.hora_ingreso 
    FROM asistencias_profesor a 
    JOIN profesores p ON a.profesor_id = p.id 
    WHERE a.fecha = CURDATE() AND a.gimnasio_id = $gimnasio_id 
    ORDER BY a.hora_ingreso DESC LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel General - <?= htmlspecialchars($nombre_gym) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
    .grid { display: flex; flex-wrap: wrap; gap: 20px; }
    .box { background: #111; padding: 15px; border-radius: 10px; flex: 1 1 300px; }
    h1, h2 { color: gold; margin-top: 0; }
    ul { padding-left: 20px; }
    .monto { font-size: 24px; color: lime; }
    .encabezado { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .logo-gym { max-height: 60px; border-radius: 8px; background: white; padding: 5px; }
  </style>
</head>
<body>

<div class="encabezado">
    <div>
        <h1>🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
        <p>📅 Vencimiento del sistema: <strong style="color:orange;"><?= date('d/m/Y', strtotime($fecha_venc)) ?></strong></p>
    </div>
    <?php if ($logo): ?>
        <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="logo-gym">
    <?php endif; ?>
</div>

<div class="grid">
  <div class="box"><h2>💰 Ingresos del Día</h2><div class="monto">$<?= number_format($pagos_dia + $ventas_dia, 2) ?></div></div>
  <div class="box"><h2>📆 Ingresos del Mes</h2><div class="monto">$<?= number_format($pagos_mes + $ventas_mes, 2) ?></div></div>

  <div class="box"><h2>🎂 Próximos Cumpleaños</h2><ul>
    <?php while($c = $cumples->fetch_assoc()): ?>
      <li><?= $c['apellido'] . ', ' . $c['nombre'] ?> (<?= date('d/m', strtotime($c['fecha_nacimiento'])) ?>)</li>
    <?php endwhile; ?>
  </ul></div>

  <div class="box"><h2>📅 Vencimientos</h2><ul>
    <?php while($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['apellido'] . ', ' . $v['nombre'] ?> (<?= date('d/m', strtotime($v['fecha_vencimiento'])) ?>)</li>
    <?php endwhile; ?>
  </ul></div>
  <div class="box"><h2>📋 Reservas de Hoy</h2><ul>
    <?php if ($reservas->num_rows > 0): ?>
      <?php while($r = $reservas->fetch_assoc()): ?>
        <li><?= $r['apellido'] . ', ' . $r['nombre'] ?> - <?= $r['horario_inicio'] ?></li>
      <?php endwhile; ?>
    <?php else: ?>
        <li>Sin reservas para hoy.</li>
    <?php endif; ?>
  </ul></div>


  <div class="box"><h2>👤 Ingresos Clientes</h2><ul>
    <?php while($i = $ingresos_clientes->fetch_assoc()): ?>
      <li><?= $i['apellido'] . ', ' . $i['nombre'] ?> - <?= $i['hora'] ?></li>
    <?php endwhile; ?>
  </ul></div>

  <div class="box"><h2>👨‍🏫 Ingresos Profesores</h2><ul>
    <?php while($p = $ingresos_profesores->fetch_assoc()): ?>
      <li><?= $p['apellido'] . ', ' . $p['nombre'] ?> - <?= $p['hora_ingreso'] ?></li>
    <?php endwhile; ?>
  </ul></div>
</div>
<?php if ($r && isset($r['apellido'], $r['nombre'], $r['horario_inicio'])): ?>
  <li><?= $r['apellido'] . ', ' . $r['nombre'] ?> - <?= $r['horario_inicio'] ?></li>
<?php endif; ?>

</body>
</html>
