<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = $_POST['apellido'];
    $nombre = $_POST['nombre'];
    $dni = $_POST['dni'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $rfid = $_POST['rfid'] ?? null;

    if (empty($apellido) || empty($nombre) || empty($dni) || empty($telefono) || empty($email) || empty($fecha_nacimiento)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos obligatorios deben ser completados.']);
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, telefono, email, fecha_nacimiento, rfid_uid) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $apellido, $nombre, $dni, $telefono, $email, $fecha_nacimiento, $rfid);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Cliente registrado correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al registrar el cliente.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Acceso no permitido.']);
}
?>
