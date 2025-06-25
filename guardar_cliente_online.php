<?php
session_start();
include 'conexion.php';

$apellido = trim($_POST['apellido']);
$nombre = trim($_POST['nombre']);
$dni = trim($_POST['dni']);
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$domicilio = trim($_POST['domicilio']);
$telefono = trim($_POST['telefono']);
$email = trim($_POST['email']);
$disciplina = trim($_POST['disciplina']);
$gimnasio_id = intval($_POST['gimnasio_id']);

// Validar DNI duplicado
$check = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
$check->bind_param("ii", $dni, $gimnasio_id);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $check->close();
    header("Location: registrar_cliente_online.php?gimnasio=$gimnasio_id&mensaje=El DNI ya está registrado.");
    exit();
}
$check->close();

// Insertar cliente
$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssisssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);

if ($stmt->execute()) {
    header("Location: registrar_cliente_online.php?gimnasio=$gimnasio_id&mensaje=Registro exitoso");
} else {
    echo "Error al registrar: " . $stmt->error;
}

$stmt->close();
?>
