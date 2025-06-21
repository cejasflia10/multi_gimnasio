<?php
session_start();
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
    $rfid_uid = !empty($_POST["rfid_uid"]) ? trim($_POST["rfid_uid"]) : null;
    $disciplina = !empty($_POST["disciplina"]) ? trim($_POST["disciplina"]) : null;
    $gimnasio_id = $_POST["gimnasio_id"];

    // Validación por DNI duplicado
    $stmt = $conexion->prepare("SELECT id FROM clientes WHERE dni = ? AND gimnasio_id = ?");
    $stmt->bind_param("si", $dni, $gimnasio_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "<script>alert('El DNI ya está registrado en este gimnasio.'); window.location.href='agregar_cliente.php';</script>";
        exit;
    }
    $stmt->close();

    // Insertar nuevo cliente
    $stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, rfid_uid, disciplina, gimnasio_id)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssisssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $rfid_uid, $disciplina, $gimnasio_id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente registrado correctamente.'); window.location.href='ver_clientes.php';</script>";
    } else {
        echo "Error al guardar: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "Acceso no permitido.";
}
?>
