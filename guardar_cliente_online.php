<?php
session_start();
include 'conexion.php';

$apellido = trim($_POST['apellido'] ?? '');
$nombre = trim($_POST['nombre'] ?? '');
$dni = trim($_POST['dni'] ?? '');
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
$domicilio = trim($_POST['domicilio'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$disciplina = trim($_POST['disciplina'] ?? '');
$gimnasio_id = intval($_POST['gimnasio_id'] ?? 0);

if (!$apellido || !$nombre || !$dni || !$fecha_nacimiento || !$domicilio || !$telefono || !$email || !$disciplina || !$gimnasio_id) {
    exit("❌ Datos incompletos.");
}

// Verificar si el DNI ya está registrado en ese gimnasio
$existe = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
if ($existe->num_rows > 0) {
    exit("⚠️ Este DNI ya está registrado.");
}

// Insertar cliente
$stmt = $conexion->prepare("INSERT INTO clientes (apellido, nombre, dni, fecha_nacimiento, domicilio, telefono, email, disciplina, gimnasio_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssisssssi", $apellido, $nombre, $dni, $fecha_nacimiento, $domicilio, $telefono, $email, $disciplina, $gimnasio_id);
$stmt->execute();

// Obtener datos del gimnasio
$gimnasio = $conexion->query("SELECT * FROM gimnasios WHERE id = $gimnasio_id")->fetch_assoc();
$config = $conexion->query("SELECT * FROM configuracion_gimnasio WHERE gimnasio_id = $gimnasio_id")->fetch_assoc();

// Mostrar pantalla de bienvenida
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro exitoso</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: black;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            text-align: center;
        }
        .contenedor {
            max-width: 500px;
            margin: auto;
            background: #111;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }
        .logo {
            max-height: 100px;
            margin-bottom: 20px;
            background: white;
            padding: 10px;
            border-radius: 8px;
        }
        .boton {
            margin-top: 25px;
            display: inline-block;
            background: gold;
            color: black;
            padding: 10px 20px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <?php if (!empty($config['mostrar_logo_pdf']) && file_exists("logos/logo_{$gimnasio_id}.png")): ?>
        <img src="logos/logo_<?= $gimnasio_id ?>.png" alt="Logo" class="logo">
    <?php endif; ?>

    <h2>🎉 ¡Bienvenido a <?= htmlspecialchars($gimnasio['nombre']) ?>!</h2>

    <?php if (!empty($config['mensaje_bienvenida'])): ?>
        <p><?= nl2br(htmlspecialchars($config['mensaje_bienvenida'])) ?></p>
    <?php else: ?>
        <p>Tu registro fue exitoso. Pronto nos pondremos en contacto contigo.</p>
    <?php endif; ?>

    <a class="boton" href="registrar_cliente_online.php?gimnasio=<?= $gimnasio_id ?>">⬅️ Volver</a>
</div>
</body>
</html>
