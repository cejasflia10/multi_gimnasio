<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
}
.menu {
    width: 250px;
    background-color: #111;
    height: 100vh;
    position: fixed;
    overflow-y: auto;
    color: gold;
}
.menu h2 {
    text-align: center;
    padding: 15px 10px;
    background-color: #000;
    margin: 0;
    font-size: 20px;
}
.menu ul {
    list-style: none;
    padding: 0;
    margin: 0;
}
.menu li {
    border-bottom: 1px solid #222;
}
.menu a {
    display: block;
    padding: 12px 20px;
    text-decoration: none;
    color: gold;
    cursor: pointer;
}
.menu a:hover {
    background-color: #333;
}
.submenu {
    display: none;
    background-color: #1a1a1a;
}
.menu li.active .submenu {
    display: block;
}
.menu .submenu a {
    padding-left: 40px;
    font-size: 14px;
    color: #ddd;
}
@media (max-width: 768px) {
    .menu {
        width: 100%;
        height: auto;
        position: relative;
    }
}
</style>

<div class="menu">
    <h2>Fight Academy</h2>
    <ul>
        <li onclick="toggleMenu(this)">
            <a>Clientes</a>
            <ul class="submenu">
                <li><a href="ver_clientes.php">Ver Clientes</a></li>
                <li><a href="agregar_cliente.php">Agregar Cliente</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Membresías</a>
            <ul class="submenu">
                <li><a href="ver_membresias.php">Ver Membresías</a></li>
                <li><a href="agregar_membresia.php">Nueva Membresía</a></li>
                <li><a href="planes.php">Planes</a></li>
                <li><a href="planes_adicionales.php">Planes Adicionales</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Asistencias</a>
            <ul class="submenu">
                <li><a href="asistencias.php">Ver Asistencias</a></li>
                <li><a href="registrar_asistencia.php">Registrar Asistencia</a></li>
                <li><a href="asistencia_qr.php">Asistencia QR</a></li>
                <li><a href="asistencia_profesor.php">Asistencia Profesores</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>QR</a>
            <ul class="submenu">
                <li><a href="scanner_qr.php">Escanear QR</a></li>
                <li><a href="generar_qr.php">Generar QR</a></li>
                <li><a href="formulario_qr.php">Formulario QR</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Profesores</a>
            <ul class="submenu">
                <li><a href="listar_profesor.php">Ver Profesores</a></li>
                <li><a href="agregar_profesor.php">Agregar Profesor</a></li>
                <li><a href="turnos_profesor.php">Turnos</a></li>
                <li><a href="reporte_profesores.php">Pagos Mensuales</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Gimnasios</a>
            <ul class="submenu">
                <li><a href="ver_gimnasios.php">Ver Gimnasios</a></li>
                <li><a href="agregar_gimnasio.php">Agregar Gimnasio</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Usuarios</a>
            <ul class="submenu">
                <li><a href="ver_usuarios.php">Ver Usuarios</a></li>
                <li><a href="agregar_usuario.php">Agregar Usuario</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Configuraciones</a>
            <ul class="submenu">
                <li><a href="configuraciones.php">Ajustes Generales</a></li>
            </ul>
        </li>
        <li onclick="toggleMenu(this)">
            <a>Ventas</a>
            <ul class="submenu">
                <li><a href="ventas.php">Ver Ventas</a></li>
                <li><a href="nueva_venta.php">Nueva Venta</a></li>
                <li><a href="productos.php">Productos</a></li>
                <li><a href="categorias.php">Categorías</a></li>
            </ul>
        </li>
    </ul>
</div>

<script>
function toggleMenu(element) {
    element.classList.toggle("active");
}
</script>
