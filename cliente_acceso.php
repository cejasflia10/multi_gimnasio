<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .formulario {
            background-color: #1c1c1c;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        h2 {
            margin-bottom: 20px;
            color: #fff;
        }
        input[type="text"] {
            width: 90%;
            padding: 12px;
            margin-bottom: 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
        }
        button {
            background-color: gold;
            color: #111;
            padding: 12px 20px;
            border: none;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
        }
        button:hover {
            background-color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="formulario">
        <h2>Acceso de Cliente</h2>
        <form action="panel_cliente.php" method="GET">
            <input type="text" name="dni" placeholder="Ingresá tu DNI" required>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
