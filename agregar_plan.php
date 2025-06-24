<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        h1 { text-align: center; }
        form { max-width: 400px; margin: 0 auto; }
        label, input { display: block; width: 100%; margin: 10px 0; }
        input[type="submit"], a.boton { background-color: gold; color: black; border: none; padding: 10px; cursor: pointer; text-align: center; display: inline-block; text-decoration: none; }
    </style>
</head>
<body>
    <h1>Nuevo Plan</h1>
    <form action="guardar_plan.php" method="POST">
        <label>Nombre del Plan:</label>
        <input type="text" name="nombre" required>

        <label>Precio:</label>
        <input type="number" name="precio" step="0.01" required>

        <label>Días disponibles:</label>
        <input type="number" name="dias" required>

        <label>Duración (en meses):</label>
        <input type="number" name="duracion" value="1" required>

        <input type="submit" value="Guardar">
    </form>
    <br>
    <a href="planes.php" class="boton">Volver</a>
</body>
</html>