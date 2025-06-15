<?php
include 'conexion.php';

$dni = $_POST['dni'];
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$telefono = $_POST['telefono'];
$email = $_POST['email'];
$domicilio = $_POST['domicilio'];
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$rfid = $_POST['rfid'];
$gimnasio_id = $_POST['gimnasio_id'];

// Calcular edad automáticamente
$fecha_nac = new DateTime($fecha_nacimiento);
$hoy = new DateTime();
$edad = $hoy->diff($fecha_nac)->y;

// Verificar si ya existe ese DNI
$verificar = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
if ($verificar->num_rows > 0) {
    echo "<script>alert('Ya existe un cliente con ese DNI.'); window.history.back();</script>";
    exit;
}

// Insertar nuevo cliente
$sql = "INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, gimnasio_id)
        VALUES ('$apellido', '$nombre', '$dni', '$fecha_nacimiento', '$edad', '$domicilio', '$telefono', '$email', '$rfid', '$gimnasio_id')";

if ($conexion->query($sql) === TRUE) {
    echo "<script>alert('Cliente registrado correctamente.'); window.location.href='registrar_cliente_online.php';</script>";
} else {
    echo "<script>alert('Error al registrar cliente.'); window.history.back();</script>";
}

$conexion->close();
?>
