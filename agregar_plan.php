<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include "conexion.php";

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Plan</title>
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        input, label { display: block; margin: 10px 0; }
        button { background: gold; color: black; padding: 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
<h1>Agregar nuevo plan</h1>
<form method="post" action="guardar_plan.php">
    <label>Nombre del plan: <input type="text" name="nombre" required></label>
    <label>Precio: <input type="number" name="precio" required></label>
    <label>Días disponibles: <input type="number" name="dias_disponibles" required></label>
    <label>Duración (en meses): <input type="number" name="duracion" required value="1"></label>
    <input type="hidden" name="gimnasio_id" value="<?= $gimnasio_id ?>">
    <button type="submit">Guardar</button>
</form>
<a href="planes.php">Volver</a>
</body>
</html>