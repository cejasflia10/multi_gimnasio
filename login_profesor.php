<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = $_POST['dni'] ?? '';

    if (!empty($dni)) {
        $stmt = $conexion->prepare("SELECT id, apellido, nombre FROM profesores WHERE dni = ?");
        $stmt->bind_param("s", $dni);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $profesor = $resultado->fetch_assoc();
            $_SESSION['profesor_id'] = $profesor['id'];
            $_SESSION['profesor_nombre'] = $profesor['apellido'] . ' ' . $profesor['nombre'];

            header("Location: panel_profesor.php");
            exit;
        } else {
            $mensaje = "DNI no encontrado.";
        }
    } else {
        $mensaje = "Por favor ingrese su DNI.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso Profesor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }

        h2 {
            margin-bottom: 20px;
        }

        form {
            background-color: #111;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }

        input[type="text"] {
            padding: 10px;
            font-size: 18px;
            width: 250px;
            margin-bottom: 15px;
        }

        input[type="submit"] {
            background-color: gold;
            color: black;
            font-weight: bold;
            padding: 10px 20px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }

        .mensaje {
            color: red;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <h2>Ingreso al Panel del Profesor</h2>
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese su DNI" required>
        <br>
        <input type="submit" value="Ingresar">
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
</body>
</html>
