<?php include 'verificar_sesion.php'; ?>
<?php
session_start();
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gimnasios</title>
    <style>
        body { background: #111; color: #fff; font-family: Arial; margin: 0; padding-left: 240px; }
        .container { padding: 30px; }
        h1 { color: #ffc107; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #333; }
        th { background-color: #222; color: #ffc107; }
        tr:hover { background-color: #222; }
        a.btn { padding: 5px 10px; background: #ffc107; color: #111; text-decoration: none; border-radius: 5px; }
        a.btn:hover { background: #e0a800; }
    </style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="container">
    <h1>Gimnasios registrados</h1>
    <a href="agregar_gimnasio.php" class="btn">➕ Agregar Gimnasio</a>
    <table>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Dirección</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Logo</th>
            <th>Acciones</th>
        </tr>
        <?php
        $result = $conexion->query("SELECT * FROM gimnasios");
        while ($row = $result->fetch_assoc()):
        ?>
        <tr>
            <td><?= $row['id'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['direccion'] ?></td>
            <td><?= $row['email'] ?></td>
            <td><?= $row['telefono'] ?></td>
            <td><img src="<?= $row['logo'] ?>" alt="logo" width="50"></td>
            <td>
                <a class="btn" href="editar_gimnasio.php?id=<?= $row['id'] ?>">✏️ Editar</a>
                <a class="btn" href="eliminar_gimnasio.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este gimnasio?')">🗑️ Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
