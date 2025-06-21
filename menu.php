<style>
.sidebar {
    position: fixed;
    width: 250px;
    height: 100%;
    background: #222;
    color: white;
    overflow-y: auto;
}
.sidebar h2 {
    text-align: center;
    padding: 20px;
    background: black;
    margin: 0;
    color: gold;
}
.sidebar ul {
    list-style: none;
    padding: 0;
}
.sidebar ul li {
    padding: 10px 20px;
    cursor: pointer;
}
.sidebar ul li:hover {
    background: #333;
}
.sidebar ul li ul {
    display: none;
    list-style: none;
    padding-left: 20px;
}
.sidebar ul li:hover ul {
    display: block;
}
.sidebar ul li ul li {
    padding: 8px 0;
    color: #ccc;
}
.sidebar ul li ul li:hover {
    color: gold;
}
</style>

<div class="sidebar">
    <h2>Fight Academy</h2>
    <ul>
        <li>📊 Panel</li>
        <li>👤 Clientes
            <ul>
                <li><a href="agregar_cliente.php">Agregar Cliente</a></li>
                <li><a href="ver_clientes.php">Ver Clientes</a></li>
            </ul>
        </li>
        <li>📋 Membresías
            <ul>
                <li><a href="agregar_membresia.php">Nueva Membresía</a></li>
                <li><a href="ver_membresias.php">Ver Membresías</a></li>
            </ul>
        </li>
        <li>🌐 Registros Online
            <ul>
                <li><a href="ver_registros_online.php">Ver Registros</a></li>
            </ul>
        </li>
        <li>🕓 Asistencias
            <ul>
                <li><a href="asistencias.php">Ver Asistencias</a></li>
                <li><a href="asistencia_qr.php">QR</a></li>
            </ul>
        </li>
        <li>👨‍🏫 Profesores
            <ul>
                <li><a href="ver_profesores.php">Ver Profesores</a></li>
                <li><a href="pagos_profesores.php">Pagos</a></li>
            </ul>
        </li>
        <li>🛒 Ventas
            <ul>
                <li><a href="ventas_indumentaria.php">Indumentaria</a></li>
                <li><a href="ventas_protecciones.php">Protecciones</a></li>
            </ul>
        </li>
        <li>🏋️ Gimnasios
            <ul>
                <li><a href="ver_gimnasios.php">Ver Gimnasios</a></li>
                <li><a href="agregar_gimnasio.php">Agregar Gimnasio</a></li>
            </ul>
        </li>
        <li>🔐 Usuarios
            <ul>
                <li><a href="usuarios.php">Ver Usuarios</a></li>
                <li><a href="agregar_usuario.php">Agregar Usuario</a></li>
            </ul>
        </li>
    </ul>
</div>
