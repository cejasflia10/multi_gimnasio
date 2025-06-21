<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR - MultiGimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding-top: 80px;
        }
        input, button {
            padding: 10px;
            font-size: 16px;
            margin: 10px;
            border-radius: 5px;
        }
        input {
            width: 200px;
        }
        button {
            background-color: #FFD700;
            color: #111;
            border: none;
            cursor: pointer;
        }
        h2 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h2>Generar Código QR para Cliente</h2>
    <form method="POST" action="generar_qr.php">
        <input type="text" name="dni" placeholder="Ingresar DNI" required>
        <br>
        <button type="submit">Generar QR</button>
    </form>
</body>
</html>
