<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso al Panel del Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: black;
            color: gold;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        input, button {
            padding: 10px;
            font-size: 18px;
            margin-top: 10px;
            border-radius: 5px;
        }
        .contenedor {
            margin-top: 100px;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>Acceso al Panel del Cliente</h1>
        <form action="login_cliente.php" method="POST">
            <input type="text" name="dni" placeholder="Ingresá tu DNI" required><br>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
