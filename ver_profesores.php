<?php
include 'conexion.php';
session_start();

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM profesores WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        .qr-img {
            width: 80px;
        }
        .boton {
            background-color: gold;
            color: black;
            border: none;
            padding: 6px 10px;
            cursor: pointer;
            margin: 3px;
            font-size: 14px;
        }
        .acciones {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
    </style>
</head>
<body>
    <h1>Listado de Profesores</h1>
    <table>
        <tr>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>DNI</th>
            <th>Teléfono</th>
            <th>QR</th>
            <th>Acciones</th>
        </tr>
        <?php while ($profesor = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($profesor['apellido']) ?></td>
                <td><?= htmlspecialchars($profesor['nombre']) ?></td>
                <td><?= $profesor['dni'] ?></td>
                <td><?= $profesor['telefono'] ?></td>
                <td>
                    <img class="qr-img" src="generar_qr_profesor.php?dni=<?= $profesor['dni'] ?>&t=<?= time() ?>" alt="QR"><br>
                    <a href="generar_qr_profesor.php?dni=<?= $profesor['dni'] ?>" target="_blank">
                        <button class="boton">Ver QR</button>
                    </a>
                    <a href="ver_profesores.php">
                        <button class="boton">Generar QR</button>
                    </a>
                </td>
                <td class="acciones">
                    <a href="editar_profesor.php?id=<?= $profesor['id'] ?>">
                        <button class="boton">Editar</button>
                    </a>
                    <a href="eliminar_profesor.php?id=<?= $profesor['id'] ?>" onclick="return confirm('¿Eliminar este profesor?')">
                        <button class="boton">Eliminar</button>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
