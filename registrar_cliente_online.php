
<?php
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function calcularEdad($fecha_nacimiento) {
    $hoy = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $hoy->diff($nacimiento);
    return $edad->y;
}

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = trim($_POST["apellido"] ?? '');
    $nombre = trim($_POST["nombre"] ?? '');
    $dni = trim($_POST["dni"] ?? '');
    $fecha_nacimiento = $_POST["fecha_nacimiento"] ?? '';
    $domicilio = trim($_POST["domicilio"] ?? '');
    $telefono = trim($_POST["telefono"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $rfid = trim($_POST["rfid"] ?? '');
    $disciplina = trim($_POST["disciplina"] ?? '');
    $fecha_vencimiento = $_POST["fecha_vencimiento"] ?? '';
    $dias_disponibles = intval($_POST["dias_disponibles"] ?? 0);
    $edad = calcularEdad($fecha_nacimiento);
    $fecha_ingreso = date("Y-m-d");

    $check = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $check->bind_param("s", $dni);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $mensaje = "<p style='color: orange;'>⚠️ Ya existe un cliente con ese DNI.</p>";
    } else {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, disciplina, fecha_vencimiento, dias_disponibles, fecha_ingreso) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissssssss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $disciplina, $fecha_vencimiento, $dias_disponibles, $fecha_ingreso);

        if ($stmt->execute()) {
            $mensaje = "<p style='color: lime;'>✅ Registro exitoso.</p>";
        } else {
            $mensaje = "<p style='color: red;'>❌ Error: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
    $check->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #FFD700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .form-container {
            max-width: 500px;
            margin: 60px auto;
            padding: 20px;
            background-color: #222;
            border-radius: 10px;
            box-shadow: 0 0 15px #000;
        }
        h2 {
            text-align: center;
            color: #FFD700;
        }
        label {
            display: block;
            margin-top: 10px;
            color: #FFD700;
        }
        input[type="text"],
        input[type="date"],
        input[type="email"],
        input[type="number"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background-color: #333;
            color: white;
            border: none;
            border-radius: 5px;
        }
        button {
            margin-top: 20px;
            background-color: #FFD700;
            color: black;
            border: none;
            padding: 12px;
            width: 100%;
            font-weight: bold;
            border-radius: 6px;
        }
        .mensaje {
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="form-container">
        <h2>Registro de Cliente</h2>
        <form method="POST">
            <label>Apellido:</label>
            <input type="text" name="apellido" required>
            <label>Nombre:</label>
            <input type="text" name="nombre" required>
            <label>DNI:</label>
            <input type="text" name="dni" required>
            <label>Fecha de Nacimiento:</label>
            <input type="date" name="fecha_nacimiento" required>
            <label>Domicilio:</label>
            <input type="text" name="domicilio" required>
            <label>Teléfono:</label>
            <input type="text" name="telefono">
            <label>Email:</label>
            <input type="email" name="email">
            <label>RFID:</label>
            <input type="text" name="rfid" required>
            <label>Disciplina:</label>
            <input type="text" name="disciplina" required>
            <label>Fecha de Vencimiento del Plan:</label>
            <input type="date" name="fecha_vencimiento" required>
            <label>Días disponibles:</label>
            <input type="number" name="dias_disponibles" required>

            <button type="submit">Registrar Cliente</button>
        </form>
        <div class="mensaje">
            <?php echo $mensaje; ?>
        </div>
    </div>
</body>
</html>
