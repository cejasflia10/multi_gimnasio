<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Nuevo Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 30px;
            margin: 0;
        }
        h1 {
            text-align: center;
            color: gold;
            margin-bottom: 30px;
        }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"],
        input[type="number"] {
            width: 100%;
            padding: 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            background-color: #333;
            color: #fff;
            font-size: 16px;
        }
        button {
            background-color: gold;
            color: #000;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            font-size: 16px;
        }
        button:hover {
            background-color: #ffd700;
        }
        .volver {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: gold;
            text-decoration: none;
        }
        .volver:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Agregar Nuevo Plan</h1>

    <form action="guardar_plan.php" method="POST">
        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" required>

        <label for="precio">Precio:</label>
        <input type="number" name="precio" required step="0.01">

        <label for="dias_disponibles">Días disponibles:</label>
        <input type="number" name="dias_disponibles" required>

        <label for="duracion">Duración (meses):</label>
        <input type="number" name="duracion" required>

        <button type="submit">Guardar</button>
    </form>

    <a class="volver" href="planes.php">← Volver</a>
</body>
</html>
