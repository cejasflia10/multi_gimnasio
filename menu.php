
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
        }
        .sidebar {
            width: 250px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            background-color: #000;
            padding-top: 20px;
            overflow-y: auto;
        }
        .sidebar h2 {
            color: gold;
            text-align: center;
            margin-bottom: 10px;
        }
        .sidebar h3 {
            color: gold;
            margin-left: 15px;
            font-size: 16px;
            border-bottom: 1px solid #444;
            padding-bottom: 5px;
        }
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            transition: background 0.3s;
            font-size: 14px;
        }
        .sidebar a:hover {
            background-color: #333;
        }
        .content {
            margin-left: 260px;
            padding: 20px;
        }
        .icon {
            margin-right: 6px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Fight Academy</h2>
        <h3>Clientes</h3>
        <a href="agregar_cliente.php"><span class="icon">➕</span>Agregar Cliente</a>
        <a href="ver_clientes.php"><span class="icon">👤</span>Ver Clientes</a>
        <a href="disciplinas.php"><span class="icon">🥋</span>Disciplinas</a>
        <a href="exportar_clientes.php"><span class="icon">📤</span>Exportar</a>

        <h3>Registros Online</h3>
        <a href="registrar_cliente_online.php"><span class="icon">🌐</span>Registrar Cliente</a>

        <h3>Membresías</h3>
        <a href="agregar_membresia.php"><span class="icon">➕</span>Agregar Membresía</a>
        <a href="ver_membresias.php"><span class="icon">📄</span>Ver Membresías</a>
        <a href="planes.php"><span class="icon">📋</span>Planes</a>
        <a href="planes_adicionales.php"><span class="icon">➕</span>Planes Adicionales</a>

        <h3>Profesores</h3>
        <a href="agregar_profesor.php"><span class="icon">➕</span>Agregar Profesor</a>
        <a href="ver_profesores.php"><span class="icon">👨‍🏫</span>Ver Profesores</a>

        <h3>Productos y Ventas</h3>
        <a href="indumentaria.php"><span class="icon">👕</span>Indumentaria</a>
        <a href="protecciones.php"><span class="icon">🥊</span>Protecciones</a>
    </div>

    <div class="content">
        <!-- Aquí va el contenido central -->
    </div>
</body>
</html>
