<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$fecha_hora = date("Y-m-d H:i:s");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);

    // Buscar cliente por DNI
    $stmt = $conexion->prepare("SELECT id, nombre, apellido, clases_disponibles, fecha_vencimiento FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $cliente = $resultado->fetch_assoc();
        $id_cliente = $cliente['id'];
        $nombre = $cliente['nombre'];
        $apellido = $cliente['apellido'];
        $clases = $cliente['clases_disponibles'];
        $vencimiento = $cliente['fecha_vencimiento'];

        if ($clases > 0 && strtotime($vencimiento) >= strtotime(date("Y-m-d"))) {
            // Descontar una clase
            $nuevas_clases = $clases - 1;
            $update = $conexion->prepare("UPDATE clientes SET clases_disponibles = ? WHERE id = ?");
            $update->bind_param("ii", $nuevas_clases, $id_cliente);
            $update->execute();

            // Registrar asistencia
            $insert = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha_hora) VALUES (?, ?)");
            $insert->bind_param("is", $id_cliente, $fecha_hora);
            $insert->execute();

            echo "<h2 style='color:yellow;'>Ingreso registrado correctamente</h2>";
            echo "<p style='color:white;'>Cliente: <strong>{$apellido}, {$nombre}</strong></p>";
            echo "<p style='color:white;'>Clases restantes: <strong>{$nuevas_clases}</strong></p>";
            echo "<p style='color:white;'>Fecha de vencimiento: <strong>{$vencimiento}</strong></p>";
        } else {
            echo "<h2 style='color:red;'>No tiene clases disponibles o su plan ha vencido.</h2>";
        }
    } else {
        echo "<h2 style='color:red;'>Cliente no encontrado</h2>";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso QR</title>
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 60px;
        }
        input[type="text"] {
            font-size: 1.2em;
            padding: 10px;
            width: 250px;
        }
        button {
            padding: 10px 20px;
            font-size: 1.2em;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <img src="logo.png" alt="Logo" style="width:150px;"><br><br>
    <form method="POST">
        <input type="text" name="dni" placeholder="Escanear DNI o QR" autofocus required>
        <br>
        <button type="submit">Registrar Ingreso</button>
    </form>
</body>
</html>
