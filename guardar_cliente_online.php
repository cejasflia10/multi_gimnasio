<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Validación básica
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $apellido = trim($_POST['apellido']);
    $nombre = trim($_POST['nombre']);
    $dni = trim($_POST['dni']);
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $domicilio = trim($_POST['domicilio']);
    $telefono = trim($_POST['telefono']);
    $email = trim($_POST['email']);
    $disciplina = $_POST['disciplina'];
    $rfid_uid = $_POST['rfid_uid'] ?? '';
    $gimnasio_id = intval($_POST['gimnasio_id']);

    // Evitar duplicados por DNI
    $verificar = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $verificar->bind_param("si", $dni, $gimnasio_id);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows > 0) {
        echo "<script>alert('Este DNI ya está registrado.'); window.location.href='registrar_cliente_online.php';</script>";
        exit;
    }
    $verificar->close();

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, rfid_uid, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $rfid_uid, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado correctamente'); window.location.href='registrar_cliente_online.php';</script>";
    } else {
        echo "Error al registrar cliente: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Método no permitido.";
}
?>
