<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = trim($_POST["dni"]);

    // Verificamos membresía activa
    $query = "SELECT c.id AS cliente_id, c.nombre, c.apellido, m.clases_disponibles, m.fecha_vencimiento
              FROM clientes c
              JOIN membresias m ON c.id = m.cliente_id
              WHERE c.dni = '$dni'
              AND m.fecha_vencimiento >= CURDATE()
              AND m.clases_disponibles > 0
              ORDER BY m.fecha_vencimiento DESC
              LIMIT 1";
    $resultado = $conexion->query($query);

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $cliente_id = $cliente['cliente_id'];
        $clases = $cliente['clases_disponibles'] - 1;

        // Descontamos una clase
        $conexion->query("UPDATE membresias SET clases_disponibles = $clases WHERE cliente_id = $cliente_id");

        // Registramos asistencia
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, CURDATE(), CURTIME())");

        $mensaje = "✅ Asistencia registrada correctamente para " . $cliente['nombre'] . " " . $cliente['apellido'];
    } else {
        $mensaje = "⚠️ El DNI $dni no tiene una membresía activa o clases disponibles.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        h1 {
            margin-bottom: 30px;
        }
        input[type="text"] {
            padding: 12px;
            font-size: 18px;
            width: 90%;
            max-width: 400px;
            margin-bottom: 20px;
        }
        .btn {
            background-color: gold;
            color: black;
            padding: 10px 20px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            margin-top: 15px;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    
    <form method="POST">
        <input type="text" name="dni" placeholder="Ingrese o escanee DNI" autofocus required>
        <br>
        <button type="submit" class="btn">Registrar</button>
    </form>

    <div class="mensaje"><?= $mensaje ?></div>

    <br><br>
    <a href="index.php"><button class="btn">Volver al menú</button></a>
</body>
</html>
