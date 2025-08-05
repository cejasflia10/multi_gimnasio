<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$edad = $_POST['edad'] ?? null;
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

if (!$apellido || !$nombre || !$dni || !$fecha_nacimiento || !$gimnasio_id) {
    die("❌ Datos incompletos.");
}

$stmt = $conexion->prepare("
    INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, disciplina, gimnasio_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("ssssissssi", 
    $apellido, 
    $nombre, 
    $dni, 
    $fecha_nacimiento, 
    $edad, 
    $domicilio, 
    $telefono, 
    $email, 
    $disciplina, 
    $gimnasio_id
);

if ($stmt->execute()) {
    $cliente_id = $stmt->insert_id;
    // Redirigir a la página donde se muestra el QR dinámico o mensaje de bienvenida
    header("Location: bienvenida_online.php?cliente_id=" . $cliente_id);
    exit;
} else {
    echo "❌ Error al guardar cliente: " . $conexion->error;
}
