
<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); } 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
    body {
        margin: 0;
        font-family: Arial, sans-serif;
        background-color: #000;
        color: gold;
    }

    .menu-horizontal {
        background-color: #a00;
        padding: 10px;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        gap: 15px;
        justify-content: flex-start;
        -webkit-overflow-scrolling: touch;
    }

    .menu-horizontal a {
        color: gold;
        text-decoration: none;
        font-weight: bold;
        padding: 8px 14px;
        flex-shrink: 0;
        border-radius: 5px;
        white-space: nowrap;
    }

    .menu-horizontal a:hover {
        background-color: #700;
        color: white;
    }

    @media screen and (max-width: 768px) {
        .menu-horizontal {
            justify-content: flex-start;
        }
    }
    </style>
</head>
<body>

<div class="menu-horizontal">
    <a href="index.php">Inicio</a>
    <a href="ver_clientes.php">Clientes</a>
    <a href="ver_membresias.php">Membresías</a>
    <a href="ver_turnos.php">Turnos</a>
    <a href="ver_profesores.php">Profesores</a>
    <a href="ver_ventas.php">Ventas</a>
    <a href="ver_pagos.php">Pagos</a>
    <a href="ver_qr.php">QR</a>
    <a href="ver_estadisticas.php">Estadísticas</a>
    <a href="logout.php">Salir</a>
</div>

</body>
</html>
