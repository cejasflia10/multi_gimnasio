<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'permisos.php';

// Bloqueo si no tiene permiso
if (!tiene_permiso('configuraciones')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}

$resultado = $conexion->query("SELECT * FROM plan_usuarios ORDER BY id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Configurar Planes de Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            color: gold;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        tr:nth-child(even) {
            background-color: #1a1a1a;
        }
        .boton {
            background-color: gold;
            color: black;
            padding: 6px 10px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }
        .boton:hover {
            background-color: #ffd700;
        }
        .top-link {
            display: inline-block;
            margin-bottom: 20px;
            text-decoration: none;
            background: gold;
            color: black;
            padding: 8px 12px;
            border-radius: 5px;
        }
        .top-link:hover {
            background: #ffd700;
        }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>

    <h1>📋 Planes disponibles para Gimnasios</h1>

    <a href="agregar_plan.php" class="top-link">➕ Nuevo Plan</a>

    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Duración (días)</th>
                <th>Clientes Máx</th>
                <th>Precio ($)</th>
                <th>Acceso Panel</th>
                <th>Acceso Ventas</th>
                <th>Acceso Asistencias</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($fila['nombre']) ?></td>
                <td><?= $fila['duracion_dias'] ?></td>
                <td><?= $fila['limite_clientes'] ?></td>
                <td>$<?= number_format($fila['precio'], 2) ?></td>
                <td><?= $fila['acceso_panel'] ? '✅' : '❌' ?></td>
                <td><?= $fila['acceso_ventas'] ? '✅' : '❌' ?></td>
                <td><?= $fila['acceso_asistencias'] ? '✅' : '❌' ?></td>
                <td>
                    <a class="boton" href="editar_plan.php?id=<?= $fila['id'] ?>">✏️ Editar</a>
                    <a class="boton" href="eliminar_plan.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Seguro que deseas eliminar este plan?')">🗑️ Eliminar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

</body>
</html>
