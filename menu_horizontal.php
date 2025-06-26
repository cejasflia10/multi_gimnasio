<div class="menu-pc">
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$rol = $_SESSION['rol'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú Horizontal</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #111;
            color: gold;
        }
        .navbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            background-color: #000;
            border-bottom: 2px solid gold;
        }
        .navbar > div {
            position: relative;
            padding: 14px 16px;
            cursor: pointer;
            color: gold;
        }
        .navbar > div:hover {
            background-color: #222;
        }
        .submenu {
            display: none;
            position: absolute;
            background-color: #111;
            min-width: 160px;
            top: 48px;
            z-index: 1;
            border: 1px solid gold;
        }
        .submenu a {
            color: gold;
            padding: 10px;
            display: block;
            text-decoration: none;
        }
        .submenu a:hover {
            background-color: #222;
        }
        .navbar > div:hover .submenu {
            display: block;
        }

        @media screen and (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: stretch;
            }
            .navbar > div {
                border-bottom: 1px solid gold;
            }
            .submenu {
                position: static;
                border: none;
            }
        }
    </style>
</head>
<body>
<div class="solo-pc">   
  <div class="navbar">
    <div>Clientes
        <div class="submenu">
            <a href="agregar_cliente.php">Agregar</a>
            <a href="ver_clientes.php">Ver</a>
            <a href="disciplinas.php">Disciplinas</a>
        </div>
    </div>
    <div>Membresías
        <div class="submenu">
            <a href="nueva_membresia.php">Nueva</a>
            <a href="ver_membresias.php">Ver</a>
            <a href="planes.php">Planes</a>
            <a href="planes_adicionales.php">Adicionales</a>
        </div>
    </div>
    <div>Asistencias
        <div class="submenu">
            <a href="registrar_asistencia.php">Registrar</a>
            <a href="ver_asistencias.php">Ver</a>
        </div>
    </div>
    <div>Profesores
        <div class="submenu">
            <a href="agregar_profesor.php">Agregar</a>
            <a href="ver_profesores.php">Ver</a>
            <a href="pagos_profesores.php">Pagos</a>
        </div>
    </div>
    <div>Ventas
        <div class="submenu">
            <a href="productos_protecciones.php">Protecciones</a>
            <a href="productos_indumentaria.php">Indumentaria</a>
            <a href="productos_suplementos.php">Suplementos</a>
            <a href="ventas.php">Crear venta</a>
        </div>
    </div>

    <div>Panel Cliente
        <div class="submenu">
            <a href="cliente_acceso.php"><i class="fas fa-id-card"></i> Ingresar con DNI</a>
            <a href="ver_pagos_cliente.php"><i class="fas fa-money-bill-wave"></i> Ver Pagos</a>
            <a href="ver_asistencias_cliente.php"><i class="fas fa-calendar-check"></i> Ver Asistencias</a>
            <a href="ver_qr_cliente.php"><i class="fas fa-qrcode"></i> Ver QR</a>
            <a href="ver_reservas_cliente.php"><i class="fas fa-calendar-alt"></i> Ver Reservas</a>
            <a href="datos_contacto.php"><i class="fas fa-address-book"></i> Datos de Contacto</a>
            <a href="subir_foto.php"><i class="fas fa-camera"></i> Subir/Cambiar Foto</a>
      </div>
   </div>
    <div>Gimnasios
        <div class="submenu">
            <a href="agregar_gimnasio.php">Crear</a>
            <a href="ver_gimnasios.php">Ver</a>
        </div>
    </div>
    <div>Usuarios
        <div class="submenu">
            <a href="agregar_usuario.php">Agregar</a>
            <a href="usuarios.php">Ver</a>
        </div>
    </div>
    <div>Configuraciones
        <div class="submenu">
            <a href="ver_planes.php">Planes</a>
            <a href="accesos.php">Accesos</a>
        </div>
     </div>
   </div>
</div>
   
    <div><a href="logout.php" style="color: gold; text-decoration: none;">Cerrar sesión</a></div>
</div>

</body>
</html>

</div>