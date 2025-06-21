<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    $_SESSION['gimnasio_id'] = 1; // solo para test
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR</title>
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            text-align: center;
            padding-top: 80px;
            font-family: Arial, sans-serif;
        }
        input, button {
            padding: 10px;
            font-size: 18px;
            margin: 10px;
        }
    </style>
</head>
<body>
    <h2>Generar QR del Cliente</h2>
    <form method="POST" action="generar_qr.php">
        <input type="text" name="dni" placeholder="Ingrese DNI" required><br>
        <button type="submit">Generar QR</button>
    </form>
</body>
</html>
