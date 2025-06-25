<?php
session_start();
include 'conexion.php';
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
 .container{padding:80px 20px 20px;position:relative;z-index:1}
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
    <strong>Próximo vencimiento del gimnasio:</strong> <?= date('d/m/Y', strtotime($proximo_vencimiento)) ?><br>
    <?= $cliente_activo ?>
  </div>
</header>

<nav>
  <div class="dropdown"><span class="dropbtn">Clientes</span>
    <div class="dropdown-content"><a href="agregar_cliente.php">Agregar</a><a href="ver_clientes.php">Ver</a><a href="disciplinas.php">Disciplinas</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Membresías</span>
    <div class="dropdown-content"><a href="nueva_membresia.php">Nueva</a><a href="ver_membresias.php">Ver</a><a href="planes.php">Planes</a><a href="adicionales.php">Adicionales</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Asistencias</span>
    <div class="dropdown-content"><a href="registrar_asistencia.php">Registrar</a><a href="ver_asistencias.php">Ver</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">QR</span>
    <div class="dropdown-content"><a href="scanner_qr.php">Escanear</a><a href="generar_qr.php">Generar</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Profesores</span>
    <div class="dropdown-content"><a href="agregar_profesor.php">Agregar</a><a href="ver_profesores.php">Ver</a><a href="ver_pagos_profesor.php">Pagos</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Ventas</span>
    <div class="dropdown-content"><a href="ventas_protecciones.php">Protecciones</a><a href="ventas_indumentaria.php">Indumentaria</a><a href="ventas_suplementos.php">Suplementos</a><a href="ver_ventas.php">Ver Todas</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Gimnasios</span>
    <div class="dropdown-content"><a href="agregar_gimnasio.php">Agregar</a><a href="ver_gimnasios.php">Ver</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Usuarios</span>
    <div class="dropdown-content"><a href="agregar_usuario.php">Agregar</a><a href="ver_usuarios.php">Ver</a></div>
  </div>
  <div class="dropdown"><span class="dropbtn">Configuraciones</span>
    <div class="dropdown-content"><a href="configurar_planes.php">Planes</a><a href="configurar_accesos.php">Accesos</a></div>
  </div>
  <a href="logout.php" class="dropbtn">Cerrar Sesión</a>
</nav>

<div class="container">

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

  <div class="card">
    <h3>Próximos Vencimientos</h3>
    <ul><li>Lucia Ramírez – 28/06/2025</li><li>Diego Martínez – 05/07/2025</li></ul>
    <h3>Próximos Cumpleaños</h3>
    <ul><li>Sofía Fernández – 26/06</li><li>Tomás Aguirre – 30/06</li></ul>
  </div>

</div>

<footer>Panel de administración – <?= $gimnasio_nombre ?></footer>

<div class="bottom-bar">
  <a href="index.php"><i class="fas fa-home"></i><br>Inicio</a>
  <a href="ver_clientes.php"><i class="fas fa-users"></i><br>Clientes</a>
  <a href="ver_membresias.php"><i class="fas fa-id-card"></i><br>Membresías</a>
  <a href="scanner_qr.php"><i class="fas fa-qrcode"></i><br>QR</a>
  <a href="registrar_asistencia.php"><i class="fas fa-calendar-check"></i><br>Asistencias</a>
  <a href="ver_ventas.php"><i class="fas fa-shopping-cart"></i><br>Ventas</a>
</div>

</body>
</html>
