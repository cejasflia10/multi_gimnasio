<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']);
    $id_gimnasio = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("INSERT INTO disciplinas (nombre, descripcion, id_gimnasio) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nombre, $descripcion, $id_gimnasio);

    if ($stmt->execute()) {
        echo "<script>alert('Disciplina creada correctamente.'); window.location.href='disciplinas.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 30px;
        }

        form {
            background-color: #222;
            padding: 20px;
            border-radius: 15px;
            max-width: 500px;
            margin: auto;
            box-shadow: 0 0 15px rgba(255, 215, 0, 0.2);
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #333;
            color: #fff;
            border: 1px solid gold;
            border-radius: 8px;
        }

        button {
            margin-top: 20px;
            background-color: gold;
            color: #111;
            padding: 12px;
            border: none;
            width: 100%;
            border-radius: 10px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background-color: #e0b800;
        }
    </style>
</head>
<body>
    <h2>Registrar Nueva Disciplina</h2>
    <form method="POST">
        <label for="nombre">Nombre de la disciplina:</label>
        <input type="text" name="nombre" id="nombre" required>

        <label for="descripcion">Descripción (opcional):</label>
        <textarea name="descripcion" id="descripcion" rows="4"></textarea>

        <button type="submit">Guardar Disciplina</button>
    </form>
</body>
</html>
