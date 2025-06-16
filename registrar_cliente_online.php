<?php
include "conexion.php";

// Verificar que se recibió una petición POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y sanitizar los datos
    $apellido = trim($_POST['apellido'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? '');
    $domicilio = trim($_POST['domicilio'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rfid_uid = trim($_POST['rfid_uid'] ?? null);

    // Validar que los campos obligatorios estén completos
    if ($apellido === '' || $nombre === '' || $dni === '' || $fecha_nacimiento === '' || $domicilio === '' || $telefono === '' || $email === '') {
        echo json_encode(["success" => false, "message" => "Este endpoint requiere método POST para funcionar."]);
    // ⚠️ Elimina la línea exit; si querés permitir seguir cargando desde navegador
    // exit;
}

    // Calcular edad automáticamente
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $fecha_nac->diff($hoy)->y;

    // Insertar datos en la base de datos
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissss", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registro exitoso.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en la base de datos.']);
    }

    $stmt->close();
    $conexion->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Acceso no permitido.']);
}
