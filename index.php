<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$rol = $_SESSION['rol'] ?? '';

include 'conexion.php';
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
            $columna = 'total';
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
    }
    $r2 = $conexion->query("
      SELECT c.nombre, c.apellido, m.fecha_vencimiento
      FROM clientes c
      JOIN membresias m ON c.id = m.cliente_id
      WHERE c.gimnasio_id = $gimnasio_id
      ORDER BY m.fecha_vencimiento ASC
      LIMIT 1
    ");
    if ($c = $r2->fetch_assoc()) {
        $cliente_activo = "{$c['nombre']} {$c['apellido']} – Vence: " . date('d/m/Y', strtotime($c['fecha_vencimiento']));
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
<strong>Próximo vencimiento del gimnasio:</strong>
<?= $proximo_vencimiento ? date('d/m/Y', strtotime($proximo_vencimiento)) : 'No disponible' ?><br>
    <?= $cliente_activo ?>
  </div>
</header>

<nav>
<div class="menu-pc">
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-users"></i> Clientes</span>
    <div class="dropdown-content">
      <a href="agregar_cliente.php">Agregar</a>
      <a href="ver_clientes.php">Ver</a>
      <a href="disciplinas.php">Disciplinas</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-id-card"></i> Membresías</span>
    <div class="dropdown-content">
      <a href="nueva_membresia.php">Nueva</a>
      <a href="ver_membresias.php">Ver</a>
      <a href="planes.php">Planes</a>
      <a href="adicionales.php">Adicionales</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-calendar-check"></i> Asistencias</span>
    <div class="dropdown-content">
      <a href="registrar_asistencia.php">Registrar</a>
      <a href="ver_asistencias.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-qrcode"></i> QR</span>
    <div class="dropdown-content">
      <a href="scanner_qr.php">Escanear</a>
      <a href="generar_qr.php">Generar</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-user-tie"></i> Profesores</span>
    <div class="dropdown-content">
      <a href="agregar_profesor.php">Agregar</a>
      <a href="ver_profesores.php">Ver</a>
      <a href="ver_pagos_profesor.php">Pagos</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-shopping-cart"></i> Ventas</span>
    <div class="dropdown-content">
      <a href="ventas_protecciones.php">Protecciones</a>
      <a href="ventas_indumentaria.php">Indumentaria</a>
      <a href="ventas_suplementos.php">Suplementos</a>
      <a href="ver_ventas.php">Ver Todas</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-dumbbell"></i> Gimnasios</span>
    <div class="dropdown-content">
      <a href="agregar_gimnasio.php">Agregar</a>
      <a href="ver_gimnasios.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-user-cog"></i> Usuarios</span>
    <div class="dropdown-content">
      <a href="agregar_usuario.php">Agregar</a>
      <a href="ver_usuarios.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-cogs"></i> Configuraciones</span>
    <div class="dropdown-content">
      <a href="configurar_planes.php">Planes</a>
      <a href="configurar_accesos.php">Accesos</a>
    </div>
  </div>
  <div class="dropdown"><a href="cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></div>
</div>

<div class="container">
  
<div style="display: flex; flex-wrap: wrap; justify-content: space-around; gap: 20px; margin-top: 20px;">
  <div style="flex: 1; min-width: 200px; background-color: #1f1f1f; padding: 20px; border-radius: 10px; text-align: center; color: gold;">
    <h3>Pagos del Día</h3>
    <p style="font-size: 22px;">
      $<?= number_format(obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA'), 0, ',', '.') ?>
    </p>
  </div>
  <div style="flex: 1; min-width: 200px; background-color: #1f1f1f; padding: 20px; border-radius: 10px; text-align: center; color: gold;">
    <h3>Pagos del Mes</h3>
    <p style="font-size: 22px;">
      $<?= number_format(obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES'), 0, ',', '.') ?>
    </p>
  </div>
  <div style="flex: 1; min-width: 200px; background-color: #1f1f1f; padding: 20px; border-radius: 10px; text-align: center; color: gold;">
    <h3>Ventas del Mes</h3>
    <p style="font-size: 22px;">
      $<?= number_format(obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES'), 0, ',', '.') ?>
    </p>
  </div>
  <div style="flex: 1; min-width: 200px; background-color: #1f1f1f; padding: 20px; border-radius: 10px; text-align: center; color: gold;">
    <h3>Total de Ventas</h3>
    <p style="font-size: 22px;">
      $<?= number_format(obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA'), 0, ',', '.') ?>
    </p>
  </div>
</div>
<div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 20px;">
  <div style="flex: 1; background:#1f1f1f; padding: 20px; border-radius: 12px;">
    <h3 style="color: gold;">Próximos Vencimientos</h3>
    <ul style="color: #fff; padding-left: 20px;">
      <?php
      $query_venc = "
        SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento
        FROM clientes
        JOIN membresias ON clientes.id = membresias.cliente_id
        WHERE clientes.gimnasio_id = $gimnasio_id
          AND membresias.fecha_vencimiento >= CURDATE()
        ORDER BY membresias.fecha_vencimiento ASC
        LIMIT 5
      ";
      $result_venc = $conexion->query($query_venc);
      if ($result_venc && $result_venc->num_rows > 0) {
          while ($v = $result_venc->fetch_assoc()) {
              echo "<li>{$v['nombre']} {$v['apellido']} – " . date('d/m/Y', strtotime($v['fecha_vencimiento'])) . "</li>";
          }
      } else {
          echo "<li>No hay vencimientos próximos.</li>";
      }
      ?>
    </ul>
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


<div class="card">
    <h3>Ingresos del Día</h3>
    <?php
    $hoy = date('Y-m-d');
    $query = "
      SELECT c.nombre, c.apellido, a.hora
      FROM asistencias a
      JOIN clientes c ON a.cliente_id = c.id
      WHERE a.fecha = '$hoy' AND c.gimnasio_id = $gimnasio_id
      ORDER BY a.hora DESC
    ";
    $res = $conexion->query($query);
    if ($res && $res->num_rows > 0): ?>
      <ul style="list-style:none; padding:0; color:#fff;">
        <?php while ($fila = $res->fetch_assoc()): ?>
          <li><?= $fila['nombre'] . ' ' . $fila['apellido'] . ' – ' . date('H:i', strtotime($fila['hora'])) ?></li>
        <?php endwhile; ?>
      </ul>
    <?php else: ?>
      <p>No se registraron ingresos hoy.</p>
    <?php endif; ?>
  </div>
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

<div class="card">
  <h3>Estadísticas por Disciplina (últimos 7 días)</h3>
  <?php
  $sql = "SELECT d.nombre AS disciplina, COUNT(*) AS total
          FROM asistencias a
          JOIN clientes c ON a.cliente_id = c.id
          JOIN disciplinas d ON c.disciplina_id = d.id
          WHERE c.gimnasio_id = $gimnasio_id
            AND a.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
          GROUP BY d.nombre";
  $res = $conexion->query($sql);
  if ($res && $res->num_rows > 0): ?>
    <ul style="list-style:none; padding:0;">
      <?php while ($fila = $res->fetch_assoc()): ?>
        <li><?= $fila['disciplina'] ?>: 
          <div style="background:#444; width:100%; height:20px; margin:5px 0; border-radius:5px;">
            <div style="background:gold; width:<?= min(100, $fila['total'] * 10) ?>%; height:100%; border-radius:5px;"></div>
          </div>
        </li>
      <?php endwhile; ?>
    </ul>
  <?php else: ?>
    <p>No hay datos disponibles.</p>
  <?php endif; ?>
</div>

</body>
<div class="menu-pc">
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-users"></i> Clientes</span>
    <div class="dropdown-content">
      <a href="agregar_cliente.php">Agregar</a>
      <a href="ver_clientes.php">Ver</a>
      <a href="disciplinas.php">Disciplinas</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-id-card"></i> Membresías</span>
    <div class="dropdown-content">
      <a href="nueva_membresia.php">Nueva</a>
      <a href="ver_membresias.php">Ver</a>
      <a href="planes.php">Planes</a>
      <a href="adicionales.php">Adicionales</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-calendar-check"></i> Asistencias</span>
    <div class="dropdown-content">
      <a href="registrar_asistencia.php">Registrar</a>
      <a href="ver_asistencias.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-qrcode"></i> QR</span>
    <div class="dropdown-content">
      <a href="scanner_qr.php">Escanear</a>
      <a href="generar_qr.php">Generar</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-user-tie"></i> Profesores</span>
    <div class="dropdown-content">
      <a href="agregar_profesor.php">Agregar</a>
      <a href="ver_profesores.php">Ver</a>
      <a href="ver_pagos_profesor.php">Pagos</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-shopping-cart"></i> Ventas</span>
    <div class="dropdown-content">
      <a href="ventas_protecciones.php">Protecciones</a>
      <a href="ventas_indumentaria.php">Indumentaria</a>
      <a href="ventas_suplementos.php">Suplementos</a>
      <a href="ver_ventas.php">Ver Todas</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-dumbbell"></i> Gimnasios</span>
    <div class="dropdown-content">
      <a href="agregar_gimnasio.php">Agregar</a>
      <a href="ver_gimnasios.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-user-cog"></i> Usuarios</span>
    <div class="dropdown-content">
      <a href="agregar_usuario.php">Agregar</a>
      <a href="ver_usuarios.php">Ver</a>
    </div>
  </div>
  <div class="dropdown"><span class="dropbtn"><i class="fas fa-cogs"></i> Configuraciones</span>
    <div class="dropdown-content">
      <a href="configurar_planes.php">Planes</a>
      <a href="configurar_accesos.php">Accesos</a>
    </div>
  </div>
  <div class="dropdown"><a href="cerrar_sesion.php"><i class="fas fa-sign-out-alt"></i> Cerrar sesión</a></div>
</div>

<!-- CONTENIDO DEL PANEL -->
<div class="contenido">
  <h2>Bienvenido al Panel</h2>
  <!-- Acá podés incluir las tarjetas o estadísticas del panel -->
</div>

<!-- FOOTER APP SOLO PARA CELULARES -->
<div class="mobile-footer">
  <a href="clientes.php"><i class="fas fa-users"></i><small>Clientes</small></a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><small>Membresías</small></a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><small>QR</small></a>
  <a href="ventas.php"><i class="fas fa-shopping-cart"></i><small>Ventas</small></a>
</div>
</body>
</html>
