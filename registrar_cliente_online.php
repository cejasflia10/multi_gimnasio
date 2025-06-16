<?php
include 'conexion.php';

// Solo mostrar errores con claridad
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mostrar datos recibidos
echo "<pre>";
echo "POST recibido:\n";
var_dump($_POST);
echo "</pre>";

// Validar campos clave
$apellido = isset($_POST['apellido']) ? trim($_POST['apellido']) : '';
$nombre = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
$dni = isset($_POST['dni']) ? trim($_POST['dni']) : '';
$fecha_nacimiento = isset($_POST['fecha_nacimiento']) ? trim($_POST['fecha_nacimiento']) : '';

$domicilio = isset($_POST['domicilio']) ? trim($_POST['domicilio']) : '';
$telefono = isset($_POST['telefono']) ? trim($_POST['telefono']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$rfid_uid = isset($_POST['rfid_uid']) ? trim($_POST['rfid_uid']) : '';

if ($apellido === '' || $nombre === '' || $dni === '' || $fecha_nacimiento === '') {
    echo "⚠️ Faltan datos obligatorios (apellido, nombre, DNI, fecha nacimiento)";
    exit;
}

try {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;
} catch (Exception $e) {
    echo "⚠️ Fecha inválida: " . $e->getMessage();
    exit;
}

$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo "❌ Error al preparar statement: " . $conexion->error;
    exit;
}

$stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);

if ($stmt->execute()) {
    echo "✅ Cliente registrado exitosamente.";
} else {
    echo "❌ Error al ejecutar: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>
