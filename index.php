<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$gimnasio_nombre = 'Gimnasio';
$proximo_vencimiento = '';
$cliente_activo = '';
if ($gimnasio_id > 0) {
    $res = $conexion->query("SELECT nombre, fecha_vencimiento FROM gimnasios WHERE id = $gimnasio_id LIMIT 1");
    if ($fila = $res->fetch_assoc()) {
        $gimnasio_nombre = $fila['nombre'];
        $proximo_vencimiento = $fila['fecha_vencimiento'];
    }
    $res_cli = $conexion->query("SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
        FROM clientes 
        JOIN membresias ON clientes.id = membresias.cliente_id 
        WHERE clientes.gimnasio_id = $gimnasio_id 
        ORDER BY membresias.fecha_vencimiento ASC LIMIT 1");
    if ($c = $res_cli->fetch_assoc()) {
        $cliente_activo = $c['nombre'] . ' ' . $c['apellido'] . ' – Vence: ' . date('d/m/Y', strtotime($c['fecha_vencimiento']));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Panel Principal</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    body { background: #111; color: gold; font-family: Arial, sans-serif; margin: 0; padding-bottom: 60px; }
    header {
      display: flex; justify-content: space-between; align-items: center;
      background-color: #1a1a1a; padding: 15px 20px; flex-wrap: wrap;
    }
    header h1 { font-size: 22px; color: gold; margin: 0; }
    .info-header { text-align: right; font-size: 14px; color: #ccc; }
    nav {
      background-color: #222;
      display: flex; flex-wrap: wrap;
      justify-content: center;
    }
    nav .dropdown {
      position: relative;
    }
    nav a, nav .dropbtn {
      color: gold;
      padding: 12px 20px;
      text-decoration: none;
      display: block;
      cursor: pointer;
    }
    nav .dropdown-content {
      display: none;
      position: absolute;
      background-color: #333;
      min-width: 180px;
      z-index: 1;
    }
    nav .dropdown-content a {
      color: gold;
      padding: 10px;
      text-decoration: none;
      display: block;
    }
    nav .dropdown:hover .dropdown-content {
      display: block;
    }
    .container { padding: 20px; }
    .stats-grid {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 20px; text-align: center; margin-bottom: 30px;
    }
    .card {
      background-color: #1f1f1f; padding: 20px; border-radius: 12px;
      box-shadow: 0 0 8px #000;
    }
    .bar-section { margin-bottom: 25px; }
    .bar-title { font-weight: bold; margin-bottom: 10px; color: gold; }
    .bar-row {
      display: flex; gap: 10px; justify-content: space-between;
    }
    .bar {
      background-color: #444; border-radius: 5px; height: 18px;
      width: 100%; overflow: hidden;
    }
    .bar-inner-yellow { background-color: gold; height: 100%; width: 70%; }
    .bar-inner-orange { background-color: orange; height: 100%; width: 40%; }
    footer {
      text-align: center; background-color: #222; color: gold;
      padding: 10px; position: fixed; bottom: 0; width: 100%; font-size: 14px;
    }
    .bottom-bar { display: none; }
    @media (max-width: 768px) {
      nav { display: none; }
      .bottom-bar {
        display: flex; justify-content: space-around;
        background-color: #222; padding: 10px 0;
        position: fixed; bottom: 0; width: 100%; z-index: 999;
      }
      .bottom-bar a {
        color: gold; text-align: center; text-decoration: none; font-size: 13px;
      }
    }
  </style>
</head>
<body>
<header>
  <h1><?= $gimnasio_nombre ?></h1>
  <div class="info-header">
    <strong>Próximo vencimiento del gimnasio:</strong> <?= date('d/m/Y', strtotime($proximo_vencimiento)) ?><br>
    Cliente activo: <?= $cliente_activo ?>
  </div>
</header>
<nav>
  <div class="dropdown">
    <div class="dropbtn">Clientes</div>
    <div class="dropdown-content">
      <a href="agregar_cliente.php">Agregar Cliente</a>
      <a href="ver_clientes.php">Ver Clientes</a>
      <a href="disciplinas.php">Disciplinas</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Membresías</div>
    <div class="dropdown-content">
      <a href="agregar_membresia.php">Agregar Membresía</a>
      <a href="ver_membresias.php">Ver Membresías</a>
      <a href="planes.php">Planes</a>
      <a href="planes_adicionales.php">Planes Adicionales</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Asistencias</div>
    <div class="dropdown-content">
      <a href="registrar_asistencia.php">Registrar Asistencia</a>
      <a href="ver_asistencias.php">Ver Asistencias</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">QR</div>
    <div class="dropdown-content">
      <a href="scanner_qr.php">Escanear QR</a>
      <a href="generar_qr.php">Generar QR</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Profesores</div>
    <div class="dropdown-content">
      <a href="agregar_profesor.php">Agregar Profesor</a>
      <a href="ver_profesores.php">Ver Profesores</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Ventas</div>
    <div class="dropdown-content">
      <a href="ventas_indumentaria.php">Indumentaria</a>
      <a href="ventas_suplementos.php">Suplementos</a>
      <a href="ventas_protecciones.php">Protecciones</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Gimnasios</div>
    <div class="dropdown-content">
      <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
      <a href="ver_gimnasios.php">Ver Gimnasios</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Usuarios</div>
    <div class="dropdown-content">
      <a href="agregar_usuario.php">Agregar Usuario</a>
      <a href="ver_usuarios.php">Ver Usuarios</a>
    </div>
  </div>
  <div class="dropdown">
    <div class="dropbtn">Configuraciones</div>
    <div class="dropdown-content">
      <a href="configuracion_general.php">General</a>
      <a href="panel_control.php">Panel General</a>
    </div>
  </div>
  <div class="dropdown"><a class="dropbtn" href="logout.php">Cerrar Sesión</a></div>
</nav>
