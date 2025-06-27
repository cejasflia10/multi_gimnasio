<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado.");
}
include 'conexion.php';
include 'menu_horizontal.php';
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
if (!isset($_GET['usuario_id'])) {
    die("ID de usuario no proporcionado.");
}

$usuario_id = intval($_GET['usuario_id']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ver_clientes = isset($_POST["ver_clientes"]) ? 1 : 0;
    $ver_membresias = isset($_POST["ver_membresias"]) ? 1 : 0;
    $ver_ventas = isset($_POST["ver_ventas"]) ? 1 : 0;
    $ver_profesores = isset($_POST["ver_profesores"]) ? 1 : 0;
    $ver_asistencias = isset($_POST["ver_asistencias"]) ? 1 : 0;
    $ver_panel = isset($_POST["ver_panel"]) ? 1 : 0;

    $stmt = $conexion->prepare("REPLACE INTO permisos_usuario 
        (usuario_id, ver_clientes, ver_membresias, ver_ventas, ver_profesores, ver_asistencias, ver_panel)
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiiiiii", $usuario_id, $ver_clientes, $ver_membresias, $ver_ventas, $ver_profesores, $ver_asistencias, $ver_panel);

    if ($stmt->execute()) {
        echo "<script>alert('Permisos actualizados correctamente'); window.location.href='usuarios.php';</script>";
    } else {
        echo "Error al actualizar permisos: " . $stmt->error;
    }

    $stmt->close();
}

// Obtener permisos actuales
$permisos = [
    'ver_clientes' => 0,
    'ver_membresias' => 0,
    'ver_ventas' => 0,
    'ver_profesores' => 0,
    'ver_asistencias' => 0,
    'ver_panel' => 0
];
$res = $conexion->query("SELECT * FROM permisos_usuario WHERE usuario_id = $usuario_id");
if ($res && $res->num_rows > 0) {
    $permisos = $res->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Permisos</title>
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .form-container {
            background-color: #222;
            padding: 20px;
            display: inline-block;
            border-radius: 10px;
            text-align: left;
        }
        .form-container label {
            display: block;
            margin: 10px 0;
        }
        button {
            background-color: #f1c40f;
            border: none;
            padding: 10px 20px;
            color: #000;
            font-weight: bold;
            cursor: pointer;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h2>Permisos para el usuario:</h2>
    <form method="post" class="form-container">
        <label><input type="checkbox" name="ver_clientes" <?= $permisos['ver_clientes'] ? 'checked' : '' ?>> Ver Clientes</label>
        <label><input type="checkbox" name="ver_membresias" <?= $permisos['ver_membresias'] ? 'checked' : '' ?>> Ver Membresías</label>
        <label><input type="checkbox" name="ver_ventas" <?= $permisos['ver_ventas'] ? 'checked' : '' ?>> Ver Ventas</label>
        <label><input type="checkbox" name="ver_profesores" <?= $permisos['ver_profesores'] ? 'checked' : '' ?>> Ver Profesores</label>
        <label><input type="checkbox" name="ver_asistencias" <?= $permisos['ver_asistencias'] ? 'checked' : '' ?>> Ver Asistencias</label>
        <label><input type="checkbox" name="ver_panel" <?= $permisos['ver_panel'] ? 'checked' : '' ?>> Ver Panel</label>
        <button type="submit">Guardar Permisos</button>
    </form>
</body>
</html>
