<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Gimnasio</title>
    <style>
        body {
            background-color: #111;
            color: #ffc107;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 50px;
        }

        form {
            background-color: #222;
            padding: 30px;
            border-radius: 10px;
            display: inline-block;
            text-align: left;
            color: #fff;
        }

        input, button {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            background-color: #333;
            color: #fff;
            border: 1px solid #555;
            border-radius: 5px;
        }

        button {
            background-color: #ffc107;
            color: #111;
            font-weight: bold;
        }

        .mensaje {
            margin-top: 20px;
            font-weight: bold;
            color: #0f0;
        }

        .error {
            color: red;
        }
    </style>
</head>
<body>
    <h1>Agregar Nuevo Gimnasio</h1>

    <form method="POST" action="">
        <label>Nombre del gimnasio:</label>
        <input type="text" name="nombre" required>

        <label>Dirección:</label>
        <input type="text" name="direccion" required>

        <label>Email:</label>
        <input type="email" name="email">

        <button type="submit" name="guardar">Guardar Gimnasio</button>
    </form>

    <?php
    if (isset($_POST['guardar'])) {
        $nombre = trim($_POST['nombre']);
        $direccion = trim($_POST['direccion']);
        $email = trim($_POST['email']);

        if ($nombre != '' && $direccion != '') {
            $stmt = $conexion->prepare("INSERT INTO gimnasios (nombre, direccion, email) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $nombre, $direccion, $email);

            if ($stmt->execute()) {
                echo "<div class='mensaje'>✅ Gimnasio registrado correctamente.</div>";
            } else {
                echo "<div class='error'>❌ Error al registrar gimnasio: " . $stmt->error . "</div>";
            }

            $stmt->close();
        } else {
            echo "<div class='error'>⚠️ Todos los campos son obligatorios.</div>";
        }
    }
    ?>
</body>
</html>
