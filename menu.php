<nav id="sidebar">
    <div class="sidebar-header">
        <h3>🥋 Fight Academy</h3>
    </div>
    <ul class="components">
        <li><a href="index.php">🏠 Dashboard</a></li>
        <li>
            <button class="dropdown-btn">👤 Clientes ▾</button>
            <div class="dropdown-container">
                <a href="clientes.php">Ver Clientes</a>
                <a href="agregar_cliente.php">Agregar Cliente</a>
            </div>
        </li>
        <li>
            <button class="dropdown-btn">📋 Membresías ▾</button>
            <div class="dropdown-container">
                <a href="membresias.php">Ver Membresías</a>
                <a href="agregar_membresia.php">Agregar Membresía</a>
            </div>
        </li>
        <li><a href="profesores.php">👨‍🏫 Profesores</a></li>
        <li><a href="asistencias.php">🕒 Asistencias</a></li>
        <li><a href="ventas.php">💵 Ventas</a></li>
        <li>
            <button class="dropdown-btn">⚙️ Configuración ▾</button>
            <div class="dropdown-container">
                <a href="cambiar_contrasena.php">🔐 Cambiar Contraseña</a>
                <a href="logout.php">🚪 Cerrar Sesión</a>
                <a href="ver_gimnasios.php">🏢 Ver Gimnasios</a>
                <a href="crear_gimnasio.php">➕ Crear Gimnasio</a>
            </div>
        </li>
    </ul>
</nav>
<script>
    const dropdowns = document.querySelectorAll(".dropdown-btn");
    dropdowns.forEach(btn => {
        btn.addEventListener("click", function () {
            this.classList.toggle("active");
            const container = this.nextElementSibling;
            container.style.display = container.style.display === "block" ? "none" : "block";
        });
    });
</script>
