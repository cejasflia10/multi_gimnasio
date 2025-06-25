<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$apellido = trim($_POST['apellido']);
$nombre = trim($_POST['nombre']);
$dni = trim($_POST['dni']);
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$domicilio = trim($_POST['domicilio']);
$telefono = trim($_POST['telefono']);
$email = trim($_POST['email']);
$disciplina = trim($_POST['disciplina']);
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

// Validar DNI duplicado
$verificar = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
$verificar->bind_param("ii", $dni, $gimnasio_id);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows > 0) {
    $mensaje = "El cliente con DNI $dni ya está registrado.";
    header("Location: registrar_cliente_online.php?gimnasio=$gimnasio_id&mensaje=" . urlencode($mensaje));
    exit;
}

// Insertar nuevo cliente
$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssisssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);

if ($stmt->execute()) {
    $mensaje = "Cliente registrado exitosamente.";
} else {
    $mensaje = "Error al registrar cliente: " . $stmt->error;
}

header("Location: registrar_cliente_online.php?gimnasio=$gimnasio_id&mensaje=" . urlencode($mensaje));
exit;
?>
