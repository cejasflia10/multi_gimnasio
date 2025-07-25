<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_evento.php';

$id = $_GET['id'];
$categoria = $conexion->query("SELECT * FROM categorias_evento WHERE id = $id")->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST['nombre'];
    $peso_min = $_POST['peso_min'];
    $peso_max = $_POST['peso_max'];
    $genero = $_POST['genero'];
    $edad_min = $_POST['edad_min'];
    $edad_max = $_POST['edad_max'];

    $conexion->query("UPDATE categorias_evento SET 
        nombre = '$nombre',
        peso_min = $peso_min,
        peso_max = $peso_max,
        genero = '$genero',
        edad_min = $edad_min,
        edad_max = $edad_max
        WHERE id = $id");

    header("Location: ver_categorias_evento.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
        <h2>✏️ Editar Categoría de Evento</h2>
        <form method="POST">
            <label>Nombre:</label>
            <input type="text" name="nombre" value="<?= $categoria['nombre'] ?>" required>

            <label>Peso Mínimo:</label>
            <input type="number" step="0.01" name="peso_min" value="<?= $categoria['peso_min'] ?>" required>

            <label>Peso Máximo:</label>
            <input type="number" step="0.01" name="peso_max" value="<?= $categoria['peso_max'] ?>" required>

            <label>Género:</label>
            <select name="genero">
                <option value="masculino" <?= $categoria['genero'] == 'masculino' ? 'selected' : '' ?>>Masculino</option>
                <option value="femenino" <?= $categoria['genero'] == 'femenino' ? 'selected' : '' ?>>Femenino</option>
                <option value="mixto" <?= $categoria['genero'] == 'mixto' ? 'selected' : '' ?>>Mixto</option>
            </select>

            <label>Edad mínima:</label>
            <input type="number" name="edad_min" value="<?= $categoria['edad_min'] ?>" required>

            <label>Edad máxima:</label>
            <input type="number" name="edad_max" value="<?= $categoria['edad_max'] ?>" required>

            <button type="submit" class="btn-principal">Actualizar</button>
            <a href="ver_categorias_evento.php" class="btn-secundario">Cancelar</a>
        </form>
    </div>
</body>
</html>
