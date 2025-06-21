<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR del Cliente</title>
    <style>
        body { background-color: #111; color: gold; text-align: center; font-family: Arial, sans-serif; }
        input, button { padding: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Generar QR del Cliente</h1>
    <form action="generar_qr.php" method="POST">
        <input type="text" name="dni" id="dni" placeholder="DNI del cliente" required>
        <br>
        <button type="submit">Generar QR</button>
    </form>
</body>
</html>
