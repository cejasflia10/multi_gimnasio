<?php
include 'conexion.php';

if (!isset($_GET['usuario_id'])) {
    die("ID de usuario no proporcionado.");
}
$usuario_id = intval($_GET['usuario_id']);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ver_clientes = isset($_POST['ver_clientes']) ? 1 : 0;
    $ver_membresias = isset($_POST['ver_membresias']) ? 1 : 0;
    $ver_ventas = isset($_POST['ver_ventas']) ? 1 : 0;
    $ver_profesores = isset($_POST['ver_profesores']) ? 1 : 0;
    $ver_panel = isset($_POST['ver_panel']) ? 1 : 0;
    $ver_asistencias = isset($_POST['ver_asistencias']) ? 1 : 0;

    // Verificar si ya existe un registro
    $check = $conexion->prepare("SELECT * FROM permisos_usuario WHERE usuario_id = ?");
    $check->bind_param("i", $usuario_id);
    $check->execute();
    $resultado = $check->get_result();

    if ($resultado->num_rows > 0) {
        // Si existe, hacer UPDATE
        $stmt = $conexion->prepare("UPDATE permisos_usuario SET ver_clientes=?, ver_membresias=?, ver_ventas=?, ver_profesores=?, ver_panel=?, ver_asistencias=? WHERE usuario_id=?");
        $stmt->bind_param("iiiiiii", $ver_clientes, $ver_membresias, $ver_ventas, $ver_profesores, $ver_panel, $ver_asistencias, $usuario_id);
    } else {
        // Si no existe, hacer INSERT
        $stmt = $conexion->prepare("INSERT INTO permisos_usuario (usuario_id, ver_clientes, ver_membresias, ver_ventas, ver_profesores, ver_panel, ver_asistencias) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiiiii", $usuario_id, $ver_clientes, $ver_membresias, $ver_ventas, $ver_profesores, $ver_panel, $ver_asistencias);
    }

    if ($stmt->execute()) {
        echo "<script>alert('Permisos actualizados'); window.location.href='usuarios.php';</script>";
    } else {
        echo "Error al guardar permisos: " . $stmt->error;
    }
    $stmt->close();
}

// Obtener nombre de usuario
$nombre = "";
$stmt = $conexion->prepare("SELECT usuario FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$stmt->bind_result($nombre);
$stmt->fetch();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Permisos</title>
    <style>
        body {
            background-color: #111;
            color: #fff;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h2 {
            color: gold;
        }
        .form-container {
            background: #222;
            padding: 20px;
            border-radius: 10px;
            max-width: 500px;
        }
        label {
            display: block;
            margin: 10px 0;
        }
        button {
            background: gold;
            border: none;
            padding: 10px 15px;
            font-weight: bold;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h2>Permisos para el usuario: <?= htmlspecialchars($nombre) ?></h2>
    <form method="POST" class="form-container">
        <label><input type="checkbox" name="ver_clientes"> Ver Clientes</label>
        <label><input type="checkbox" name="ver_membresias"> Ver Membresías</label>
        <label><input type="checkbox" name="ver_ventas"> Ver Ventas</label>
        <label><input type="checkbox" name="ver_profesores"> Ver Profesores</label>
        <label><input type="checkbox" name="ver_asistencias"> Ver Asistencias</label>
        <label><input type="checkbox" name="ver_panel"> Ver Panel</label>
        <br>
        <button type="submit">Guardar Permisos</button>
    </form>
</body>
</html>
