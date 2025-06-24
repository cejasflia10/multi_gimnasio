<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$mensaje = '';
$color = 'gold';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);

    $consulta = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni'");
    if ($consulta && $consulta->num_rows > 0) {
        $cliente = $consulta->fetch_assoc();
        $cliente_id = $cliente['id'];
        $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

        // Verificar membresía activa
        $membresia = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id ORDER BY id DESC LIMIT 1");
        if ($membresia && $membresia->num_rows > 0) {
            $datos = $membresia->fetch_assoc();
            $fecha_vencimiento = $datos['fecha_vencimiento'];
            $clases_disponibles = $datos['clases_disponibles'];

            if ($clases_disponibles > 0 && $fecha_vencimiento >= date('Y-m-d')) {
                // Verificar si ya asistió hoy
                $hoy = date('Y-m-d');
                $asistio = $conexion->query("SELECT * FROM asistencias WHERE cliente_id = $cliente_id AND DATE(fecha_hora) = '$hoy'");
                if ($asistio->num_rows === 0) {
                    // Registrar asistencia
                    $conexion->query("INSERT INTO asistencias (cliente_id, fecha_hora) VALUES ($cliente_id, NOW())");

                    // Descontar clase
                    $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = " . $datos['id']);

                    $mensaje = "✅ $nombre ingresó correctamente<br>🎯 Clases restantes: " . ($clases_disponibles - 1) . "<br>📅 Vence: $fecha_vencimiento";
                } else {
                    $mensaje = "⚠️ $nombre ya registró su ingreso hoy<br>🎯 Clases restantes: $clases_disponibles<br>📅 Vence: $fecha_vencimiento";
                    $color = "orange";
                }
            } else {
                $mensaje = "⚠️ $nombre no tiene clases disponibles o está vencido<br>📅 Vence: $fecha_vencimiento<br>🎯 Clases: $clases_disponibles";
                $color = "red";
            }
        } else {
            $mensaje = "❌ $nombre no tiene membresía activa.";
            $color = "red";
        }
    } else {
        $mensaje = "❌ Cliente no encontrado.";
        $color = "red";
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
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 30px;
        }
        h1 {
            font-size: 28px;
            color: gold;
            margin-bottom: 30px;
        }
        input {
            padding: 12px;
            font-size: 20px;
            width: 90%;
            max-width: 400px;
            border-radius: 5px;
            border: none;
            margin-bottom: 20px;
            text-align: center;
        }
        #respuesta {
            margin-top: 20px;
            font-size: 20px;
        }
        button {
            margin-top: 20px;
            padding: 12px 24px;
            font-size: 18px;
            border: none;
            border-radius: 5px;
            background-color: gold;
            color: black;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>

    <form id="formulario" method="POST" autocomplete="off">
        <input type="text" name="dni" id="dni" autofocus placeholder="Escanear QR o ingresar DNI">
    </form>

    <div id="respuesta" style="color: <?= $color ?>;"><?= $mensaje ?></div>

    <?php if (!empty($mensaje)) : ?>
    <script>
        setTimeout(() => {
            document.getElementById('dni').value = '';
            document.getElementById('dni').focus();
            document.getElementById('respuesta').innerHTML = '';
        }, 4000);
    </script>
    <?php endif; ?>
</body>
</html>
