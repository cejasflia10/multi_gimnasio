<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

// Obtener datos del formulario
$apellido = $_POST['apellido'];
$nombre = $_POST['nombre'];
$dni = $_POST['dni'];
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$edad = $_POST['edad'];
$domicilio = $_POST['domicilio'];
$telefono = $_POST['telefono'];
$email = $_POST['email'];
$rfid = $_POST['rfid'];
$disciplina = $_POST['disciplina'];
$fecha_vencimiento = $_POST['fecha_vencimiento'];

// Obtener gimnasio desde sesión
$gimnasio_id = $_SESSION['gimnasio_id'] ?? ($_GET['gimnasio'] ?? 0);

if ($gimnasio_id > 0) {
    $query = "INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, disciplina, fecha_vencimiento, gimnasio_id) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ssssissssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $disciplina, $fecha_vencimiento, $gimnasio_id);
    
    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado correctamente'); window.location.href='registro_online.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
} else {
    echo "Error: gimnasio no identificado.";
}
?>
