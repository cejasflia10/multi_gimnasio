<?php
// guardar_cliente_online.php (simplificado sin creado_en)
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

// Recibir POST
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);
$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nac = !empty($_POST['fecha_nacimiento']) ? $_POST['fecha_nacimiento'] : null;
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');

if ($gimnasio_id <= 0 || $apellido === '' || $nombre === '' || $dni === '') {
    die("Faltan datos obligatorios.");
}

// 1) Ver si ya existe cliente
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ? LIMIT 1");
$stmt->bind_param("si", $dni, $gimnasio_id);
$stmt->execute();
$res = $stmt->get_result();
$cliente_id = 0;

if ($row = $res->fetch_assoc()) {
    // Ya existe → actualizar datos
    if ($fecha_nac) {
        $upd = $conexion->prepare("UPDATE clientes SET apellido = ?, nombre = ?, fecha_nacimiento = ?, domicilio = ?, telefono = ?, email = ?, disciplina = ? WHERE id = ? AND gimnasio_id = ?");
        $upd->bind_param("ssssssiii", $apellido, $nombre, $fecha_nac, $domicilio, $telefono, $email, $disciplina, $row['id'], $gimnasio_id);
    } else {
        $upd = $conexion->prepare("UPDATE clientes SET apellido = ?, nombre = ?, fecha_nacimiento = NULL, domicilio = ?, telefono = ?, email = ?, disciplina = ? WHERE id = ? AND gimnasio_id = ?");
        $upd->bind_param("ssssssii", $apellido, $nombre, $domicilio, $telefono, $email, $disciplina, $row['id'], $gimnasio_id);
    }
    $upd->execute();
    $upd->close();
    $cliente_id = $row['id'];
} else {
    // No existe → insertar
    if ($fecha_nac) {
        $ins = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("ssssssssi", $apellido, $nombre, $dni, $fecha_nac, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);
    } else {
        $ins = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?)");
        $ins->bind_param("sssssssi", $apellido, $nombre, $dni, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);
    }
    $ins->execute();
    $cliente_id = $ins->insert_id;
    $ins->close();
}

// 2) Marcar como nuevo online si la columna existe
$conexion->query("UPDATE clientes SET nuevo_online = 1 WHERE id = $cliente_id AND gimnasio_id = $gimnasio_id");

// 3) Redirigir para mostrar el QR dinámico en la página de bienvenida
header("Location: bienvenida_online.php?cliente_id=" . intval($cliente_id));
exit;
