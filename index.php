<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

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
    SELECT r.dia_semana, r.hora_inicio, td.hora_fin,
           c.nombre, c.apellido,
           CONCAT(p.apellido, ' ', p.nombre) AS profesor
    FROM reservas_clientes r
    JOIN clientes c ON r.cliente_id = c.id
    JOIN profesores p ON r.profesor_id = p.id
    JOIN turnos_disponibles td ON r.turno_id = td.id
    WHERE r.fecha_reserva = CURDATE()
      AND r.gimnasio_id = $gimnasio_id
    ORDER BY r.hora_inicio
");

$alumnos_hoy = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora 
    FROM asistencias a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = CURDATE() AND c.gimnasio_id = $gimnasio_id
    ORDER BY a.hora
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
    .box, .cuadro { background: #111; padding: 15px; border-radius: 10px; flex: 1 1 300px; }
    h1, h2, h3 { color: gold; margin-top: 0; }
    ul { padding-left: 20px; }
    .monto { font-size: 24px; color: lime; }
    .encabezado { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
    .logo-gym {
        max-height: 60px;
        max-width: 180px;
        border-radius: 8px;
        background: white;
        padding: 5px;
        object-fit: contain;
    }
    .btn-logo {
        margin-top: 8px;
        padding: 5px 10px;
        background: gold;
        color: black;
        font-weight: bold;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
  </style>
</head>
<body>

<div class="encabezado">
    <div style="display:flex; align-items:center; gap:15px;">
        <?php if ($logo): ?>
            <div style="position:relative; display:flex; flex-direction:column; align-items:flex-start;">
                <img src="<?= htmlspecialchars($logo) ?>?v=<?= time() ?>" alt="Logo" class="logo-gym" id="logoGym">

                <?php if ($gimnasio_id > 0): ?>
                    <button onclick="document.getElementById('formLogo').style.display='block'" class="btn-logo-mini">🖋</button>
                    <form method="POST" action="subir_logo.php" enctype="multipart/form-data" id="formLogo" style="display:none; margin-top:3px;">
                        <input type="file" name="logo" accept="image/*" required onchange="this.form.submit()">
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div>
            <h1>🏋️ <?= htmlspecialchars($nombre_gym) ?></h1>
            <p>📅 Vencimiento del sistema: <strong style="color:orange;"><?= date('d/m/Y', strtotime($fecha_venc)) ?></strong></p>
        </div>
    </div>
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
      <?php while ($r = $reservas->fetch_assoc()): ?>
        <li>
          🕒 <?= $r['hora_inicio'] ?> a <?= $r['hora_fin'] ?><br>
          👤 <?= $r['apellido'] ?> <?= $r['nombre'] ?><br>
          👨‍🏫 <?= $r['profesor'] ?>
        </li>
      <?php endwhile; ?>
    <?php else: ?>
      <li>Sin reservas registradas para hoy.</li>
    <?php endif; ?>
  </ul></div>

  <div class="cuadro">
        <h3>🧍 Alumnos que ingresaron Hoy</h3>
        <?php if ($alumnos_hoy->num_rows > 0): ?>
            <ul>
                <?php while ($al = $alumnos_hoy->fetch_assoc()): ?>
                    <li><?= $al['apellido'] . ' ' . $al['nombre'] ?> - ⏰ <?= $al['hora'] ?></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <p style="color:gray;">No se registraron ingresos de alumnos hoy.</p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action="subir_logo.php"]');
    if (form) {
        form.addEventListener('submit', function() {
            const btn = form.querySelector('button');
            btn.textContent = "Subiendo...";
            btn.disabled = true;
        });
    }
});


</script>

</body>
</html>
