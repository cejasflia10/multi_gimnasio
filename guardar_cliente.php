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
    $disciplina = trim($_POST["disciplina"] ?? '');

    // Gimnasio: lo toma del form si es admin, o de la sesión
    $gimnasio_id = $_POST["gimnasio_id"] ?? ($_SESSION["gimnasio_id"] ?? null);

    // Validación de campos obligatorios
    if (!$apellido || !$nombre || !$dni || !$fecha_nacimiento || !$edad || !$gimnasio_id) {
        echo "<script>alert('Faltan datos obligatorios.'); window.history.back();</script>";
        exit;
    }

    // Verificar si el DNI ya existe en ese gimnasio
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('El DNI ya está registrado en este gimnasio.'); window.location.href='agregar_cliente.php';</script>";
        exit;
    }
    $stmt->close();

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes 
        (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, disciplina, gimnasio_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("ssssissssi", 
        $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);

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
