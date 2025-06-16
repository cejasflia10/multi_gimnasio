<?php
include 'conexion.php';

// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     echo json_encode(["success" => false, "message" => "Acceso no permitido."]);
//     exit;
// }

$apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';

$domicilio = isset($_POST['domicilio']) ? trim($_POST['domicilio']) : '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$rfid_uid = isset($_POST['rfid_uid']) ? trim($_POST['rfid_uid']) : '';

// Requiere solo los campos esenciales
if ($apellido === '' || $nombre === '' || $dni === '' || $fecha_nacimiento === '') {
    echo json_encode(['success' => false, 'message' => 'Debe completar apellido, nombre, DNI y fecha de nacimiento.']);
    exit;
}

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Fecha inválida.']);
    exit;
}

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
