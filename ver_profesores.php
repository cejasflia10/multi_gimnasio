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
        h1 {
            text-align: center;
            font-size: 24px;
            margin-bottom: 20px;
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
        img.qr {
            width: 80px;
        }
        .boton-generar, .boton-ver {
            background-color: gold;
            color: black;
            border: none;
            padding: 6px 10px;
            margin-top: 5px;
            cursor: pointer;
            font-size: 14px;
            border-radius: 4px;
        }
        .boton-ver {
            background-color: #222;
            color: gold;
            border: 1px solid gold;
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
        </tr>
        <?php while ($profesor = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($profesor['apellido']) ?></td>
                <td><?= htmlspecialchars($profesor['nombre']) ?></td>
                <td><?= $profesor['dni'] ?></td>
                <td><?= $profesor['telefono'] ?></td>
                <td>
                    <?php
                    // Generar el QR dinámicamente cada vez
                    $qr_code = 'P-' . $profesor['dni'];
                    echo '<img class="qr" src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qr_code) . '" alt="QR"><br>';
                    echo '<a href="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qr_code) . '" target="_blank">
                            <button class="boton-ver">Ver QR</button>
                          </a>';
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
