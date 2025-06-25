<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion'] ?? '');
    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("INSERT INTO disciplinas (nombre, descripcion, id_gimnasio) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nombre, $descripcion, $gimnasio_id);
    $stmt->execute();

    echo "<script>alert('Disciplina agregada correctamente'); window.location.href='disciplinas.php';</script>";
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
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin: 0;
        }

        h2 {
            text-align: center;
        }

        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input[type="text"], textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid gold;
            border-radius: 5px;
            background-color: #333;
            color: gold;
        }

        button {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
        }

        @media (max-width: 600px) {
            form {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <h2>Nueva Disciplina</h2>
    <form method="POST">
        <label for="nombre">Nombre:</label>
        <input type="text" name="nombre" id="nombre" required>

        <label for="descripcion">Descripción:</label>
        <textarea name="descripcion" id="descripcion" rows="4"></textarea>

        <button type="submit">Guardar</button>
    </form>
</body>
</html>
