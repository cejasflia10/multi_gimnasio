<?php
include 'conexion.php';
include 'menu.php';

$consulta = "SELECT u.id, u.nombre_usuario, u.rol, u.email, g.nombre AS gimnasio
             FROM usuarios u
             LEFT JOIN gimnasios g ON u.id_gimnasio = g.id";
$resultado = $conexion->query($consulta);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios por Gimnasio</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #f1f1f1;
            margin: 0;
            padding: 60px 20px 20px 240px;
        }
        h2 {
            color: #ffc107;
            text-align: center;
            margin-bottom: 30px;
        }
        .tabla-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #222;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
            text-align: left;
        }
        th {
            background-color: #333;
            color: #ffc107;
        }
        .btn {
            padding: 4px 8px;
            text-decoration: none;
            border-radius: 4px;
            color: white;
        }
        .btn-warning { background-color: #ffc107; color: #000; }
        .btn-danger { background-color: #dc3545; }
    </style>
</head>
<body>
    <h2>Usuarios por Gimnasio</h2>
    <div class="tabla-container">
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Rol</th>
                    <th>Gimnasio</th>
                    <th>Email</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                while ($fila = $resultado->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($fila['nombre_usuario']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['rol']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['gimnasio']) . "</td>";
                    echo "<td>" . htmlspecialchars($fila['email']) . "</td>";
                    echo "<td style='text-align:center;'>
                            <a href='editar_usuario.php?id=" . $fila['id'] . "' class='btn btn-warning btn-sm'>Editar</a>
                            <a href='eliminar_usuario.php?id=" . $fila['id'] . "' class='btn btn-danger btn-sm' onclick=\"return confirm('¿Estás seguro de eliminar este usuario?')\">Eliminar</a>
                          </td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</body>
</html>
