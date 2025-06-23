<?php
include 'conexion.php';
$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM planes WHERE id = $id");
$plan = $resultado->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Plan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; padding: 20px; font-family: Arial, sans-serif; }
        input, button { padding: 10px; margin: 5px; width: 100%; }
        label { display: block; margin-top: 10px; }
    </style>
</head>
<body>
    <h2>Editar Plan</h2>
    <form action="guardar_plan.php" method="POST">
        <input type="hidden" name="id" value="<?= $plan['id'] ?>">
        <label>Nombre: <input type="text" name="nombre" value="<?= $plan['nombre'] ?>" required></label>
        <label>Precio: <input type="number" name="precio" value="<?= $plan['precio'] ?>" step="0.01" required></label>
        <label>Días disponibles: <input type="number" name="dias_disponibles" value="<?= $plan['dias_disponibles'] ?>" required></label>
        <label>Duración (meses): <input type="number" name="duracion" value="<?= $plan['duracion'] ?>" required></label>
        <button type="submit">Guardar Cambios</button>
    </form>
    <a href="planes.php" class="boton">Volver</a>
</body>
</html>