<?php
include 'conexion.php';

// Acceso permitido temporalmente incluso por GET o navegador
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(["success" => false, "message" => "Acceso no permitido."]);
//     exit;
// }

$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$rfid_uid = trim($_POST['rfid'] ?? null);

if ($apellido === '' || $nombre === '' || $dni === '' || $fecha_nacimiento === '') {
    echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios.']);
    exit;
}

$fecha_nac = new DateTime($fecha_nacimiento);
$hoy = new DateTime();
$edad = $fecha_nac->diff($hoy)->y;

$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Cliente registrado exitosamente.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al registrar cliente: ' . $stmt->error]);
}

$stmt->close();
$conexion->close();
?>
