<?php
include 'conexion.php';
if (!isset($_GET['id'])) {
    die("ID de gimnasio no especificado.");
}
$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM gimnasios WHERE id = $id");
if ($resultado->num_rows === 0) {
    die("Gimnasio no encontrado.");
}
$gimnasio = $resultado->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = $_POST["nombre"];
    $direccion = $_POST["direccion"];
    $telefono = $_POST["telefono"];
    $email = $_POST["email"];
    $plan = $_POST["plan"];
    $fecha_vencimiento = !empty($_POST["fecha_vencimiento"]) ? $_POST["fecha_vencimiento"] : null;
    $duracion = $_POST["duracion_plan"];
    $limite = $_POST["limite_clientes"];
    $panel = isset($_POST["acceso_panel"]) ? 1 : 0;
    $ventas = isset($_POST["acceso_ventas"]) ? 1 : 0;
    $asistencias = isset($_POST["acceso_asistencias"]) ? 1 : 0;

    $stmt = $conexion->prepare("
        UPDATE gimnasios 
        SET nombre=?, direccion=?, telefono=?, email=?, plan=?, fecha_vencimiento=?, 
            duracion_plan=?, limite_clientes=?, acceso_panel=?, acceso_ventas=?, acceso_asistencias=? 
        WHERE id=?
    ");

    // Si fecha es null, usar tipo "s" normal (MySQL lo interpreta como NULL)
    $stmt->bind_param(
        "ssssssiiiiii",
        $nombre, $direccion, $telefono, $email, $plan, $fecha_vencimiento,
        $duracion, $limite, $panel, $ventas, $asistencias, $id
    );

    $stmt->execute();

    // Crear usuario si se cargó
    if (!empty($_POST["usuario"]) && !empty($_POST["clave"])) {
        $usuario = $_POST["usuario"];
        $clave = password_hash($_POST["clave"], PASSWORD_BCRYPT);
        $rol = "admin";
        $stmt_user = $conexion->prepare("INSERT INTO usuarios (usuario, contraseña, rol, gimnasio_id) VALUES (?, ?, ?, ?)");
        $stmt_user->bind_param("sssi", $usuario, $clave, $rol, $id);
        $stmt_user->execute();
    }

    header("Location: gimnasios.php");
    exit;
}
?>
