<?php
// --- ninguno de estos archivos debe tener salida previa al session_start():
session_start();
include 'menu.php';
include 'conexion.php';
include 'funciones.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$usuario     = $_SESSION['usuario'] ?? 'Usuario';

// montos
$pagos_dia    = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes    = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia   = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes   = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

// datos
$asistencias  = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$cumples      = obtenerCumpleanios($conexion, $gimnasio_id);
$vencs        = obtenerVencimientos($conexion, $gimnasio_id);
$grafDiscs    = obtenerDisciplinas($conexion, $gimnasio_id);
$grafPagos    = obtenerPagosPorMetodo($conexion, $gimnasio_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel de Control</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * { box-sizing: border-box; margin:0; padding:0; }
    body { background:#111; color:gold; font-family:Arial,sans-serif; min-height:100vh; }
    .contenido { padding:20px; margin-left:260px; margin-bottom:60px; max-width:1200px; }
    h2 { margin:20px 0; text-align:center; }

    table { width:100%; border-collapse:collapse; margin-bottom:20px; }
    th,td { padding:8px; border:1px solid #333; color:#fff; text-align:left; }
    th { background:#222; }

    .panel-cards { display:flex; flex-wrap:wrap; gap:15px; justify-content:center; margin-bottom:20px; }
    .card { background:#222; padding:15px; border-radius:8px; width:200px; text-align:center; }

    .graficos { display:flex; flex-wrap:wrap; justify-content:center; gap:20px; margin-bottom:20px; }
    .grafico-box { background:#222; padding:10px; border-radius:8px; width:220px; }

    .mobile-footer { display:none; }
    @media(max-width:768px){
      .contenido { margin-left:0; padding:10px; }
      .mobile-footer { display:flex; position:fixed; bottom:0; left:0; width:100%; background:#000; border-top:2px solid gold;
                       justify-content:space-around; padding:6px 0; z-index:999; }
      .mobile-footer a { color:gold; font-size:12px; text-decoration:none; flex:1; text-align:center; }
      .mobile-footer i { font-size:18px; margin-bottom:2px; }
    }
  </style>
</head>
<body>

<div class="contenido">
  <h2><?= date('H')<12 ? '¡Buenos días' : '¡Buenas tardes'; ?>, <?= htmlspecialchars($usuario) ?>!</h2>
  <h2><?= 'Mi Gimnasio' ?></h2>

  <!-- Totales -->
  <div class="card" style="margin:auto; max-width:400px; background:#222; padding:15px; border-radius:8px;">
    <h3><i class="fas fa-money-bill-wave"></i> Totales</h3>
    <p>Pagos hoy: $<?= number_format($pagos_dia,2,',','.') ?></p>
    <p>Pagos mes: $<?= number_format($pagos_mes,2,',','.') ?></p>
    <p>Ventas hoy: $<?= number_format($ventas_dia,2,',','.') ?></p>
    <p>Ventas mes: $<?= number_format($ventas_mes,2,',','.') ?></p>
  </div>

  <!-- Ingresos Clientes hoy -->
  <h2><i class="fas fa-users"></i> Ingresos Hoy - Clientes</h2>
  <table>
    <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Hora</th><th>Vto</th></tr>
    <?php if($asistencias->num_rows===0): ?>
      <tr><td colspan="5" style="color:orange;text-align:center;">Sin ingresos hoy.</td></tr>
    <?php else: while($r=$asistencias->fetch_assoc()): ?>
      <tr>
        <td><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></td>
        <td><?= htmlspecialchars($r['dni']) ?></td>
        <td><?= htmlspecialchars($r['disciplina']) ?></td>
        <td><?= htmlspecialchars($r['hora']) ?></td>
        <td><?= htmlspecialchars($r['fecha_vencimiento'] ?? '---') ?></td>
      </tr>
    <?php endwhile; endif;?>
  </table>

  <!-- Vencimientos -->
  <h2><i class="fas fa-calendar-alt"></i> Próximos Vencimientos</h2>
  <ul>
    <?php while($v=$vencs->fetch_assoc()): ?>
      <li><?= htmlspecialchars($v['nombre'].' '.$v['apellido']) ?> - <?= htmlspecialchars($v['fecha_vencimiento']) ?></li>
    <?php endwhile; ?>
  </ul>

  <!-- Estadísticas -->
  <h2><i class="fas fa-chart-bar"></i> Estadísticas Visuales</h2>
  <div class="graficos">
    <div class="grafico-box"><canvas id="chart1"></canvas></div>
    <div class="grafico-box"><canvas id="chart2"></canvas></div>
  </div>

  <!-- Cumpleaños -->
  <h2><i class="fas fa-birthday-cake"></i> Cumpleaños del Mes</h2>
  <ul>
    <?php while($c=$cumples->fetch_assoc()):?>
      <li><?= htmlspecialchars($c['nombre'].' '.$c['apellido']) ?> - <?= substr($c['fecha_nacimiento'],5) ?></li>
    <?php endwhile;?>
  </ul>

</div>

<!-- menú inferior móvil -->
<div class="mobile-footer">
  <a href="index.php"><i class="fas fa-home"></i><span>Inicio</span></a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><span>Clientes</span></a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><span>Membresías</span></a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><span>QR</span></a>
  <a href="ver_asistencias.php"><i class="fas fa-calendar-check"></i><span>Asist.</span></a>
</div>

<script>
  // gráfico barras disciplinas
  const data1 = { labels: [<?php while($d=$grafDiscs->fetch_assoc()) echo "'{$d['disciplina']}',";?>],
                  datasets:[{ data:[<?php mysqli_data_seek($grafDiscs,0); while($d=$grafDiscs->fetch_assoc()) echo "{$d['cantidad']},";?>],
                               backgroundColor:['#f1c40f','#e67e22','#ecf0f1','#2ecc71','#3498db'], borderRadius:4, barThickness:30 }]};
  new Chart(document.getElementById('chart1'),{ type:'bar', data:data1, options:{ responsive:true, plugins:{legend:{display:false}}}});

  // gráfico pie pagos
  const data2 = { labels:[<?php while($p=$grafPagos->fetch_assoc()) echo "'{$p['metodo_pago']}',";?>],
                  datasets:[{ data:[<?php mysqli_data_seek($grafPagos,0); while($p=$grafPagos->fetch_assoc()) echo "{$p['cantidad']},";?>],
                               backgroundColor:['gold','orange','white','gray','red'] }]};
  new Chart(document.getElementById('chart2'),{ type:'pie', data:data2, options:{ responsive:true, plugins:{legend:{position:'bottom'}}}});
</script>

</body>
</html>
