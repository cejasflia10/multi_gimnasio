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
    <a href="panel_profesor.php">Inicio</a>
    <a href="ver_alumnos.php">Alumnos</a>
    <a href="subir_rutina.php">Subir Archivo</a>
    <a href="historial_alumnos.php">Archivos</a>
    <a href="registrar_progreso.php">Progreso Físico</a>
    <a href="ver_progreso_fisico.php">Ver Progreso</a>
    <a href="scanner_qr_profesor.php">Escanear Alumnos (QR)</a></li>
    <a href="registrar_graduacion.php">Graduación</a>
    <a href="ver_graduaciones.php">Ver Graduaciones</a>
    <a href="registrar_competencia.php">Competencia</a>
    <a href="ver_competencias.php">Ver Competencias</a>
    <a href="registrar_datos_fisicos.php">Datos Físicos</a>
    <a href="ver_datos_fisicos_profesor.php">Ver Datos</a>
    <a href="ver_saldo.php">Saldo</a>
    <a href="logout_profesor.php">Cerrar Sesión</a>
</div>
