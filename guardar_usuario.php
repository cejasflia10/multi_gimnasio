<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

if (
    isset($_POST['id'], $_POST['usuario'], $_POST['rol']) &&
    is_numeric($_POST['id'])
) {
    $id = intval($_POST['id']);
    $usuario = $conexion->real_escape_string(trim($_POST['usuario']));
    $email = $conexion->real_escape_string(trim($_POST['email'] ?? ''));
    $rol = $conexion->real_escape_string($_POST['rol']);

    // Si se carga una nueva contraseña
    $nueva_contrasena = $_POST['nueva_contrasena'] ?? '';
    $update_pass = '';
    if (!empty($nueva_contrasena)) {
        $hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $update_pass = ", contrasena = '$hash'";
    }

    // Permisos con valores por defecto
    $permisos = [
        'permiso_clientes' => isset($_POST['permiso_clientes']) ? 1 : 0,
        'permiso_membresias' => isset($_POST['permiso_membresias']) ? 1 : 0,
        'permiso_profesores' => isset($_POST['permiso_profesores']) ? 1 : 0,
        'permiso_ventas' => isset($_POST['permiso_ventas']) ? 1 : 0,
        'permiso_asistencias' => isset($_POST['permiso_asistencias']) ? 1 : 0,
        'permiso_panel' => isset($_POST['permiso_panel']) ? 1 : 0
    ];

    $query = "
        UPDATE usuarios SET 
            usuario = '$usuario',
            email = '$email',
            rol = '$rol',
            permiso_clientes = {$permisos['permiso_clientes']},
            permiso_membresias = {$permisos['permiso_membresias']},
            permiso_profesores = {$permisos['permiso_profesores']},
            permiso_ventas = {$permisos['permiso_ventas']},
            permiso_asistencias = {$permisos['permiso_asistencias']},
            permiso_panel = {$permisos['permiso_panel']}
            $update_pass
        WHERE id = $id
    ";

    if ($conexion->query($query)) {
        echo "<script>alert('Usuario actualizado correctamente.'); window.location='ver_usuarios.php';</script>";
    } else {
        echo "Error al actualizar: " . $conexion->error;
    }
} else {
    echo "Datos incompletos.";
}
