<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Online - Fight Academy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        input, select, button {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            box-sizing: border-box;
            font-size: 16px;
            border-radius: 5px;
            border: none;
        }
        button {
            background-color: gold;
            color: #111;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover {
            background-color: #e0b000;
        }
        @media (max-width: 600px) {
            .container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registro Online</h1>
        <form method="POST" action="registrar_cliente_online.php">
            <input type="text" name="apellido" placeholder="Apellido" required>
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="text" name="dni" placeholder="DNI" required>
            <label>Fecha de Nacimiento:</label>
            <input type="date" name="fecha_nacimiento" required>
            <button type="submit">Registrar</button>
        </form>
    </div>
</body>
</html>

<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = trim($_POST["apellido"] ?? '');
    $nombre = trim($_POST["nombre"] ?? '');
    $dni = trim($_POST["dni"] ?? '');
    $fecha_nac = trim($_POST["fecha_nacimiento"] ?? '');

    if ($apellido && $nombre && $dni && $fecha_nac) {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $apellido, $nombre, $dni, $fecha_nac);

        if ($stmt->execute()) {
            echo "<p style='color:lightgreen;text-align:center;'>✅ Cliente registrado correctamente.</p>";
        } else {
            echo "<p style='color:red;text-align:center;'>❌ Error al registrar: " . $stmt->error . "</p>";
        }
    } else {
        echo "<p style='color:orange;text-align:center;'>⚠️ Faltan datos obligatorios (apellido, nombre, DNI, fecha nacimiento)</p>";
    }
}
?>