<?php
include 'conexion.php';
session_start();

if (!isset($_SESSION['gimnasio_id'])) {
    echo "<script>alert('Debe iniciar sesión.'); window.location.href='login.php';</script>";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido']);
    $nombre = trim($_POST['nombre']);
    $dni = trim($_POST['dni']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $domicilio = trim($_POST['domicilio']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $edad = $_POST['edad'];
    $disciplina = $_POST['disciplina'];
    $rfid = trim($_POST['rfid']);
    $gimnasio_id = $_SESSION['gimnasio_id'];

    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('El DNI ya está registrado en este gimnasio.'); window.location.href='clientes.php';</script>";
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, telefono, email, domicilio, fecha_nacimiento, edad, disciplina, rfid, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssssi", $apellido, $nombre, $dni, $telefono, $email, $domicilio, $fecha_nacimiento, $edad, $disciplina, $rfid, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente agregado correctamente.'); window.location.href='clientes.php';</script>";
    } else {
        echo "<script>alert('Error al agregar cliente.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Cliente</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-top: 10px;
        }
        input, select {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            background: #222;
            color: gold;
            border: 1px solid gold;
            border-radius: 5px;
        }
        .btn {
            background: gold;
            color: black;
            font-weight: bold;
            padding: 10px;
            margin-top: 20px;
            width: 100%;
            border: none;
            border-radius: 8px;
        }
        small {
            color: #ccc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Agregar Cliente</h1>
        <form method="POST">
            <label>Apellido <small>(obligatorio)</small></label>
            <input type="text" name="apellido" required>

            <label>Nombre <small>(obligatorio)</small></label>
            <input type="text" name="nombre" required>

            <label>DNI <small>(obligatorio)</small></label>
            <input type="text" name="dni" required>

            <label>Teléfono</label>
            <input type="text" name="telefono">

            <label>Email</label>
            <input type="email" name="email">

            <label>Domicilio</label>
            <input type="text" name="domicilio">

            <label>Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento">

            <label>Edad</label>
            <input type="number" name="edad">

            <label>Disciplina</label>
            <select name="disciplina">
                <option value="Boxeo">Boxeo</option>
                <option value="Kickboxing">Kickboxing</option>
                <option value="MMA">MMA</option>
            </select>

            <label>RFID <small>(solo si se conoce)</small></label>
            <input type="text" name="rfid">

            <button class="btn" type="submit">Guardar Cliente</button>
        </form>
    </div>
</body>
</html>
