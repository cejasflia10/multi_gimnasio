<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

if (!isset($_GET['id'])) {
    echo "<div class='error'>ID de cliente no proporcionado.</div>";
    exit;
}

$id = $_GET['id'];
$consulta = "SELECT * FROM clientes WHERE id = $id";
$resultado = mysqli_query($conexion, $consulta);
$cliente = mysqli_fetch_assoc($resultado);
if (!$cliente) {
    echo "<div class='error'>Cliente no encontrado.</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Cliente</title>
    <style>
        body {
            background-color: #111;
            color: #f1c40f;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        .container {
            margin: 80px auto;
            max-width: 700px;
            padding: 20px;
            background-color: #1c1c1c;
            border-radius: 10px;
            box-shadow: 0 0 10px #000;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #f1c40f;
        }
        input[type="text"], input[type="number"], input[type="date"], input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-top: 6px;
            margin-bottom: 16px;
            background-color: #222;
            color: #fff;
            border: 1px solid #f1c40f;
            border-radius: 4px;
        }
        label {
            font-weight: bold;
        }
        button {
            background-color: #f1c40f;
            color: #111;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            font-weight: bold;
            border-radius: 5px;
            width: 100%;
        }
        button:hover {
            background-color: #d4ac0d;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Editar Cliente</h2>
    <form action="guardar_edicion_cliente.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
        <label>Apellido:</label>
        <input type="text" name="apellido" value="<?php echo $cliente['apellido']; ?>" required>
        <label>Nombre:</label>
        <input type="text" name="nombre" value="<?php echo $cliente['nombre']; ?>" required>
        <label>DNI:</label>
        <input type="text" name="dni" value="<?php echo $cliente['dni']; ?>" required>
        <label>Fecha de nacimiento:</label>
        <input type="date" name="fecha_nacimiento" value="<?php echo $cliente['fecha_nacimiento']; ?>">
        <label>Domicilio:</label>
        <input type="text" name="domicilio" value="<?php echo $cliente['domicilio']; ?>">
        <label>Teléfono:</label>
        <input type="text" name="telefono" value="<?php echo $cliente['telefono']; ?>">
        <label>Email:</label>
        <input type="email" name="email" value="<?php echo $cliente['email']; ?>">
        <label>RFID:</label>
        <input type="text" name="rfid_uid" value="<?php echo $cliente['rfid_uid']; ?>">
        <label>Fecha de vencimiento del plan:</label>
        <input type="date" name="fecha_vencimiento" value="<?php echo $cliente['fecha_vencimiento']; ?>">
        <label>Días disponibles:</label>
        <input type="number" name="dias_disponibles" value="<?php echo $cliente['dias_disponibles']; ?>">
        <button type="submit">Guardar Cambios</button>
    </form>
</div>
</body>
</html>
