<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generar QR</title>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
        }
        input[type="text"], input[type="submit"] {
            padding: 10px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Generar QR del Cliente</h1>
    <form action="generar_qr.php" method="POST">
        <input type="text" name="dni" placeholder="Ingrese el DNI">
        <input type="submit" value="Generar QR">
    </form>
</body>
</html>
