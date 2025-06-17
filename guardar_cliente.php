<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido']);
    $nombre = trim($_POST['nombre']);
    $dni = trim($_POST['dni']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $domicilio = $_POST['domicilio'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $rfid = $_POST['rfid'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $dias_disponibles = $_POST['dias_disponibles'];
    $gimnasio_id = $_SESSION['gimnasio_id'];

    if (!$apellido || !$nombre || !$dni || !$rfid) {
        echo "Faltan datos obligatorios.";
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, rfid, fecha_vencimiento, dias_disponibles) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $rfid, $fecha_vencimiento, $dias_disponibles);

    if ($stmt->execute()) {
        $cliente_id = $stmt->insert_id;
        // Vincular con gimnasio
        $conexion->query("INSERT INTO clientes_gimnasio (cliente_id, gimnasio_id) VALUES ($cliente_id, $gimnasio_id)");
        header("Location: ver_clientes.php?mensaje=creado");
    } else {
        echo "Error al agregar cliente: " . $stmt->error;
    }

    $stmt->close();
}
?>
