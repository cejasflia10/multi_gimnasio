<?php
include 'conexion.php';

// Obtener y validar los campos
$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$rfid_uid = trim($_POST['rfid_uid'] ?? '');

// Verificar que los campos obligatorios no estén vacíos (excepto RFID)
if (empty($apellido) || empty($nombre) || empty($dni) || empty($fecha_nacimiento) || empty($domicilio) || empty($telefono) || empty($email)) {
    echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
    exit;
}

// Calcular edad automáticamente
$fecha_actual = new DateTime();
$nacimiento = new DateTime($fecha_nacimiento);
$edad = $fecha_actual->diff($nacimiento)->y;

// Validar que no se repita el DNI
$stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
$stmt->bind_param("s", $dni);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo json_encode(["success" => false, "message" => "El cliente ya existe."]);
    exit;
}

// Insertar nuevo cliente
$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Registro exitoso."]);
} else {
    echo json_encode(["success" => false, "message" => "Error al registrar."]);
}

$stmt->close();
$conexion->close();
?>
