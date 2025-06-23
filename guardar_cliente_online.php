<?php
include("conexion.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recibir datos del formulario
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $rfid = $_POST["rfid_uid"] ?? '';
    $disciplina = $_POST["disciplina"];
    $gimnasio_id = $_POST["gimnasio_id"];

    // Verificar si el DNI ya existe en este gimnasio
    $verificar = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
    if ($verificar->num_rows > 0) {
        echo "<script>alert('Este DNI ya está registrado en este gimnasio.'); window.history.back();</script>";
        exit;
    }

    // Calcular edad automáticamente
    $edad = date_diff(date_create($fecha_nacimiento), date_create('today'))->y;

    // Insertar el cliente en la base de datos
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid, disciplina, gimnasio_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $disciplina, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado correctamente.'); window.location.href='cliente_acceso.php';</script>";
    } else {
        echo "<script>alert('Error al registrar el cliente.'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    header("Location: registro_online.php");
    exit;
}
