<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; }
        form { background-color: #222; padding: 30px; border-radius: 10px; width: 90%; max-width: 400px; }
        label { font-weight: bold; display: block; margin-top: 15px; }
        input { width: 100%; padding: 10px; margin-top: 5px; background-color: #333; border: none; border-radius: 6px; color: white; }
        button { margin-top: 20px; width: 100%; padding: 10px; background-color: gold; color: black; border: none; border-radius: 8px; font-weight: bold; cursor: pointer; }
        button:hover { background-color: orange; }
    </style>
</head>
<body>
    <form action="guardar_plan.php" method="POST">
        <h2 style="text-align:center;">Agregar Nuevo Plan</h2>
        <label>Nombre del plan:</label>
        <input type="text" name="nombre" required>

        <label>Precio ($):</label>
        <input type="number" name="precio" step="0.01" required>

        <label>Cantidad de clases:</label>
        <input type="number" name="clases" required>

        <label>Duración (en meses):</label>
        <input type="number" name="duracion_meses" required>

        <button type="submit">Guardar Plan</button>
    </form>
</body>
</html>
