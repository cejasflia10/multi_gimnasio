<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $apellido = trim($_POST["apellido"]);
    $nombre = trim($_POST["nombre"]);
    $dni = trim($_POST["dni"]);
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $edad = $_POST["edad"];
    $domicilio = trim($_POST["domicilio"]);
    $telefono = trim($_POST["telefono"]);
    $email = trim($_POST["email"]);
    $rfid_uid = trim($_POST["rfid_uid"]);
    $disciplina_id = $_POST["disciplina_id"];
    $gimnasio_id = $_POST["gimnasio_id"];

    // Validar que el DNI no esté registrado
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo "<script>alert('Este DNI ya está registrado.'); window.history.back();</script>";
        exit;
    }
    $stmt->close();

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid, disciplina_id, gimnasio_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssissssii", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid, $disciplina_id, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Registro exitoso. Bienvenido a la academia.'); window.location.href='index.php';</script>";
    } else {
        echo "<script>alert('Error al registrar.'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
}
?>
