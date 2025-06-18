
<?php
include 'conexion.php';
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Online</title>
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 500px;
            margin: 40px auto;
            padding: 20px;
            background-color: #222;
            border-radius: 12px;
            box-shadow: 0 0 10px #000;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
            color: #FFD700;
        }
        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            color: #FFD700;
        }
        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            background-color: #333;
            color: #fff;
        }
        button {
            margin-top: 20px;
            width: 100%;
            padding: 12px;
            background-color: #FFD700;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            color: #000;
        }
        .mensaje {
            text-align: center;
            margin-top: 15px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Registro Online</h1>
        <form method="POST" action="registrar_cliente_online.php">
            <label for="apellido">Apellido:</label>
            <input type="text" name="apellido" id="apellido" required>

            <label for="nombre">Nombre:</label>
            <input type="text" name="nombre" id="nombre" required>

            <label for="dni">DNI:</label>
            <input type="text" name="dni" id="dni" required>

            <label for="fecha_nacimiento">Fecha de Nacimiento:</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" required>

            <button type="submit">Registrar</button>
        </form>

        <div class="mensaje">
            <?php
            if ($_SERVER["REQUEST_METHOD"] == "POST") {
                $apellido = trim($_POST["apellido"]);
                $nombre = trim($_POST["nombre"]);
                $dni = trim($_POST["dni"]);
                $fecha_nacimiento = $_POST["fecha_nacimiento"];
                $fecha_ingreso = date("Y-m-d");

                if (!empty($apellido) && !empty($nombre) && !empty($dni) && !empty($fecha_nacimiento)) {
                    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, fecha_ingreso) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $apellido, $nombre, $dni, $fecha_nacimiento, $fecha_ingreso);

                    if ($stmt->execute()) {
                        echo "<span style='color: lime;'>✅ Registro exitoso</span>";
                    } else {
                        echo "<span style='color: red;'>❌ Error al registrar: " . $stmt->error . "</span>";
                    }
                    $stmt->close();
                } else {
                    echo "<span style='color: orange;'>⚠️ Faltan datos obligatorios</span>";
                }
            }
            ?>
        </div>
    </div>
</body>
</html>
