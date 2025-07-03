<?php
session_start();
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// Aquí es donde pegás las llamadas
$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$total_ventas = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    switch ($tabla) {
        case 'ventas':
            $columna = 'monto_total';
            break;
        case 'pagos':
            $columna = 'monto';
            break;
        case 'membresias':
            $columna = 'total_pagado';
            break;
        default:
            $columna = 'monto';
    }

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $res = $conexion->query($query);
    if ($res && $fila = $res->fetch_assoc()) {
        return $fila['total'] ?? 0;
    }
    return 0;
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$gimnasio_nombre = 'Gimnasio';
$proximo_vencimiento = '';
$cliente_activo = '';

if ($gimnasio_id) {
    $r = $conexion->query("SELECT nombre, fecha_vencimiento FROM gimnasios WHERE id=$gimnasio_id");
    if ($f = $r->fetch_assoc()) {
        $gimnasio_nombre = $f['nombre'];
        $proximo_vencimiento = $f['fecha_vencimiento'];
    }}
    $r2 = $conexion->query("
      SELECT c.nombre, c.apellido, m.fecha_vencimiento
      FROM clientes c
      JOIN membresias m ON c.id = m.cliente_id
      WHERE c.gimnasio_id = $gimnasio_id
      ORDER BY m.fecha_vencimiento ASC
      LIMIT 1
    ");
    if ($c = $r2->fetch_assoc()) {
     if (!empty($fila['fecha_nacimiento'])) {
    $fecha_nac = new DateTime($fila['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;
}} else {
    $edad = 'No registrada';
}
// Clientes con deuda (total negativo en la membresía más reciente)
$deudas_q = $conexion->query("
    SELECT c.id, c.nombre, c.apellido, m.total, m.fecha_inicio
    FROM membresias m
    JOIN clientes c ON m.cliente_id = c.id
    WHERE m.total < 0
      AND m.gimnasio_id = $gimnasio_id
    ORDER BY m.fecha_inicio DESC
    LIMIT 15
");

$clientes_deudores = [];
if ($deudas_q && $deudas_q->num_rows > 0) {
    while ($d = $deudas_q->fetch_assoc()) {
        $clientes_deudores[] = $d;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel Principal</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
 body { background:#111; color:gold; font-family:Arial,sans-serif; margin:0; padding-bottom:60px }
 header{display:flex;justify-content:space-between;align-items:center;background:#1a1a1a;padding:15px 20px}
 header h1{margin:0;font-size:22px;color:gold}
 .info-header{font-size:14px;color:#ccc;text-align:right}
 nav{display:flex;flex-wrap:wrap;justify-content:center;background:#222;position:relative;z-index:10}
 nav .dropdown{position:relative}
 nav a, nav .dropbtn{color:gold;padding:12px 20px;text-decoration:none;display:block;cursor:pointer}
 nav .dropdown-content{display:none;position:absolute;background:#333;min-width:180px;z-index:1000}
 nav .dropdown-content a{color:gold;padding:10px;display:block}
 nav .dropdown:hover .dropdown-content{display:block}
 .container{padding:30px 10px 5px;position:relative;z-index:1}
 .card{background:#1f1f1f;padding:20px;margin:20px;border-radius:12px;box-shadow:0 0 8px #000}
 footer{background:#222;color:gold;padding:10px;text-align:center;font-size:14px}
 .bottom-bar{display:none}
 @media(max-width:768px){
   nav{display:none}
   .bottom-bar{display:flex;justify-content:space-around;background:#222;padding:10px 0;position:fixed;bottom:0;width:100%;z-index:999}
   .bottom-bar a{color:gold;text-decoration:none;text-align:center;font-size:13px}
 }
</style>
</head>
<body>

<header>
  
  <h1><?= $gimnasio_nombre ?></h1>
  <div class="info-header">
<?php if (!empty($proximo_vencimiento)): ?>
  <strong>Próximo vencimiento del gimnasio:</strong> <?= date('d/m/Y', strtotime($proximo_vencimiento)) ?><br>
<?php else: ?>
  <strong>Próximo vencimiento del gimnasio:</strong> No disponible<br>

  <?php endif; ?>
<?= $cliente_activo ?>
    <?= $cliente_activo ?>
  </div>
</header>

<div class="container">
  <div class="panel">
    <h3>💰 Clientes con Deuda</h3>
    <ul>
        <?php if (!empty($clientes_deudores)): ?>
            <?php foreach ($clientes_deudores as $cli): ?>
                <li>
                    <?= $cli['apellido'] . ' ' . $cli['nombre'] ?> – 
                    💸 $<?= number_format(abs($cli['total']), 2, ',', '.') ?>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>Todos los clientes están al día.</li>
        <?php endif; ?>
    </ul>
</div>
<!-- Panel superior de estadísticas -->
<div style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: center; margin-top: 20px;">

    <div style="background-color: #222; color: gold; padding: 20px; border-radius: 10px; width: 200px; text-align: center;">
        <h3>Pagos del Día</h3>
        <p>$<?= number_format($pagos_dia, 0, ',', '.') ?></p>
    </div>

    <div style="background-color: #222; color: gold; padding: 20px; border-radius: 10px; width: 200px; text-align: center;">
        <h3>Pagos del Mes</h3>
        <p>$<?= number_format($pagos_mes, 0, ',', '.') ?></p>
    </div>

    <div style="background-color: #222; color: gold; padding: 20px; border-radius: 10px; width: 200px; text-align: center;">
        <h3>Ventas del Mes</h3>
        <p>$<?= number_format($ventas_mes, 0, ',', '.') ?></p>
    </div>

    <div style="background-color: #222; color: gold; padding: 20px; border-radius: 10px; width: 200px; text-align: center;">
        <h3>Total de Ventas</h3>
        <p>$<?= number_format($total_ventas, 0, ',', '.') ?></p>
    </div>

</div>

<div style="flex: 1; background:#1f1f1f; padding: 20px; border-radius: 12px;">
    <h3 style="color: gold;">Próximos Cumpleaños</h3>
    <ul style="color: #fff; padding-left: 20px;">
      <?php
      $query_cump = "
        SELECT nombre, apellido, fecha_nacimiento
        FROM clientes
        WHERE gimnasio_id = $gimnasio_id
          AND MONTH(fecha_nacimiento) = MONTH(CURDATE())
        ORDER BY DAY(fecha_nacimiento)
        LIMIT 5
      ";
      $result_cump = $conexion->query($query_cump);
      if ($result_cump && $result_cump->num_rows > 0) {
          while ($c = $result_cump->fetch_assoc()) {
              echo "<li>{$c['nombre']} {$c['apellido']} – " . date('d/m', strtotime($c['fecha_nacimiento'])) . "</li>";
          }
      } else {
          echo "<li>No hay cumpleaños este mes.</li>";
      }
      ?>
    </ul>
  </div>
</div>


<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 30px;">
    <!-- INGRESOS DEL DÍA -->
    <div style="flex: 1; min-width: 300px; background: #111; padding: 20px; color: gold; border: 1px solid #333;">
        <h3>📥 Ingresos del día</h3>
        <?php
        $ingresos = $conexion->query("
            SELECT c.apellido, c.nombre, a.hora
            FROM asistencias_clientes a
            JOIN clientes c ON a.cliente_id = c.id
            WHERE a.fecha = CURDATE() AND a.gimnasio_id = $gimnasio_id
            ORDER BY a.hora DESC
        ");

        if ($ingresos->num_rows > 0) {
            echo "<ul style='list-style: none; padding: 0;'>";
            while ($i = $ingresos->fetch_assoc()) {
                echo "<li>🕒 {$i['apellido']} {$i['nombre']} - {$i['hora']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: gray;'>Aún no se registraron ingresos hoy.</p>";
        }
        ?>
    </div>

    <!-- PRÓXIMOS VENCIMIENTOS -->
    <div style="flex: 1; min-width: 300px; background: #111; padding: 20px; color: gold; border: 1px solid #333;">
        <h3>📅 Próximos vencimientos</h3>
        <?php
        $vencimientos = $conexion->query("
            SELECT c.apellido, c.nombre, m.fecha_vencimiento
            FROM membresias m
            JOIN clientes c ON m.cliente_id = c.id
            WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 5 DAY)
              AND m.gimnasio_id = $gimnasio_id
            ORDER BY m.fecha_vencimiento ASC
        ");

        if ($vencimientos->num_rows > 0) {
            echo "<ul style='list-style: none; padding: 0;'>";
            while ($v = $vencimientos->fetch_assoc()) {
                echo "<li>📌 {$v['apellido']} {$v['nombre']} - vence el {$v['fecha_vencimiento']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: gray;'>No hay vencimientos en los próximos días.</p>";
        }
        ?>
    </div>
</div>


    <!-- Reservas del Día -->
    <div style="flex:1; min-width:300px; background:#222; padding:15px; border-radius:10px;">
        <h3 style="color:gold;">Reservas del Día</h3>
        <?php
        $reservas_q = $conexion->query("
            SELECT r.dia_semana AS dia, r.hora_inicio, td.hora_fin,
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

        if ($reservas_q->num_rows > 0) {
            while ($res = $reservas_q->fetch_assoc()) {
                echo "<p style='color:white; margin:5px 0;'>
                    🕒 {$res['hora_inicio']} a {$res['hora_fin']}<br>
                    👤 {$res['apellido']} {$res['nombre']}<br>
                    👨‍🏫 Prof. {$res['profesor']}
                </p>";
            }
        } else {
            echo "<p style='color:gray;'>No hay reservas registradas para hoy.</p>";
        }
        ?>
    </div>
</div>

    </ul>
  </div>
<div class="bar-section">
    <div class="bar-title">Estadísticas por Disciplina</div>
    <div class="bar-row">
      <div class="bar"><div class="bar-inner-yellow" style="width: 70%;"></div></div>
<div class="bar-section">
    <div class="bar-title">Ventas Mensuales</div>
    <div class="bar-row">
      <div class="bar"><div class="bar-inner-yellow" style="width: 80%;"></div></div>
<footer>Panel de administración – <?= $gimnasio_nombre ?></footer>

<div class="bottom-bar">
  <a href="index.php"><i class="fas fa-home"></i><br>Inicio</a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><br><i class="fas fa-users"></i> Clientes</a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><br><i class="fas fa-id-card"></i> Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><br><i class="fas fa-qrcode"></i> QR</a>
  <a href="registrar_asistencia.php"><i class="fas fa-calendar-check"></i><br><i class="fas fa-calendar-check"></i> Asistencias</a>
  <a href="ver_ventas.php"><i class="fas fa-shopping-cart"></i><br><i class="fas fa-shopping-cart"></i> Ventas</a>
</div>

</body>
</html>
