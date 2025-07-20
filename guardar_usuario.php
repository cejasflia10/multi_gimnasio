<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$id = intval($_POST['id'] ?? 0);
$usuario = trim($_POST['usuario'] ?? '');
$email = trim($_POST['email'] ?? '');
$nueva_contrasena = trim($_POST['nueva_contrasena'] ?? '');
$rol = trim($_POST['rol'] ?? '');
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

// Permisos reales (basado en tu estructura de tabla actual)
$perm_clientes = isset($_POST['perm_clientes']) ? 1 : 0;
$perm_membresias = isset($_POST['perm_membresias']) ? 1 : 0;
$perm_profesores = isset($_POST['perm_profesores']) ? 1 : 0;
$perm_ventas = isset($_POST['perm_ventas']) ? 1 : 0;
$perm_admin = isset($_POST['perm_admin']) ? 1 : 0;
$perm_asistencias = isset($_POST['perm_asistencias']) ? 1 : 0;
$perm_panel = isset($_POST['perm_panel']) ? 1 : 0;

if ($id > 0) {
    // EDITAR usuario
    if (!empty($nueva_contrasena)) {
        $clave_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $query = $conexion->prepare("UPDATE usuarios SET usuario=?, email=?, contrasena=?, rol=?, gimnasio_id=?,
            perm_clientes=?, perm_membresias=?, perm_profesores=?, perm_ventas=?, perm_admin=?, puede_ver_asistencias=?, puede_ver_panel=?
            WHERE id=?");
        $query->bind_param(
            "ssssiiiiiiiii",
            $usuario, $email, $clave_hash, $rol, $gimnasio_id,
            $perm_clientes, $perm_membresias, $perm_profesores, $perm_ventas, $perm_admin, $perm_asistencias, $perm_panel,
            $id
        );
    } else {
        $query = $conexion->prepare("UPDATE usuarios SET usuario=?, email=?, rol=?, gimnasio_id=?,
            perm_clientes=?, perm_membresias=?, perm_profesores=?, perm_ventas=?, perm_admin=?, puede_ver_asistencias=?, puede_ver_panel=?
            WHERE id=?");
        $query->bind_param(
            "sssiiiiiiiii",
            $usuario, $email, $rol, $gimnasio_id,
            $perm_clientes, $perm_membresias, $perm_profesores, $perm_ventas, $perm_admin, $perm_asistencias, $perm_panel,
            $id
        );
    }
    $query->execute();
} else {
    // NUEVO usuario
    if (!empty($nueva_contrasena)) {
        $clave_hash = password_hash($nueva_contrasena, PASSWORD_DEFAULT);
        $query = $conexion->prepare("INSERT INTO usuarios (
            usuario, email, contrasena, rol, gimnasio_id,
            perm_clientes, perm_membresias, perm_profesores, perm_ventas, perm_admin, puede_ver_asistencias, puede_ver_panel
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $query->bind_param(
            "ssssiiiiiiii",
            $usuario, $email, $clave_hash, $rol, $gimnasio_id,
            $perm_clientes, $perm_membresias, $perm_profesores, $perm_ventas, $perm_admin, $perm_asistencias, $perm_panel
        );
        $query->execute();
    } else {
        echo "⚠️ Debes ingresar una contraseña.";
        exit;
    }
}

header("Location: ver_usuarios.php");
exit;
