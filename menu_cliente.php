<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<style>
.menu-horizontal {
    background-color: #111;
    overflow-x: auto;
    white-space: nowrap;
    padding: 10px;
    display: flex;
    gap: 10px;
    font-family: Arial, sans-serif;
    border-bottom: 1px solid gold;
}
.menu-horizontal a {
    color: gold;
    text-decoration: none;
    font-weight: bold;
    padding: 10px 15px;
    flex-shrink: 0;
    border-radius: 6px;
    white-space: nowrap;
}
.menu-horizontal a:hover {
    background-color: gold;
    color: black;
}
@media screen and (max-width: 768px) {
    .menu-horizontal {
        flex-wrap: nowrap;
        overflow-x: auto;
    }
}
</style>

<div class="menu-horizontal">
    <a href="panel_cliente.php">Inicio</a>
    <a href="ver_mis_pagos.php">Mis Pagos</a>
    <a href="pago_online.php">Pago Online</a>
    <a href="ver_turnos_cliente.php">Ver Turnos</a>
    <a href="ver_progreso_cliente.php">Progreso</a>
    <a href="ver_graduaciones_cliente.php">Graduaciones</a>
    <a href="ver_competencias_cliente.php">Competencias</a>
    <a href="registrar_competencia_cliente.php">Inscribirme a competencia</a>
    <a href="ver_datos_fisicos_cliente.php">Datos Físicos</a>
    <a href="grafico_progreso_cliente.php">Evolución</a>
    <a href="subastas.php">Subastas</a>
    <a href="sorteos.php">Sorteos</a>
    <a href="logout.php">Salir</a>
</div>
