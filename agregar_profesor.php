<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Error: sesión no iniciada.");
}
include 'conexion.php';
include 'menu_horizontal.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = trim($_POST["apellido"]);
    $nombre = trim($_POST["nombre"]);
    $domicilio = trim($_POST["domicilio"]);
    $telefono = trim($_POST["telefono"]);
    $rfid = trim($_POST["rfid"]);
    $gimnasio_id = $_SESSION["gimnasio_id"];

    $stmt = $conexion->prepare("INSERT INTO profesores (apellido, nombre, domicilio, telefono, rfid, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $apellido, $nombre, $domicilio, $telefono, $rfid, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Profesor agregado correctamente'); window.location.href='ver_profesores.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: 'Segoe UI', sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        form {
            background-color: #1a1a1a;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
            width: 100%;
            max-width: 500px;
        }

        h2 {
            color: #f7d774;
            margin-bottom: 20px;
            text-align: center;
        }

        label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #222;
            color: #f1f1f1;
            border: 1px solid #555;
            border-radius: 5px;
        }

        .botones {
            margin-top: 20px;
            display: flex;
            justify-content: space-between;
        }

        button, a {
            background-color: #f7d774;
            color: #111;
            padding: 10px 20px;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-weight: bold;
        }

        button:hover, a:hover {
            background-color: #ffe700;
        }
    </style>
</head>
<body>

<form method="POST" action="">
    <h2>Agregar Profesor</h2>
    <label>Apellido</label>
    <input type="text" name="apellido" required>

    <label>Nombre</label>
    <input type="text" name="nombre" required>

    <label>Domicilio</label>
    <input type="text" name="domicilio" required>

    <label>Teléfono</label>
    <input type="text" name="telefono" required>

    <label>RFID</label>
    <input type="text" name="rfid" required>

    <div class="botones">
        <button type="submit">Guardar</button>
        <a href="index.php">Volver al menú</a>
    </div>
</form>

</body>
</html>
