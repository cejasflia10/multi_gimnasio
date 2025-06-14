<?php include 'verificar_sesion.php'; ?>

<?php
include 'conexion.php';

$resultado = $conexion->query("
    SELECT id, nombre, email, rol
    FROM usuarios_gimnasio
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios del Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h1 {
            color: #ffc107;
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 90%;
            margin: auto;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th, td {
            padding: 10px;
            text-align: center;
            border: 1px solid #444;
        }

        th {
            background-color: #222;
            color: #ffc107;
        }

        tr:nth-child(even) {
            background-color: #1a1a1a;
        }

        a.editar {
            color: #00bcd4;
            text-decoration: none;
            font-weight: bold;
        }

        a.editar:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Usuarios del Gimnasio</h1>

    <table>
        <tr>
            <th>Nombre</th>
            <th>Email</th>
            <th>Rol</th>
            <th>Acciones</th>
        </tr>
        <?php while ($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row['nombre']) ?></td>
            <td><?= htmlspecialchars($row['email']) ?></td>
            <td><?= ucfirst($row['rol']) ?></td>
            <td><a class="editar" href="editar_usuario.php?id=<?= $row['id'] ?>">✏️ Editar</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
