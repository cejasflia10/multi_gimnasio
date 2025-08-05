<?php
// guardar_cliente_online.php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// Recibir POST
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);
$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nac = $_POST['fecha_nacimiento'] ?? null;
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');

if ($gimnasio_id <= 0 || $apellido === '' || $nombre === '' || $dni === '') {
    die("Faltan datos obligatorios.");
}

// 1) Buscar si ya existe cliente con ese DNI en este gimnasio
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ? LIMIT 1");
$stmt->bind_param("si", $dni, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();
$cliente_id = 0;

if ($row = $res->fetch_assoc()) {
    // Ya existe -> actualizar algunos datos y marcar nuevo_online = 1
    $cliente_id = intval($row['id']);
    $upd = $conexion->prepare("UPDATE clientes SET apellido = ?, nombre = ?, fecha_nacimiento = ?, domicilio = ?, telefono = ?, email = ?, disciplina = ? WHERE id = ? AND gimnasio_id = ?");
    $upd->bind_param("ssssssiii", $apellido, $nombre, $fecha_nac, $domicilio, $telefono, $email, $disciplina, $cliente_id, $gimnasio_id);
    $upd->execute();
    $upd->close();
} else {
    // No existe -> insertar
    $ins = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id, creado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $ins->bind_param("sssssssis", $apellido, $nombre, $dni, $fecha_nac, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);
    if (!$ins->execute()) {
        die("Error al insertar cliente: " . $ins->error);
    }
    $cliente_id = $ins->insert_id;
    $ins->close();
}

// 2) Intentar marcar cliente como 'nuevo_online = 1' para que se muestre en el index
// (Si la columna no existe, el UPDATE fallará; capturamos silenciosamente el error)
$conexion->query("UPDATE clientes SET nuevo_online = 1 WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id");

// 3) Redirigir a bienvenida con el id de cliente
header("Location: bienvenida_online.php?cliente_id=" . intval($cliente_id));
exit;
