<?php include 'conexion.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; padding: 20px; font-family: Arial, sans-serif; }
        input, button { padding: 10px; margin: 5px; width: 100%; }
        label { display: block; margin-top: 10px; }
    </style>
</head>
<body>
    <h2>Agregar Nuevo Plan</h2>
    <form action="guardar_plan.php" method="POST">
        <label>Nombre: <input type="text" name="nombre" required></label>
        <label>Precio: <input type="number" name="precio" required step="0.01"></label>
        <label>Días disponibles: <input type="number" name="dias_disponibles" required></label>
        <label>Duración (meses): <input type="number" name="duracion" required></label>
        <button type="submit">Guardar</button>
    </form>
    <a href="planes.php" class="boton">Volver</a>
</body>
</html>