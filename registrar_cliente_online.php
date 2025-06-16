<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include("conexion.php");

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['correo'] ?? '');
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $rfid_uid = trim($_POST['rfid_uid'] ?? '');

    // Validar campos obligatorios
    if (empty($nombre) || empty($apellido) || empty($dni) || empty($telefono) || empty($email) || empty($fecha_nacimiento)) {
        echo json_encode(["success" => false, "message" => "Todos los campos son obligatorios, excepto el RFID."]);
        exit;
    }

    // Calcular edad
    $edad = 0;
    if ($fecha_nacimiento != '') {
        $fecha_nacimiento_dt = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nacimiento_dt)->y;
    }

    // Verificar que el DNI no exista
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "El cliente con ese DNI ya está registrado."]);
        exit;
    }

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (nombre, apellido, dni, telefono, email, fecha_nacimiento, edad, rfid_uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssis", $nombre, $apellido, $dni, $telefono, $email, $fecha_nacimiento, $edad, $rfid_uid);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Cliente registrado correctamente."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar cliente."]);
    }

    $stmt->close();
    $conexion->close();
} else {
    echo json_encode(["success" => false, "message" => "Acceso no permitido."]);
}
?>
