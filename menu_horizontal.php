<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>

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
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
    }

    .menu-horizontal a {
        color: gold;
        text-decoration: none;
        font-weight: bold;
        padding: 6px 12px;
    }

    .menu-horizontal a:hover {
        background-color: #700;
        border-radius: 5px;
    }

    .menu-lateral {
        position: fixed;
        top: 50px;
        left: 0;
        width: 200px;
        background-color: rgba(0, 0, 0, 0.8);
        padding: 15px;
        z-index: 999;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.5);
        display: none;
    }

    .menu-lateral h3 {
        color: gold;
        margin-bottom: 10px;
    }

    .menu-lateral a {
        display: block;
        padding: 8px 0;
        color: gold !important;
        text-decoration: none !important;
        border-bottom: 1px solid #444;
    }

    .menu-lateral a:hover {
        background-color: rgba(255, 215, 0, 0.1);
    }

    @media screen and (max-width: 768px) {
        .menu-horizontal {
            flex-direction: column;
            align-items: center;
        }

        .menu-lateral {
            position: relative;
            width: 100%;
            top: 0;
        }
    }
    @media screen and (max-width: 768px) {
    /* Ocultar los submenús en celulares */
    #menu-clientes,
    #menu-asistencias,
    #menu-profesores,
    #menu-qr,
    #menu-ventas,
    #menu-panel,
    #menu-configuraciones {
        display: none !important;
    }
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

@media screen and (max-width: 768px) {
    .menu-toggle {
        display: block;
    }

    .menu-horizontal {
        display: none;
        flex-direction: column;
        align-items: center;
    }

    .menu-horizontal.active {
        display: flex !important;
    }

    .menu-lateral {
        position: relative;
        width: 100%;
        top: 0;
    }

    /* Ocultar submenús en celulares */
    #menu-clientes,
    #menu-asistencias,
    #menu-profesores,
    #menu-qr,
    #menu-ventas,
    #menu-panel,
    #menu-configuraciones {
        display: none !important;
    }
}

    </style>
</head>
<body>
<!-- BOTÓN PARA CELULARES -->
<div class="menu-toggle" onclick="toggleMenu()">☰ Menú</div>

<!-- MENÚ HORIZONTAL -->
<div class="menu-horizontal" id="menu-principal">
    <a href="#" onclick="mostrarLateral('clientes')">👥 Clientes</a>
    <a href="#" onclick="mostrarLateral('asistencias')">🕘 Asistencias</a>
    <a href="#" onclick="mostrarLateral('profesores')">🧑‍🏫 Profesores</a>
    <a href="#" onclick="mostrarLateral('qr')">📷 QR</a>
    <a href="#" onclick="mostrarLateral('ventas')">🛒 Ventas</a>
    <a href="#" onclick="mostrarLateral('panel')">👤 Panel Cliente</a>
    <a href="#" onclick="mostrarLateral('configuraciones')">⚙️ Configuraciones</a>
    <a href="logout.php" class="cerrar-sesion">Cerrar sesión</a>
    <a href="index.php">Volver</a>
</div>

<!-- MENÚ LATERAL DE CLIENTES -->
<div id="menu-clientes" class="menu-lateral">
    <h3>Clientes</h3>
    <a href="agregar_cliente.php">Agregar Cliente</a>
    <a href="ver_clientes.php">Ver Clientes</a>
    <a href="nueva_membresia.php">Nueva Membresía</a>
    <a href="ver_membresias.php">Ver Membresías</a>
    <a href="disciplinas.php">Disciplinas</a>
    <a href="planes.php">Planes</a>
    <a href="adicionales.php">Adicionales</a>
    <a href="ver_pagos_pendientes.php">Aprobar Pagos</a> <!-- solo visible para admin -->
    <a href="config_alias.php">Alias Transferencia</a>
</div>

<!-- MENÚ LATERAL DE ASISTENCIAS -->
<div id="menu-asistencias" class="menu-lateral">
    <h3>Asistencias</h3>
    <a href="editar_turno.php?id=1">Editar Turno</a> <!-- ejemplo con ID=1 -->
    <a href="registrar_asistencia_qr.php">Registrar Asistencia QR</a>
    <a href="ver_asistencia_qr.php">Ver Asistencia QR</a>
    <a href="asistencia_qr.php">Asistencia QR</a>
    <a href="formulario_qr.php">Formulario QR</a>
    <a href="historial_asistencia_filtro.php">Historial con Filtro</a>
    <a href="ver_asistencia.php">Ver Asistencia</a>
    <a href="ver_asistencia_mes.php">Asistencia del Mes</a>
</div>

