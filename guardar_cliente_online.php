<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include 'conexion.php';

    $apellido = $_POST['apellido'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $dni = $_POST['dni'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $edad = $_POST['edad'] ?? null;
    $domicilio = $_POST['domicilio'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $email = $_POST['email'] ?? '';
    $disciplina = $_POST['disciplina'] ?? '';
    $gimnasio_id = $_POST['gimnasio_id'] ?? 0;

    if (empty($apellido) || empty($nombre) || empty($dni) || $gimnasio_id == 0) {
        echo "Error: datos incompletos o gimnasio no identificado.";
        exit;
    }

    // Validar si el DNI ya está registrado
    $check = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $check->bind_param("si", $dni, $gimnasio_id);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo "Error: ya existe un cliente con ese DNI en este gimnasio.";
        exit;
    }

    // Insertar cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, disciplina, gimnasio_id) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssi", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);

    if ($stmt->execute()) {
        $cliente_id = $stmt->insert_id;

        // Crear QR con el DNI
        $qr_nombre_archivo = "qr/cliente_" . $cliente_id . ".png";
        include 'phpqrcode/qrlib.php';
        QRcode::png($dni, $qr_nombre_archivo, QR_ECLEVEL_L, 4);

        echo "Registro exitoso. <a href='cliente_acceso.php'>Volver</a>";
    } else {
        echo "Error al registrar: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "Acceso no autorizado.";
}
