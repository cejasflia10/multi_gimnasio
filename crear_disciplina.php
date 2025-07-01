<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 400px; margin: auto; }
        input[type="text"] { width: 100%; padding: 10px; margin-top: 10px; background-color: #222; color: white; border: 1px solid gold; }
        input[type="submit"] { background-color: gold; color: black; padding: 10px 20px; margin-top: 20px; font-weight: bold; border: none; cursor: pointer; }
    </style>
</head>
<script src="fullscreen.js"></script>

<body>
    <h2 style="text-align:center;">Agregar Nueva Disciplina</h2>
    <form action="guardar_disciplina.php" method="POST">
        <label>Nombre de la Disciplina:</label>
        <input type="text" name="nombre" required>
        <input type="submit" value="Guardar">
    </form>
</body>
</html>
