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
        img.qr {
            width: 80px;
        }
        .boton-generar {
            background-color: gold;
            color: black;
            border: none;
            padding: 6px 12px;
            cursor: pointer;
            font-size: 14px;
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
                    $qr_filename = "qr/qr_profesor_P-" . $profesor['dni'] . ".png";
                    if (file_exists($qr_filename)) {
                        echo '<img class="qr" src="' . $qr_filename . '" alt="QR">';
                    } else {
                        echo '<a href="generar_qr_individual_profesor.php?id=' . $profesor['id'] . '">
                                <button class="boton-generar">Generar QR</button>
                              </a>';
                    }
                    ?>
                </td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
