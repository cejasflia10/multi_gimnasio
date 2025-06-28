<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso no autorizado.");
}

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $conexion->real_escape_string(trim($_POST['nombre']));
    $gimnasio_id = $_SESSION['gimnasio_id'];

    if (!empty($nombre)) {
        $conexion->query("INSERT INTO disciplinas (nombre, gimnasio_id) VALUES ('$nombre', $gimnasio_id)");
        $mensaje = "✅ Disciplina agregada correctamente.";
    } else {
        $mensaje = "❌ El nombre de la disciplina no puede estar vacío.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .form-container {
            max-width: 500px;
            margin: auto;
            background-color: #1c1c1c;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            background-color: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: gold;
            color: #111;
            border: none;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background-color: #e5c100;
        }
        .mensaje {
            text-align: center;
            margin-bottom: 10px;
            color: lightgreen;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>➕ Agregar Nueva Disciplina</h2>

        <?php if ($mensaje): ?>
            <div class="mensaje"><?= $mensaje ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Nombre de la disciplina:</label>
            <input type="text" name="nombre" required>
            <button type="submit">Guardar</button>
        </form>
    </div>
</body>
</html>
