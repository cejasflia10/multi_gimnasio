<?php
include 'conexion.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Tomamos el gimnasio desde la URL
$gimnasio_id = isset($_GET['gimnasio']) ? intval($_GET['gimnasio']) : 0;
if ($gimnasio_id === 0) {
    die("Gimnasio no especificado.");
}

$error = '';
$exito = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nac = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $rfid = $_POST["rfid"] ?? '';
    $disciplina = $_POST["disciplina"];
    $vencimiento = $_POST["fecha_vencimiento"];

    $verificar = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $verificar->bind_param("s", $dni);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        $error = "Ya existe un cliente con ese DNI.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, rfid, disciplina, fecha_vencimiento, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssi", $apellido, $nombre, $dni, $fecha_nac, $domicilio, $telefono, $email, $rfid, $disciplina, $vencimiento, $gimnasio_id);

        if ($stmt->execute()) {
            $exito = "Cliente registrado exitosamente.";
        } else {
            $error = "Error al registrar cliente.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Online</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
<div class="contenedor">
    <h2>Registro de Cliente Online</h2>

    <?php if ($error): ?>
        <div class="mensaje error"><?= $error ?></div>
    <?php elseif ($exito): ?>
        <div class="mensaje exito"><?= $exito ?></div>
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
        <input type="text" name="domicilio">

        <label>Teléfono:</label>
        <input type="text" name="telefono">

        <label>Email:</label>
        <input type="email" name="email">

        <label>RFID (opcional):</label>
        <input type="text" name="rfid">

        <label>Disciplina:</label>
        <select name="disciplina">
            <option value="Boxeo">Boxeo</option>
            <option value="Kickboxing">Kickboxing</option>
            <option value="MMA">MMA</option>
            <option value="Funcional">Funcional</option>
        </select>

        <label>Fecha de vencimiento:</label>
        <input type="date" name="fecha_vencimiento" required>

        <button type="submit">Registrar Cliente</button>
    </form>
</div>
</body>
</html>
