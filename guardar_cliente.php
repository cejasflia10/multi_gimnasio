<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $rfid_uid = $_POST['rfid_uid'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $dias_disponibles = $_POST['dias_disponibles'];

    $consulta = "UPDATE clientes SET 
        apellido='$apellido',
        nombre='$nombre',
        dni='$dni',
        fecha_nacimiento='$fecha_nacimiento',
        domicilio='$domicilio',
        telefono='$telefono',
        email='$email',
        rfid_uid='$rfid_uid',
        fecha_vencimiento='$fecha_vencimiento',
        dias_disponibles='$dias_disponibles'
        WHERE id=$id";

    if (mysqli_query($conexion, $consulta)) {
        header("Location: ver_clientes.php?mensaje=editado");
    } else {
        echo "Error al actualizar cliente: " . mysqli_error($conexion);
    }
}
?>
