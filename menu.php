<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}
$rol = $_SESSION['rol'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - MultiGimnasio</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        body {
            margin: 0;
            background-color: #111;
            color: gold;
            font-family: 'Segoe UI', sans-serif;
        }
        .sidebar {
            position: fixed;
            top: 0; left: 0;
            width: 250px;
            height: 100vh;
            background-color: #1a1a1a;
            padding: 20px 0;
            overflow-y: auto;
        }
        .sidebar h2 {
            text-align: center;
            color: gold;
            margin-bottom: 30px;
        }
        .sidebar a {
            display: block;
            padding: 10px 20px;
            color: white;
            text-decoration: none;
        }
        .sidebar a:hover {
            background-color: #444;
        }
        .submenu {
            padding-left: 30px;
        }
        .content {
            margin-left: 250px;
            padding: 20px;
        }
        @media screen and (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Fight Academy</h2>
        <a href="index.php">Panel</a>

        <a href="#">Clientes</a>
        <div class="submenu">
            <a href="agregar_cliente.php">Agregar Cliente</a>
            <a href="ver_clientes.php">Ver Clientes</a>
        </div>

        <a href="#">Membresías</a>
        <div class="submenu">
            <a href="nueva_membresia.php">Nueva Membresía</a>
            <a href="ver_membresias.php">Ver Membresías</a>
        </div>

        <a href="#">Registros Online</a>
        <div class="submenu">
            <a href="registro_online.php">Ver Registros</a>
        </div>

        <a href="#">Asistencias</a>
        <div class="submenu">
            <a href="asistencias_clientes.php">Clientes</a>
            <a href="asistencias_profesores.php">Profesores</a>
        </div>

        <a href="#">QR</a>
        <div class="submenu">
            <a href="scanner_qr.php">Escanear QR</a>
            <a href="generar_qr.php">Generar QR</a>
        </div>

        <a href="#">Profesores</a>
        <div class="submenu">
            <a href="agregar_profesor.php">Agregar Profesor</a>
            <a href="ver_profesores.php">Ver Profesores</a>
            <a href="turnos_profesores.php">Turnos</a>
            <a href="pagos_profesores.php">Pagos</a>
        </div>

        <a href="#">Ventas</a>
        <div class="submenu">
            <a href="ventas_indumentaria.php">Indumentaria</a>
            <a href="ventas_suplementos.php">Suplementos</a>
        </div>

        <a href="#">Gimnasios</a>
        <div class="submenu">
            <a href="ver_gimnasios.php">Ver Gimnasios</a>
            <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
        </div>

        <a href="#">Usuarios</a>
        <div class="submenu">
            <a href="ver_usuarios.php">Ver Usuarios</a>
            <a href="agregar_usuario.php">Agregar Usuario</a>
        </div>

        <a href="#">Configuración</a>
        <div class="submenu">
            <a href="configuracion.php">Sistema</a>
        </div>
    </div>

    <div class="content">
        <h1>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $rol; ?>)</h1>
        <h3>Panel de control de Fight Academy</h3>
        <?php include('asistencias_index.php'); ?>
    </div>
</body>
</html>
