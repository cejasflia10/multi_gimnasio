<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

// Validar datos
$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = intval($_POST['dni'] ?? 0);
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

// Verificar si el DNI ya existe
$existe = $conexion->query("SELECT id FROM clientes WHERE dni = $dni AND gimnasio_id = $gimnasio_id")->num_rows;
if ($existe > 0) {
    echo "<div style='color: red; text-align: center; font-size: 20px;'>❌ El cliente con ese DNI ya está registrado.</div>";
    echo "<div style='text-align: center;'><a href='registrar_cliente_online.php?gimnasio=$gimnasio_id'>Volver</a></div>";
    exit;
}

// Calcular edad automáticamente
$nacimiento = new DateTime($fecha_nacimiento);
$hoy = new DateTime();
$edad = $hoy->diff($nacimiento)->y;

// Insertar en la tabla clientes
$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, edad, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssissssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $edad, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);
$stmt->execute();

// Obtener nombre del gimnasio
$gimnasio = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$nombre_gimnasio = $gimnasio['nombre'] ?? 'el gimnasio';

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Exitoso</title>
    <meta http-equiv="refresh" content="3;url=cliente_acceso.php">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
        }
        .mensaje {
            font-size: 24px;
            margin-top: 40px;
        }
        .btn {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <img src="logo.png" alt="Logo" style="max-width: 150px;">
    <h1>🎉 ¡Bienvenido a <?= htmlspecialchars($nombre_gimnasio) ?>!</h1>
    <p class="mensaje">Tu registro fue exitoso.</p>
    <a href="cliente_acceso.php" class="btn">Ir al panel</a>
</body>
</html>
