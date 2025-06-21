
<?php
session_start();
include 'conexion.php';
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
$resultado = $conexion->query("SELECT * FROM profesores WHERE gimnasio_id = $gimnasio_id");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: 'Segoe UI', sans-serif;
            padding: 20px;
        }
        h1 {
            color: #f7d774;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1a1a1a;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            border: 1px solid #333;
            text-align: left;
        }
        th {
            background-color: #222;
            color: #f7d774;
        }
        .action {
            color: #f7d774;
            text-decoration: none;
            margin-right: 10px;
            font-size: 1.2em;
        }
        .action:hover {
            color: #fff;
        }
        a.boton-volver {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #f7d774;
            color: #111;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        }
        a.boton-volver:hover {
            background-color: #ffe700;
        }
    </style>
</head>
<body>

<h1>Listado de Profesores</h1>

<table>
    <thead>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Domicilio</th>
            <th>Teléfono</th>
            <th>RFID</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
        <?php while($row = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $row['apellido'] ?></td>
            <td><?= $row['nombre'] ?></td>
            <td><?= $row['domicilio'] ?></td>
            <td><?= $row['telefono'] ?></td>
            <td><?= $row['rfid'] ?></td>
            <td>
                <a class="action" href="editar_profesor.php?id=<?= $row['id'] ?>">✏️</a>
                <a class="action" href="eliminar_profesor.php?id=<?= $row['id'] ?>" onclick="return confirm('¿Eliminar este profesor?')">🗑️</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<a class="boton-volver" href="index.php">Volver al menú</a>

</body>
</html>
