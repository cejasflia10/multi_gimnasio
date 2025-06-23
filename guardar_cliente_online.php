<?php
include("conexion.php");
include("phpqrcode/qrlib.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Recibir datos
    $apellido = $_POST["apellido"];
    $nombre = $_POST["nombre"];
    $dni = $_POST["dni"];
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $domicilio = $_POST["domicilio"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $disciplina = $_POST["disciplina"];
    $gimnasio_id = $_POST["gimnasio_id"];

    // Verificar si el DNI ya existe en este gimnasio
    $verificar = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
    if ($verificar->num_rows > 0) {
        echo "<script>alert('Este DNI ya está registrado en este gimnasio.'); window.history.back();</script>";
        exit;
    }

    // Calcular edad automáticamente
    $edad = date_diff(date_create($fecha_nacimiento), date_create('today'))->y;

    // Generar código QR
    $dir = "qrs/";
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    $archivo_qr = $dir . "qr_" . $dni . ".png";
    QRcode::png($dni, $archivo_qr, QR_ECLEVEL_L, 4);

    // Insertar en la base de datos incluyendo qr_path
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, disciplina, gimnasio_id, qr_path)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssis", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $disciplina, $gimnasio_id, $archivo_qr);

    if ($stmt->execute()) {
        echo "<script>window.location.href='ver_qr.php?dni=$dni';</script>";
    } else {
        echo "<script>alert('Error al registrar el cliente.'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    header("Location: registro_online.php");
    exit;
}
