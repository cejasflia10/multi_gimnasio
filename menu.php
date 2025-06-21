<?php
session_start();
if (!isset($_SESSION['rol'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Menú - Gym</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        nav {
            width: 250px;
            background-color: #000;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            padding-top: 20px;
        }
        .nav-item {
            list-style: none;
            margin: 10px 0;
        }
        .nav-link {
            color: gold;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
        }
        .nav-link:hover {
            background-color: #333;
        }
        .submenu {
            list-style: none;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <nav>
        <h2 style="color: gold; text-align:center;">Fight Academy</h2>
        <ul>

        <!-- INICIO DEL MENU -->
        <?php if ($_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'escuela') { ?>
            <!-- Clientes -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">Clientes</a>
                <ul class="submenu">
                    <li><a href="agregar_cliente.php" class="nav-link">Agregar Cliente</a></li>
                    <li><a href="ver_clientes.php" class="nav-link">Ver Clientes</a></li>
                    <li><a href="ver_asistencias_qr.php" class="nav-link">Ver Asistencias</a></li>
                    <li><a href="importar_clientes.php" class="nav-link">Importar</a></li>
                    <li><a href="exportar_clientes.php" class="nav-link">Exportar</a></li>
                </ul>
            </li>

            <!-- Membresías -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">Membresías</a>
                <ul class="submenu">
                    <li><a href="nueva_membresia.php" class="nav-link">Nueva Membresía</a></li>
                    <li><a href="ver_membresias.php" class="nav-link">Ver Membresías</a></li>
                    <li><a href="planes_adicionales.php" class="nav-link">Planes Adicionales</a></li>
                    <li><a href="disciplinas.php" class="nav-link">Disciplinas</a></li>
                </ul>
            </li>

            <!-- Ventas -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">Ventas</a>
                <ul class="submenu">
                    <li><a href="ventas_indumentaria.php" class="nav-link">Indumentaria</a></li>
                    <li><a href="ventas_protecciones.php" class="nav-link">Protecciones</a></li>
                    <li><a href="ventas_suplementos.php" class="nav-link">Suplementos</a></li>
                </ul>
            </li>

            <!-- Asistencias -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">Asistencias</a>
                <ul class="submenu">
                    <li><a href="registrar_asistencia_qr.php" class="nav-link">Registrar QR</a></li>
                </ul>
            </li>
        <?php } ?>

        <?php if ($_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'profesor' || $_SESSION['rol'] == 'escuela') { ?>
            <!-- QR -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">QR</a>
                <ul class="submenu">
                    <li><a href="generar_qr.php" class="nav-link">Generar QR</a></li>
                    <li><a href="registrar_asistencia_qr.php" class="nav-link">Escanear QR</a></li>
                </ul>
            </li>
        <?php } ?>

        <?php if ($_SESSION['rol'] == 'admin') { ?>
            <!-- Administración -->
            <li class="nav-item has-submenu">
                <a class="nav-link" href="#">Admin</a>
                <ul class="submenu">
                    <li><a href="usuarios.php" class="nav-link">Usuarios</a></li>
                    <li><a href="agregar_usuario.php" class="nav-link">Agregar Usuario</a></li>
                    <li><a href="gimnasios.php" class="nav-link">Gimnasios</a></li>
                    <li><a href="agregar_gimnasio.php" class="nav-link">Agregar Gimnasio</a></li>
                </ul>
            </li>
        <?php } ?>
        <!-- FIN DEL MENU -->

        </ul>
    </nav>
</body>
</html>
