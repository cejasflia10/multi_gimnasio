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
    <title>Ver Profesores</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
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
        canvas.qr {
            width: 80px;
            height: 80px;
        }
        .boton {
            background-color: gold;
            color: black;
            border: none;
            padding: 6px 12px;
            margin: 2px;
            cursor: pointer;
            font-size: 14px;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/qrious/dist/qrious.min.js"></script>
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
                    <canvas class="qr" id="qr_<?= $profesor['dni'] ?>"></canvas>
                    <script>
                        new QRious({
                            element: document.getElementById("qr_<?= $profesor['dni'] ?>"),
                            value: "P-<?= $profesor['dni'] ?>",
                            size: 80,
                            level: 'H'
                        });
                    </script>
                </td>
                <td>
                    <a href="editar_profesor.php?id=<?= $profesor['id'] ?>">
                        <button class="boton">Editar</button>
                    </a>
                    <a href="eliminar_profesor.php?id=<?= $profesor['id'] ?>" onclick="return confirm('¿Estás seguro de eliminar este profesor?');">
                        <button class="boton">Eliminar</button>
                    </a>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
