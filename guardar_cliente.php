<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $edad = $_POST['edad'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $rfid = !empty($_POST['rfid']) ? $_POST['rfid'] : null;
    $gimnasio = $_POST['gimnasio'];

    // Verificar si el cliente ya existe por DNI
    $check = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    if ($check->num_rows > 0) {
        echo "<script>alert('El cliente ya está registrado.'); window.location.href='agregar_cliente.php';</script>";
        exit;
    }

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes 
        (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, gimnasio) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssisssss", 
        $apellido, $nombre, $dni, $fecha_nacimiento, $edad, 
        $domicilio, $telefono, $email, $rfid, $gimnasio);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado correctamente'); window.location.href='ver_clientes.php';</script>";
    } else {
        echo "<script>alert('Error al registrar cliente'); window.location.href='agregar_cliente.php';</script>";
    }

    $stmt->close();
}
?>
