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
        <link rel="stylesheet" href="estilo_unificado.css">
    <meta charset="UTF-8">
    <title>Nueva Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
</head>

<body>
    <div class="contenedor">
    <h2 style="text-align:center;">Agregar Nueva Disciplina</h2>
    <form action="guardar_disciplina.php" method="POST">
        <label>Nombre de la Disciplina:</label>
        <input type="text" name="nombre" required>
        <input type="submit" value="Guardar">
    </form>
    </div>
</body>
</html>
