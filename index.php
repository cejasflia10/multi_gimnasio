<?php
if (session_status()===PHP_SESSION_NONE) session_start();
include 'menu.php';
include 'conexion.php';
include 'funciones.php';

// gimnasio nombre traído con login:
$gymNombre = $_SESSION['gimnasio_nombre'] ?? 'Mi Gimnasio';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$usuario = $_SESSION['usuario'] ?? 'Usuario';

// Cálculos datos:
$pagos_dia = obtenerMonto($conexion,'pagos','fecha',$gimnasio_id,'DIA');
$pagos_mes = obtenerMonto($conexion,'pagos','fecha',$gimnasio_id,'MES');
$ventas_dia = obtenerMonto($conexion,'ventas','fecha',$gimnasio_id,'DIA');
$ventas_mes = obtenerMonto($conexion,'ventas','fecha',$gimnasio_id,'MES');
$clientesHoy = obtenerAsistenciasClientes($conexion,$gimnasio_id);
$cumples = obtenerCumpleanios($conexion,$gimnasio_id);
$vencimientos = obtenerVencimientos($conexion,$gimnasio_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel - <?= htmlspecialchars($gymNombre) ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body{ background:#111; color:gold; font-family:Arial; margin:0; padding:20px; margin-left:260px; }
    h1,h2{text-align:center;}
    .cuadro{ background:#222; margin:15px auto; padding:15px; border-radius:8px; width:90%; }
    table{ width:100%; border-collapse:collapse; margin-top:10px; }
    th,td{ padding:6px; border-bottom:1px solid #444; color:#fff; }
    .rojo{ color:red; }
    @media(max-width:768px){
      body{ margin-left:0; padding-bottom:70px; }
      .sidebar{ display:none!important; }
    }
    .menu-inferior{ display:none; }
    @media(max-width:768px){
      .menu-inferior{ display:flex; justify-content:space-around; background:#111; position:fixed;
        bottom:0; width:100%; border-top:1px solid gold; padding:6px 0; z-index:999; }
      .menu-inferior a{ flex:1; text-align:center; font-size:12px; color:gold; text-decoration:none; }
      .menu-inferior i{ font-size:18px; display:block; }
    }
  </style>
</head>
<body>
  <h1><?= date('H')<12?'¡Buenos días':'¡Buenas tardes' ?>, <?= htmlspecialchars($usuario) ?>!</h1>
  <h2><?= htmlspecialchars($gymNombre) ?></h2>

  <div class="cuadro">
    <h2>💰 Totales</h2>
    <p>Pagos hoy: $<?= $pagos_dia ?></p>
    <p>Pagos mes: $<?= $pagos_mes ?></p>
    <p>Ventas hoy: $<?= $ventas_dia ?></p>
    <p>Ventas mes: $<?= $ventas_mes ?></p>
  </div>

  <div class="cuadro">
    <h2>👥 Ingresos Hoy - Clientes</h2>
    <table>
      <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Hora</th><th>Vto.</th></tr>
      <?php while($c=$clientesHoy->fetch_assoc()): ?>
        <tr>
          <td><?= $c['nombre'].' '.$c['apellido'] ?></td>
          <td><?= $c['dni'] ?></td>
          <td><?= $c['disciplina'] ?></td>
          <td><?= $c['hora'] ?></td>
          <td class="<?= ($c['fecha_vencimiento']<date('Y-m-d'))?'rojo':'' ?>">
            <?= $c['fecha_vencimiento'] ?: '---' ?>
          </td>
        </tr>
      <?php endwhile; ?>
    </table>
  </div>

  <div class="cuadro">
    <h2>🎂 Cumpleaños del Mes</h2>
    <ul>
      <?php while($cf=$cumples->fetch_assoc()): ?>
        <li><?= $cf['nombre'].' '.$cf['apellido'].' - '.date('d/m',strtotime($cf['fecha_nacimiento'])) ?></li>
      <?php endwhile; ?>
    </ul>
  </div>

  <div class="cuadro">
    <h2>📅 Vencimientos (próx. 10 días)</h2>
    <ul>
      <?php while($v=$vencimientos->fetch_assoc()): ?>
        <li><?= $v['nombre'].' '.$v['apellido'].' - '.date('d/m/Y',strtotime($v['fecha_vencimiento'])) ?></li>
      <?php endwhile; ?>
    </ul>
  </div>

  <!-- Menú inferior móvil -->
  <div class="menu-inferior">
    <a href="index.php"><i class="fas fa-home"></i>Inicio</a>
    <a href="ver_clientes.php"><i class="fas fa-users"></i>Clientes</a>
    <a href="ver_membresias.php"><i class="fas fa-id-card-alt"></i>Membresías</a>
    <a href="scanner_qr.php"><i class="fas fa-qrcode"></i>QR</a>
    <a href="ver_asistencias.php"><i class="fas fa-calendar-check"></i>Asistencias</a>
  </div>
</body>
</html>
