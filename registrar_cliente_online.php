<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

// Obtener gimnasio desde sesión o URL
$gimnasio_id = $_SESSION['gimnasio_id'] ?? ($_GET['gimnasio'] ?? 0);
$nombre_gimnasio = "Gimnasio";

if ($gimnasio_id > 0) {
    $resultado = $conexion->query("SELECT nombre FROM gimnasios WHERE id = $gimnasio_id");
    if ($fila = $resultado->fetch_assoc()) {
        $nombre_gimnasio = $fila['nombre'];
    }
} else {
    echo "<h2 style='color:red; text-align:center;'>Gimnasio no identificado</h2>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro Cliente</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        .formulario {
            max-width: 600px;
            margin: auto;
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
        }
        input, select {
            width: 100%;
            margin-bottom: 12px;
            padding: 10px;
            border-radius: 5px;
            border: none;
        }
        input[type="submit"] {
            background-color: gold;
            color: black;
            font-weight: bold;
        }
        h1 {
            text-align: center;
            color: gold;
        }
    </style>
</head>
<body>

    <h1><?php echo strtoupper($nombre_gimnasio); ?></h1>

    <div class="formulario">
        <form action="guardar_cliente.php" method="POST">
            <input type="text" name="apellido" placeholder="Apellido" required>
            <input type="text" name="nombre" placeholder="Nombre" required>
            <input type="number" name="dni" placeholder="DNI" required>
            <input type="date" name="fecha_nacimiento" placeholder="Fecha de nacimiento" required>
            <input type="number" name="edad" placeholder="Edad">
            <input type="text" name="domicilio" placeholder="Domicilio">
            <input type="text" name="telefono" placeholder="Teléfono">
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="rfid" placeholder="RFID" required>
            <input type="text" name="disciplina" placeholder="Disciplina">
            <input type="date" name="fecha_vencimiento" placeholder="Fecha de vencimiento" required>
            <input type="submit" value="Registrar Cliente">
        </form>
    </div>
</body>
</html>
