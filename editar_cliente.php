<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

if (!isset($_GET['id'])) {
    echo "ID de cliente no especificado.";
    exit;
}

$id = $_GET['id'];
$resultado = $conexion->query("SELECT * FROM clientes WHERE id = $id");
$cliente = $resultado->fetch_assoc();

function calcularEdad($fechaNacimiento) {
    $fechaNacimiento = new DateTime($fechaNacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fechaNacimiento);
    return $edad->y;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Cliente</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/clientes.css">
  <style>
    .formulario-container {
        max-width: 700px;
        margin: 80px auto 20px auto;
        background-color: #222;
        padding: 20px;
        border-radius: 10px;
        box-shadow: 0 0 10px #f1c40f;
    }
    label {
        display: block;
        margin-top: 10px;
        font-weight: bold;
    }
    input[type="text"], input[type="number"], input[type="email"], input[type="date"] {
        width: 100%;
        padding: 8px;
        background-color: #333;
        color: #f1c40f;
        border: 1px solid #555;
        border-radius: 5px;
    }
    input[type="submit"] {
        margin-top: 15px;
    }
  </style>
</head>
<div style="text-align: center; margin: 20px;">
  <input type="text" id="buscador" placeholder="Buscar por apellido o DNI..." style="width: 80%; padding: 10px; font-size: 16px;">
</div>
<body>
<div class="formulario-container">
    <h2>Editar Cliente</h2>
    <form action="guardar_edicion_cliente.php" method="POST">
        <input type="hidden" name="id" value="<?= $cliente['id'] ?>">
        <label>Apellido:</label>
        <input type="text" name="apellido" value="<?= $cliente['apellido'] ?>" required>
        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?= $cliente['nombre'] ?>" required>
        <label>DNI:</label>
        <input type="text" name="dni" value="<?= $cliente['dni'] ?>" required>
        <label>Fecha de Nacimiento:</label>
        <input type="date" name="fecha_nacimiento" value="<?= $cliente['fecha_nacimiento'] ?>" required>
        <label>Edad:</label>
        <input type="number" name="edad" value="<?= calcularEdad($cliente['fecha_nacimiento']) ?>" readonly>
        <label>Domicilio:</label>
        <input type="text" name="domicilio" value="<?= $cliente['domicilio'] ?>" required>
        <label>Teléfono:</label>
        <input type="text" name="telefono" value="<?= $cliente['telefono'] ?>" required>
        <label>Email:</label>
        <input type="email" name="email" value="<?= $cliente['email'] ?>" required>
        <label>RFID:</label>
        <input type="text" name="rfid_uid" value="<?= $cliente['rfid_uid'] ?>">
        <input type="submit" value="Guardar Cambios">
    </form>
</div>
</body>
</html>