<!-- MENÚ LATERAL DE PROFESORES -->
<div id="menu-profesores" class="menu-lateral">
    <h3>Profesores</h3>
    <a href="panel_profesor.php">Panel Profesor</a>
    <a href="agregar_profesor.php">Agregar Profesor</a>
    <a href="ver_profesores.php">Ver Profesores</a>
    <a href="configurar_precios_hora.php">precio horas</a>
    <a href="ver_asistencias_profesor.php">Ver Asistencias</a>
    <a href="reporte_horas_profesor.php">Reporte de Horas</a>
    <a href="reporte_horas_profesor.php">Reporte Horas Profesor</a>
    <a href="cargar_turno.php">Cargar Turno</a>
    <a href="turnos_profesor.php">Turnos Profesor</a>
    <a href="ver_pagos_profesor.php">Ver Pagos Profesor</a>
</div>
<!-- MENÚ LATERAL DE VENTAS -->
<div id="menu-ventas" class="menu-lateral">
    <h3>Ventas</h3>
    <a href="ver_productos.php">Ver Productos</a>
    <a href="ver_suplementos.php">Ver Suplementos</a>
    <a href="ver_indumentaria.php">Ver Indumentaria</a>
    <a href="ventas_proteccion.php">Ventas Protección</a>
    <a href="ventas_productos.php">Ventas Productos</a>
    <a href="ventas_suplementos.php">Ventas Suplementos</a>
    <a href="editar_producto.php">Editar Producto</a>
    <a href="agregar_producto.php">Agregar Producto</a>
    <a href="agregar_indumentaria.php">Agregar Indumentaria</a>
    <a href="agregar_suplementos.php">Agregar Suplementos</a>
    <a href="ver_categorias.php">Categorías</a>
    <a href="ver_ventas.php">Ver Ventas</a>

</div>

<!-- MENÚ LATERAL PANEL CLIENTES -->
<div id="menu-panel" class="menu-lateral">
    <h3>Panel Cliente</h3>
    <a href="cliente_turnos.php">Reservar Turno (Cliente)</a></li>
    <a href="cliente_acceso.php">Acceso Cliente</a>
    <a href="cliente_reservas.php">Reservas</a>
    <a href="clientes_pagos.php">Pagos</a>
    <a href="asistencias_cliente.php">Asistencias</a>
    <a href="crear_sorteo.php">Crear Sorteo</a>
    <a href="ver_sorteos_admin.php">Ver Sorteos</a>
    <a href="crear_subasta.php">➕ Crear Subasta</a>
    <a href="ver_subastas_admin.php">📋 Ver Subastas</a>

</div>
<!-- MENÚ LATERAL DE CONFIGURACIONES -->
<div id="menu-configuraciones" class="menu-lateral">
    <h3>Configuraciones</h3>
    <a href="agregar_usuario.php">Agregar Usuario</a>
    <a href="usuarios.php">Usuarios</a>
    <a href="usuarios_gimnasio.php">Usuarios por Gimnasio</a>
    <a href="configurar_accesos.php">Configurar Accesos</a>
    <a href="agregar_gimnasio.php">Agregar Gimnasio</a>
    <a href="crear_gimnasio.php">Crear Gimnasio</a>
    <a href="gimnasios.php">Ver Gimnasios</a>
    <a href="registro_pagos_gimnasio.php">Registro de Pagos</a>
    <a href="historial_pagos_gym.php">Historial de Pagos</a>
</div>

<!-- MENÚ LATERAL DE QR -->
<div id="menu-qr" class="menu-lateral">
    <h3>QR</h3>
 <a href="ver_clientes.php">Ver Clientes</a>
        <a href="ver_profesores.php">Ver Profesores</a>
        <a href="registro_qr_multi.php">Escanear QR Ingreso/Egreso</a>
        <a href="ver_asistencias_dia.php">Ver Asistencias del Día</a>
        <a href="reporte_horas_profesores.php">Reporte Horas Profesores</a>
        <a href="ver_qr_profesores.php">Códigos QR</a>
        <a href="configurar_precios_hora.php">Configurar Precio Hora</a>    <a href="scanner_qr.php">Escanear QR</a>
</div>

<!-- SCRIPT -->
<script>
function mostrarLateral(seccion) {
    const secciones = ['clientes', 'asistencias', 'profesores', 'qr', 'ventas', 'panel', 'configuraciones'];
    secciones.forEach(s => {
        const menu = document.getElementById('menu-' + s);
        if (menu) menu.style.display = 'none';
    });

    const menuMostrar = document.getElementById('menu-' + seccion);
    if (menuMostrar) {
        menuMostrar.style.display = 'block';
    }
}

// Cerrar menú lateral al hacer clic fuera
document.addEventListener('click', function (e) {
    const esLateral = e.target.closest('.menu-lateral');
    const esBotonMenu = e.target.closest('.menu-horizontal a');

    if (!esLateral && !esBotonMenu) {
        const secciones = ['clientes', 'asistencias', 'profesores', 'qr', 'ventas', 'panel', 'configuraciones'];
        secciones.forEach(s => {
            const menu = document.getElementById('menu-' + s);
            if (menu) menu.style.display = 'none';
        });
    }
});
// Toggle menú horizontal en celulares
function toggleMenu() {
    const menu = document.getElementById("menu-principal");
    if (menu) menu.classList.toggle("active");
}

</script>

</body>
</html>
