<?php
include "conexion.php";

function calcularEdad($fechaNacimiento) {
    $fecha_nac = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    return $hoy->diff($fecha_nac)->y;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rfid_uid = trim($_POST['rfid_uid'] ?? null);
    $edad = calcularEdad($fecha_nacimiento);

    if ($apellido === '' || $nombre === '' || $dni === '' || $fecha_nacimiento === '' || $domicilio === '' || $telefono === '' || $email === '') {
        $mensaje = "Faltan datos obligatorios.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);
        if ($stmt->execute()) {
            $mensaje = "Registro exitoso. ¡Gracias por registrarte!";
        } else {
            $mensaje = "Error al registrar: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        form {
            max-width: 500px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        input, label {
            width: 100%;
            display: block;
            margin-bottom: 10px;
        }
        input[type="text"], input[type="date"], input[type="email"], input[type="number"] {
            background: #333;
            color: #FFD700;
            border: 1px solid #FFD700;
            padding: 8px;
            border-radius: 5px;
        }
        input[type="submit"] {
            background-color: #FFD700;
            color: #111;
            font-weight: bold;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }
        .mensaje {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h2>Registro de Cliente - Fight Academy</h2>
    <?php if (!empty($mensaje)): ?>
        <p class="mensaje"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>Apellido:</label>
        <input type="text" name="apellido" required>

        <label>Nombre:</label>
        <input type="text" name="nombre" required>

        <label>DNI:</label>
        <input type="text" name="dni" required>

        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" required>

        <label>Domicilio:</label>
        <input type="text" name="domicilio" required>

        <label>Teléfono:</label>
        <input type="text" name="telefono" required>

        <label>Email:</label>
        <input type="email" name="email" required>

        <label>RFID (opcional):</label>
        <input type="text" name="rfid_uid">

        <input type="submit" value="Registrarme">
    </form>
</body>
</html>