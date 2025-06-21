<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'menu.php';
include 'conexion.php';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #111;
            color: #f1f1f1;
            margin: 0;
            padding: 0;
        }

        .contenido {
            margin-left: 240px;
            padding: 20px;
        }

        h1 {
            color: gold;
            font-size: 1.8rem;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .contenido {
                margin-left: 0;
                padding: 10px;
            }

            h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <div class="contenido">
        <h1>Bienvenido al Panel de Control</h1>

        <?php include 'asistencias_index.php'; ?>
    </div>
</body>
</html>
