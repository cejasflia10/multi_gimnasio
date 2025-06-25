<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<style>
/* Estilo general del menú */
.menu-horizontal {
    background-color: #111;
    color: gold;
    display: flex;
    justify-content: space-around;
    padding: 10px 0;
    font-weight: bold;
    position: fixed;
    width: 100%;
    top: 0;
    z-index: 999;
    flex-wrap: wrap;
}
.menu-horizontal a {
    color: gold;
    text-decoration: none;
    padding: 8px 12px;
}
.menu-horizontal a:hover {
    background-color: #222;
}
@media (max-width: 768px) {
    .menu-horizontal {
        bottom: 0;
        top: auto;
        font-size: 14px;
    }
}
body {
    padding-top: 60px;
}
@media (max-width: 768px) {
    body {
        padding-top: 0;
        padding-bottom: 60px;
    }
}
</style>

<div class="menu-horizontal">
    <a href="index.php">Inicio</a>
    <a href="clientes.php">Clientes</a>
    <a href="membresias.php">Membresías</a>
    <a href="asistencias.php">Asistencias</a>
    <a href="ventas.php">Ventas</a>
    <a href="profesores.php">Profesores</a>
</div>
