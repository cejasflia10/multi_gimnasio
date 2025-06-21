<?php
session_start();
include 'conexion.php';

function mostrarMensaje($mensaje, $exito = true) {
    $color = $exito ? '#00ff88' : '#ff5555';
    echo "<!DOCTYPE html>
    <html lang='es'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1'>
        <title>Guardar Cliente</title>
        <style>
            body {
                background-color: #111;
                color: $color;
                font-family: Arial, sans-serif;
                text-align: center;
                padding: 50px 20px;
            }
            .boton {
                background-color: #ffd700;
                color: #111;
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                text-decoration: none;
                margin-top: 20px;
                display: inline-block;
            }
            .boton:hover {
                background-color: #e5c100;
            }
        </style>
    </head>
    <body>
        <h2>$mensaje</h2>
        <a href='agregar_cliente.php' class='boton'>Agregar otro cliente</a>
        <a href='ver_clientes.php' class='boton'>Ver clientes</a>
    </body>
    </html>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = trim($_POST["apellido"] ?? '');
    $nombre = trim($_POST["nombre"] ?? '');
    $dni = trim($_POST["dni"] ?? '');
    $fecha_nacimiento = $_POST["fecha_nacimiento"] ?? '';
    $edad = $_POST["edad"] ?? '';
    $domicilio = trim($_POST["domicilio"] ?? '');
    $telefono = trim($_POST["telefono"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $rfid = trim($_POST["rfid"] ?? '');
    $disciplina = trim($_POST["disciplina"] ?? '');
    $gimnasio_id = $_POST["gimnasio_id"] ?? '';

    if (empty($apellido) || empty($nombre) || empty($dni) || empty($fecha_nacimiento) || empty($edad) || empty($gimnasio_id)) {
        mostrarMensaje("Faltan datos obligatorios.", false);
    }

    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        mostrarMensaje("El DNI ya está registrado en este gimnasio.", false);
    }
    $stmt->close();

    $stmt = $conexion->prepare("INSERT INTO clientes 
        (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, disciplina, gimnasio_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisssssi", 
        $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $disciplina, $gimnasio_id);

    if ($stmt->execute()) {
        mostrarMensaje("✅ Cliente registrado correctamente.");
    } else {
        mostrarMensaje("❌ Error al guardar: " . $stmt->error, false);
    }

    $stmt->close();
    $conexion->close();
} else {
    mostrarMensaje("Acceso no permitido.", false);
}
