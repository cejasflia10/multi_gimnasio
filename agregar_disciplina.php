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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .form-container {
            max-width: 600px;
            margin: 80px auto;
            background-color: #1c1c1c;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="text"] {
            width: 100%;
            padding: 12px;
            background-color: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
            margin-bottom: 16px;
        }
        button {
            width: 100%;
            padding: 12px;
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
            margin-bottom: 15px;
            font-weight: bold;
        }
        @media screen and (max-width: 600px) {
            .form-container {
                margin: 20px;
                padding: 15px;
            }
            input, button {
                font-size: 16px;
            }
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
            <input type="text" name="nombre" placeholder="Ej. Kickboxing, MMA..." required>
            <button type="submit"><i class="fas fa-save"></i> Guardar</button>
        </form>
    </div>
</body>
</html>
