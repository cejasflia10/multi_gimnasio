<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include("conexion.php");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($gimnasio_id <= 0) {
    die("No hay un gimnasio logueado.");
}

$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $fecha_nac = $_POST['fecha_nacimiento'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $rfid = $_POST['rfid'];
    $disciplina = $_POST['disciplina'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];

    // Calcular edad
    $edad = date_diff(date_create($fecha_nac), date_create('today'))->y;

    // Verificar DNI duplicado
    $consulta = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $consulta->bind_param("s", $dni);
    $consulta->execute();
    $consulta->store_result();

    if ($consulta->num_rows > 0) {
        $mensaje = "Ya existe un cliente con ese DNI.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, fecha_vencimiento, disciplina, gimnasio_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssisssssii", $apellido, $nombre, $dni, $fecha_nac, $edad, $domicilio, $telefono, $email, $rfid, $fecha_vencimiento, $disciplina, $gimnasio_id);
        if ($stmt->execute()) {
            $mensaje = "Cliente registrado correctamente.";
        } else {
            $mensaje = "Error al registrar el cliente.";
        }
        $stmt->close();
    }

    $consulta->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Online de Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            text-align: center;
        }
        form {
            max-width: 600px;
            margin: auto;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 8px;
            background-color: #222;
            border: 1px solid gold;
            color: gold;
        }
        button {
            margin-top: 20px;
            background-color: gold;
            color: black;
            padding: 10px;
            width: 100%;
            border: none;
            font-weight: bold;
        }
        .mensaje {
            text-align: center;
            margin-top: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>

<h2>Registro Online de Cliente</h2>

<?php if (!empty($mensaje)): ?>
    <div class="mensaje"><?= $mensaje ?></div>
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

    <label>RFID:</label>
    <input type="text" name="rfid" required>

    <label>Fecha de vencimiento:</label>
    <input type="date" name="fecha_vencimiento" required>

    <label>Disciplina:</label>
    <select name="disciplina" required>
        <option value="">Seleccione una</option>
        <option value="Boxeo">Boxeo</option>
        <option value="Kickboxing">Kickboxing</option>
        <option value="MMA">MMA</option>
    </select>

    <button type="submit">Registrar Cliente</button>
</form>

</body>
</html>
