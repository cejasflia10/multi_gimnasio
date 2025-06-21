<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = trim($_POST["apellido"] ?? '');
    $nombre = trim($_POST["nombre"] ?? '');
    $dni = trim($_POST["dni"] ?? '');
    $fecha_nacimiento = $_POST["fecha_nacimiento"] ?? '';
    $edad = $_POST["edad"] ?? '';
    $domicilio = trim($_POST["domicilio"] ?? '');
    $telefono = trim($_POST["telefono"] ?? '');
    $email = trim($_POST["email"] ?? '');
    $rfid = trim($_POST["rfid"] ?? '');
    $disciplina = trim($_POST["disciplina"] ?? '');
    $gimnasio_id = $_SESSION["gimnasio_id"] ?? null;

    if (!$apellido || !$nombre || !$dni || !$fecha_nacimiento || !$edad || !$gimnasio_id) {
        echo "Faltan datos obligatorios.";
        exit;
    }

    // Verificar si ya existe el DNI en este gimnasio
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "<script>alert('El DNI ya está registrado.'); window.location.href='agregar_cliente.php';</script>";
        exit;
    }
    $stmt->close();

    // Insertar cliente
    $stmt = $conexion->prepare("INSERT INTO clientes 
        (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid, disciplina, gimnasio_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssisssssi", 
        $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid, $disciplina, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente agregado correctamente.'); window.location.href='ver_clientes.php';</script>";
    } else {
        echo "Error al agregar cliente: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "Acceso no permitido.";
}
