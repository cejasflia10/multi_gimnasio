<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
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
        input {
            padding: 10px;
            font-size: 20px;
            width: 90%;
            max-width: 400px;
            margin-bottom: 15px;
            background-color: #111;
            border: 1px solid gold;
            color: gold;
        }
        .alerta {
            color: yellow;
            margin-top: 20px;
            font-size: 18px;
        }
        .exito {
            color: lime;
            margin-top: 20px;
            font-size: 18px;
        }
        .info {
            font-size: 16px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>

    <form method="POST" id="formulario">
        <input type="text" name="dni" id="dni" placeholder="Escanear QR" autofocus autocomplete="off">
    </form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($conexion->real_escape_string($_POST["dni"]));
    $fecha_actual = date("Y-m-d");
    $hora_actual = date("H:i:s");

    echo "<div style='color:gray;'>📌 DNI recibido: <b>$dni</b></div>";

    $cliente_result = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

    if (!$cliente_result || $cliente_result->num_rows === 0) {
        echo "<div class='alerta'>❌ DNI no encontrado: $dni</div>";
    } else {
        $cliente = $cliente_result->fetch_assoc();
        $cliente_id = $cliente['id'];
        $nombre = $cliente['nombre'] . " " . $cliente['apellido'];

        // Buscar membresía activa
        $membresia_result = $conexion->query("
            SELECT * FROM membresias 
            WHERE cliente_id = $cliente_id 
            AND fecha_vencimiento >= CURDATE() 
            AND clases_restantes > 0 
            ORDER BY fecha_vencimiento DESC 
            LIMIT 1
        ");

        if ($membresia_result->num_rows === 0) {
            echo "<div class='alerta'>⚠️ $nombre no tiene membresía activa o clases disponibles.</div>";
        } else {
            $membresia = $membresia_result->fetch_assoc();
            $id_membresia = $membresia['id'];
            $clases_restantes = $membresia['clases_restantes'] - 1;
            $fecha_vencimiento = $membresia['fecha_vencimiento'];

            // Descontar clase
            $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

            // Registrar asistencia
            $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

            echo "<div class='exito'>✅ Asistencia registrada</div>";
            echo "<div class='info'>👤 Cliente: <strong>$nombre</strong><br>📅 Vence: $fecha_vencimiento<br>🎯 Clases restantes: $clases_restantes<br>🕒 Hora: $hora_actual</div>";
        }
    }

    echo "
    <script>
        setTimeout(function() {
            document.getElementById('dni').value = '';
            document.getElementById('dni').focus();
        }, 3000);
    </script>";
}
?>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        document.getElementById("dni").focus();
    });
</script>
</body>
</html>
