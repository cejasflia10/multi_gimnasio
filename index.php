<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'funciones.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$usuario = $_SESSION['usuario'] ?? '';
$rol = $_SESSION['rol'] ?? '';
$nombre_gimnasio = '';

// Obtener nombre del gimnasio
if ($gimnasio_id) {
    $res = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
    if ($fila = $res->fetch_assoc()) {
        $nombre_gimnasio = $fila['nombre'];
    }
}

$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Panel - <?= $nombre_gimnasio ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #111;
      color: gold;
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 20px;
      padding-left: 260px;
    }
    h1, h2 {
      text-align: center;
    }
    .cuadro {
      background: #222;
      padding: 20px;
      margin: 15px auto;
      border-radius: 10px;
      max-width: 1000px;
    }
    .cuadro h3 {
      margin-top: 0;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 10px;
    }
    th, td {
      padding: 10px;
      text-align: left;
      border-bottom: 1px solid #444;
      color: white;
    }
    ul {
      padding-left: 20px;
    }
    .rojo {
      color: red;
    }
    @media (max-width: 768px) {
      body {
        padding: 10px;
        padding-bottom: 80px;
      }
      .cuadro {
        width: 100%;
        margin-left: auto;
        margin-right: auto;
        padding: 15px;
      }
    }
    .menu-inferior {
      display: none;
    }
    @media (max-width: 768px) {
      .menu-inferior {
        display: flex;
        justify-content: space-around;
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        background: #000;
        border-top: 2px solid gold;
        padding: 8px 0;
        z-index: 9999;
      }
      .menu-inferior a {
        text-align: center;
        flex: 1;
        color: gold;
        font-size: 13px;
        text-decoration: none;
      }
      .menu-inferior i {
        display: block;
        font-size: 18px;
        margin-bottom: 4px;
      }
    }
  </style>
</head>
<body>

<?php include 'menu.php'; ?>

<h1>¡Buenos días, <?= strtolower($usuario) ?>!</h1>
<h2><?= $nombre_gimnasio ?></h2>

<div class="cuadro">
  <h3>💰 Totales</h3>
  <p>Pagos hoy: $<?= $pagos_dia ?></p>
  <p>Pagos mes: $<?= $pagos_mes ?></p>
  <p>Ventas hoy: $<?= $ventas_dia ?></p>
  <p>Ventas mes: $<?= $ventas_mes ?></p>
</div>

<div class="cuadro">
  <h3>👥 Ingresos Hoy - Clientes</h3>
  <table>
    <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Hora</th><th>Vto.</th></tr>
    <?php if ($asistencias_clientes->num_rows == 0): ?>
      <tr><td colspan="5">Sin ingresos hoy</td></tr>
    <?php else: ?>
      <?php while ($r = $asistencias_clientes->fetch_assoc()): ?>
        <tr>
          <td><?= $r['nombre'] . ' ' . $r['apellido'] ?></td>
          <td><?= $r['dni'] ?></td>
          <td><?= $r['disciplina'] ?></td>
          <td><?= $r['hora'] ?></td>
          <td class="<?= ($r['fecha_vencimiento'] < date('Y-m-d')) ? 'rojo' : '' ?>">
            <?= $r['fecha_vencimiento'] ?>
          </td>
        </tr>
      <?php endwhile; ?>
    <?php endif; ?>
  </table>
</div>

<div class="cuadro">
  <h3>🎂 Cumpleaños del Mes</h3>
  <ul>
    <?php while ($c = $cumpleanios->fetch_assoc()): ?>
      <li><?= $c['nombre'] . ' ' . $c['apellido'] ?> - <?= date('d/m', strtotime($c['fecha_nacimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>
</div>

<div class="cuadro">
  <h3>📅 Vencimientos Próximos (10 días)</h3>
  <ul>
    <?php while ($v = $vencimientos->fetch_assoc()): ?>
      <li><?= $v['nombre'] . ' ' . $v['apellido'] ?> - <?= date('d/m/Y', strtotime($v['fecha_vencimiento'])) ?></li>
    <?php endwhile; ?>
  </ul>
</div>

<!-- Menú inferior para celulares -->
<div class="menu-inferior">
  <a href="index.php"><i class="fas fa-home"></i>Inicio</a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i>Clientes</a>
  <a href="ver_membresias.php"><i class="fas fa-id-card-alt"></i>Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i>QR</a>
  <a href="ver_asistencia.php"><i class="fas fa-calendar-check"></i>Asistencias</a>
</div>

</body>
</html>
