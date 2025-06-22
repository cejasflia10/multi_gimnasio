<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = intval($_POST["id"]);
    $apellido = trim($_POST["apellido"]);
    $nombre = trim($_POST["nombre"]);
    $dni = trim($_POST["dni"]);
    $fecha_nacimiento = $_POST["fecha_nacimiento"];
    $domicilio = trim($_POST["domicilio"]);
    $telefono = trim($_POST["telefono"]);
    $email = trim($_POST["email"]);
    $disciplina = trim($_POST["disciplina"]);
    $gimnasio_id = intval($_POST["gimnasio_id"]); // ✅ Este campo estaba faltando

    // ✅ Incluimos gimnasio_id en la consulta
    $stmt = $conexion->prepare("UPDATE clientes 
        SET apellido = ?, nombre = ?, dni = ?, fecha_nacimiento = ?, domicilio = ?, telefono = ?, email = ?, disciplina = ?, gimnasio_id = ?
        WHERE id = ?");

    $stmt->bind_param("ssssssssii", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $gimnasio_id, $id);

    if ($stmt->execute()) {
        echo "<script>alert('Cliente actualizado correctamente.'); window.location.href='ver_clientes.php';</script>";
    } else {
        echo "<script>alert('Error al actualizar cliente: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Método no permitido.'); window.history.back();</script>";
}
