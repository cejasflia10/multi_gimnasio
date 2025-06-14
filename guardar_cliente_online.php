<?php
include 'conexion.php';

$apellido = $_POST['apellido'];
$nombre = $_POST['nombre'];
$dni = $_POST['dni'];
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$edad = $_POST['edad'];
$domicilio = $_POST['domicilio'];
$telefono = $_POST['telefono'];
$email = $_POST['email'];
$rfid = $_POST['rfid'];
$gimnasio_id = $_POST['gimnasio_id'];
include 'conexion.php';

$dni = $_POST['dni'];
$rfid = $_POST['rfid'];
$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$telefono = $_POST['telefono'];
$email = $_POST['email'];
$domicilio = $_POST['domicilio'];
$fecha_nacimiento = $_POST['fecha_nacimiento'];
$gimnasio_id = $_POST['gimnasio_id'];

// Verificar duplicados por DNI o RFID
$verificar = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' OR rfid = '$rfid'");
if ($verificar->num_rows > 0) {
    echo "<script>alert('Error: Ya existe un cliente con ese DNI o RFID.'); window.history.back();</script>";
    exit;
}

// Insertar nuevo cliente
$sql = "INSERT INTO clientes (dni, rfid, nombre, apellido, telefono, email, domicilio, fecha_nacimiento, gimnasio_id)
        VALUES ('$dni', '$rfid', '$nombre', '$apellido', '$telefono', '$email', '$domicilio', '$fecha_nacimiento', '$gimnasio_id')";

if ($conexion->query($sql) === TRUE) {
    echo "<script>alert('Cliente registrado correctamente.'); window.location.href='ver_clientes.php';</script>";
} else {
    echo "<script>alert('Error al registrar cliente.'); window.history.back();</script>";
}


$sql = "INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, gimnasio_id)
        VALUES ('$apellido', '$nombre', '$dni', '$fecha_nacimiento', '$edad', '$domicilio', '$telefono', '$email', '$rfid', '$gimnasio_id')";

if ($conexion->query($sql) === TRUE) {
    echo "<script>alert('Cliente registrado correctamente'); window.location.href='registrar_cliente_online.php';</script>";
} else {
    echo "Error: " . $sql . "<br>" . $conexion->error;
}

$conexion->close();
?>
