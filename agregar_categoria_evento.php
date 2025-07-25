<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_eventos.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST['nombre'];
    $peso_min = $_POST['peso_min'];
    $peso_max = $_POST['peso_max'];
    $genero = $_POST['genero'];
    $edad_min = $_POST['edad_min'];
    $edad_max = $_POST['edad_max'];

    $conexion->query("INSERT INTO categorias_evento (nombre, peso_min, peso_max, genero, edad_min, edad_max) 
        VALUES ('$nombre', $peso_min, $peso_max, '$genero', $edad_min, $edad_max)");
    header("Location: ver_categorias_evento.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Categoría</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h2>➕ Nueva Categoría de Evento</h2>
        <form method="POST">
            <label>Nombre:</label>
            <input type="text" name="nombre" required>

            <label>Peso Mínimo (kg):</label>
            <input type="number" step="0.01" name="peso_min" required>

            <label>Peso Máximo (kg):</label>
            <input type="number" step="0.01" name="peso_max" required>

            <label>Género:</label>
            <select name="genero">
                <option value="masculino">Masculino</option>
                <option value="femenino">Femenino</option>
                <option value="mixto">Mixto</option>
            </select>

            <label>Edad mínima:</label>
            <input type="number" name="edad_min" required>

            <label>Edad máxima:</label>
            <input type="number" name="edad_max" required>

            <button type="submit" class="btn-principal">Guardar</button>
            <a href="ver_categorias_evento.php" class="btn-secundario">Volver</a>
        </form>
    </div>
</body>
</html>
