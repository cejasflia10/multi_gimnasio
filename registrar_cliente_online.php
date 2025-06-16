<?php
include 'conexion.php';

// Solo continuar si hay datos enviados por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rfid_uid = trim($_POST['rfid_uid'] ?? '');

    // Validar campos obligatorios (excepto rfid)
    if (empty($apellido) || empty($nombre) || empty($dni) || empty($fecha_nacimiento) || empty($domicilio) || empty($telefono) || empty($email)) {
        echo json_encode(["success" => false, "message" => "Faltan datos obligatorios."]);
        exit;
    }

    // Calcular edad automática
    $fecha_actual = new DateTime();
    $nacimiento = new DateTime($fecha_nacimiento);
    $edad = $fecha_actual->diff($nacimiento)->y;

    // Verificar duplicado
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["success" => false, "message" => "El cliente ya está registrado."]);
        exit;
    }

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);

    if ($stmt->execute()) {
        echo json_encode(["success" => true, "message" => "Registro exitoso."]);
    } else {
        echo json_encode(["success" => false, "message" => "Error al registrar cliente."]);
    }

    $stmt->close();
    $conexion->close();
} else {
    // Quitar este bloque si querés que el archivo responda sin restricción de método
    echo json_encode(["success" => false, "message" => "Acceso no permitido."]);
}
?>
