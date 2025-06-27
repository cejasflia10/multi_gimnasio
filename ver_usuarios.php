<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

$resultado = $conexion->query("SELECT u.id, u.usuario, u.rol, g.nombre AS gimnasio 
                               FROM usuarios u 
                               LEFT JOIN gimnasios g ON u.gimnasio_id = g.id 
                               ORDER BY u.id DESC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Sistema</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            padding: 12px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        a.boton {
            background-color: gold;
            color: black;
            padding: 10px 15px;
            text-decoration: none;
            margin: 10px 0;
            display: inline-block;
            border-radius: 5px;
            font-weight: bold;
        }
        .acciones a {
            margin-right: 10px;
            color: gold;
            text-decoration: underline;
        }
    </style>
</head>
<body>

    <h2>Usuarios del Sistema</h2>

    <a href="agregar_usuario.php" class="boton">➕ Agregar Nuevo Usuario</a>

    <table>
        <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Gimnasio</th>
            <th>Acciones</th>
        </tr>
        <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $fila['id'] ?></td>
            <td><?= htmlspecialchars($fila['usuario']) ?></td>
            <td><?= $fila['rol'] ?></td>
            <td><?= htmlspecialchars($fila['gimnasio'] ?? '') ?></td>
            <td class="acciones">
                <a href="editar_usuario.php?id=<?= $fila['id'] ?>">✏️ Editar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>

</body>
</html>
