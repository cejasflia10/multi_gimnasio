<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_usuario = $_POST['nombre_usuario'];
    $rol = $_POST['rol'];
    $id_gimnasio = $_POST['id_gimnasio'];
    $contrasena = $_POST['contrasena'];
    $confirmar = $_POST['confirmar_contrasena'];

    // Validar contraseñas
    if ($contrasena !== $confirmar) {
        echo "⚠️ Error: Las contraseñas no coinciden.";
        exit();
    }

    // Validar existencia del gimnasio
    $verificar = $conexion->prepare("SELECT id FROM gimnasios WHERE id = ?");
    $verificar->bind_param("i", $id_gimnasio);
    $verificar->execute();
    $resultado = $verificar->get_result();

    if ($resultado->num_rows === 0) {
        echo "⚠️ Error: El gimnasio seleccionado no existe.";
        exit();
    }

    // Generar hash seguro
    $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

    // Permisos
    $puede_ver_clientes     = isset($_POST['puede_ver_clientes']) ? 1 : 0;
    $puede_ver_membresias   = isset($_POST['puede_ver_membresias']) ? 1 : 0;
    $puede_ver_profesores   = isset($_POST['puede_ver_profesores']) ? 1 : 0;
    $puede_ver_ventas       = isset($_POST['puede_ver_ventas']) ? 1 : 0;
    $puede_ver_asistencias  = isset($_POST['puede_ver_asistencias']) ? 1 : 0;
    $puede_ver_panel        = isset($_POST['puede_ver_panel']) ? 1 : 0;
    $puede_ver_admin        = isset($_POST['puede_ver_admin']) ? 1 : 0;

    // Insertar usuario
    $sql = "INSERT INTO usuarios (
                nombre_usuario, contrasena, rol, id_gimnasio,
                puede_ver_clientes, puede_ver_membresias, puede_ver_profesores,
                puede_ver_ventas, puede_ver_asistencias, puede_ver_panel, puede_ver_admin
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("sssiiiiiiii", $nombre_usuario, $contrasena_hash, $rol, $id_gimnasio,
        $puede_ver_clientes, $puede_ver_membresias, $puede_ver_profesores,
        $puede_ver_ventas, $puede_ver_asistencias, $puede_ver_panel, $puede_ver_admin);

    if ($stmt->execute()) {
        header("Location: usuarios.php?mensaje=creado");
        exit();
    } else {
        echo "❌ Error al guardar el usuario. Detalles: " . $stmt->error;
    }
}
?>
