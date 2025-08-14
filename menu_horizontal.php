<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú Horizontal</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            margin: 0;
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
        }

        .menu-toggle {
            display: none;
            background-color: #a00;
            color: gold;
            font-size: 20px;
            padding: 10px;
            text-align: center;
            cursor: pointer;
        }

        .menu-horizontal {
            background-color: #a00;
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            padding: 6px 10px;
            position: relative;
            z-index: 1000;
        }

        .menu-horizontal .dropdown {
            position: relative;
        }

        .menu-horizontal > .dropdown > a,
        .menu-horizontal > a {
            color: gold;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 14px;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #111;
            min-width: 160px;
            z-index: 1100;
            border: 1px solid #700;
        }

        .dropdown-content a {
            display: block;
            padding: 8px 12px;
            border-bottom: 1px solid #333;
            color: gold;
            text-decoration: none;
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }

        .menu-horizontal a:hover,
        .dropdown-content a:hover {
            background-color: #700;
            border-radius: 5px;
        }

        @media screen and (max-width: 768px) {
            .menu-toggle {
                display: block;
            }

            .menu-horizontal {
                display: none;
                flex-direction: column;
                width: 100%;
            }

            .menu-horizontal.active {
                display: flex !important;
            }

            .dropdown {
                width: 100%;
            }

            .dropdown-content {
                position: static;
                background-color: #111;
                border: none;
            }

            .menu-horizontal a {
                display: block;
                padding: 12px;
            }

            .dropdown-content a {
                padding-left: 20px;
            }
        }
    </style>
    <script>
        function toggleMenu() {
            var menu = document.getElementById("menu-principal");
            menu.classList.toggle("active");
        }
    </script>
</head>
<body>

<div class="menu-toggle" onclick="toggleMenu()">☰ Menú</div>

<div class="menu-horizontal" id="menu-principal">
    <div class="dropdown">
        <a href="#">👤 Clientes</a>
        <div class="dropdown-content">
            <a href="ver_clientes.php">Ver Clientes</a>
            <a href="agregar_cliente.php">Agregar Cliente</a>
<?php if (false) : ?>
    <a href="panel_eventos.php">Eventos</a>
<?php endif; ?>
<?php if (false) : ?>
            <a href="login_evento.php"class="oculto">Acceso a Panel de Eventos</a>
          <?php endif; ?>  
          <?php if (false) : ?>
            <a href="eventos_publicos.php"class="oculto">Ver Eventos Públicos</a>
          <?php endif; ?>  
        </div>
    </div>
    <div class="dropdown">
        <a href="#">📅 Membresías</a>
        <div class="dropdown-content">
            <a href="ver_membresias.php">Ver Membresías</a>
            <a href="nueva_membresia.php">Agregar Membresía</a>
            <a href="disciplinas.php">Disicplinas</a>
            <a href="planes.php">Planes</a>
            <a href="adicionales.php">Adicionales</a>
        </div>
    </div>
    <div class="dropdown">
        <a href="#">💳 Pagos</a>
        <div class="dropdown-content">
            <a href="ver_pagos_pendientes.php">Pagos Pendientes</a>
            <a href="config_alias.php">Alias</a>
            <a href="ver_pagos_mes.php">Pagos del Mes</a>
            <a href="ver_cuentas_corrientes.php">Pagos Cuenta Corrientes</a>
        </div>
    </div>
    <div class="dropdown">
        <a href="#">🧍‍♂️ Asistencias</a>
        <div class="dropdown-content">
            <a href="ver_asistencia.php">Ver Asistencias</a>
            <a href="registrar_asistencia.php">Registrar Asistencia</a>
            <a href="scanner_qr.php">Escaneo QR</a>
            <a href="ver_asistencias_profesor.php">Asistencia Profesores</a>

        </div>
    </div>
    <div class="dropdown">
        <a href="#">🛒 Ventas</a>
        <div class="dropdown-content">
            <a href="agregar_producto.php">Agregar Productos</a>
            <a href="ventas_proteccion.php">Ventas Protecciones</a>
            <a href="ventas_suplementos.php">Ventas Suplementos</a>
            <a href="ventas_indumentaria.php">Ventas Indumentaria</a>
            <a href="ver_productos.php">Ver Productos</a>
            <a href="ver_facturas.php">Ver Facturas</a>
        </div>
    </div>
    <div class="dropdown">
        <a href="#">👨‍🏫 Profesores</a>
        <div class="dropdown-content">
            <a href="agregar_profesor.php">Agregar Profesor</a>
            <a href="login_profesor.php">Panel</a>
            <a href="ver_profesores.php">Ver Profesores</a>
            <a href="turnos_profesor.php">Turnos Profesores</a>
            <a href="editar_tarifa_profesor.php">Precio de Horas</a>
            <a href="reporte_horas_profesor.php">Reporte de Horas</a>
            <a href="biometria/enrolar_profesores.php">Enrolar huella</a>

        </div>
    </div>
    <div class="dropdown">
        <a href="#">📲 Panel Cliente</a>
        <div class="dropdown-content">
            <a href="cliente_acceso.php">Panel</a>
            <a href="panel_configuracion.php">Panel Configuración</a>
        </div>
    </div>
    <div class="dropdown">
        <a href="#">❌ Cerrar</a>
        <div class="dropdown-content">
            <a href="index.php">Volver al Inicio</a>
            <a href="logout.php"> Cerrar Sesión</a>
            <a href="#" onclick="cerrarApp()">❌ Cerrar Programa</a>
        </div>
    </div>
</div>

<script>
function cerrarApp() {
    if (confirm("¿Seguro que deseas cerrar la aplicación?")) {
        if (window.electronAPI) {
            window.electronAPI.cerrarVentana();
        } else {
            window.close();
        }
    }
}
</script>

</body>
</html>
