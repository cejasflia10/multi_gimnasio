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
    <title>Escaneo QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 30px;
        }
        input[type="text"] {
            position: absolute;
            top: -1000px; /* oculto fuera de pantalla */
            opacity: 0;
        }
        .info, .exito, .alerta {
            margin-top: 20px;
            font-size: 18px;
        }
        .exito { color: lime; }
        .alerta { color: yellow; }
    </style>
</head>
<body>

<h1>Escaneo QR - Asistencia</h1>

<form method="POST" id="formulario">
    <input type="text" name="dni" id="dni" autofocus autocomplete="off">
</form>

<div id="respuesta">
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $fecha_actual = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

    if ($cliente_q && $cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

        $membresia_q = $conexion->query("
            SELECT * FROM membresias 
            WHERE cliente_id = $cliente_id 
            ORDER BY fecha_vencimiento DESC 
            LIMIT 1
        ");

        if ($membresia_q && $membresia_q->num_rows > 0) {
            $membresia = $membresia_q->fetch_assoc();
            $clases_restantes = $membresia['clases_restantes'];
            $fecha_vencimiento = $membresia['fecha_vencimiento'];

            if ($clases_restantes > 0 && $fecha_vencimiento >= $fecha_actual) {
                $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

                echo "<div class='exito'>✅ $nombre - Asistencia registrada</div>
                      <div class='info'>📅 Vence: $fecha_vencimiento<br>🎯 Clases restantes: " . ($clases_restantes - 1) . "<br>🕒 $hora_actual</div>";
            } else {
                echo "<div class='alerta'>⚠️ $nombre no tiene clases disponibles o la membresía está vencida.</div>
                      <div class='info'>📅 Vence: $fecha_vencimiento<br>🎯 Clases: $clases_restantes</div>";
            }
        } else {
            echo "<div class='alerta'>⚠️ $nombre no tiene membresía registrada.</div>";
        }
    } else {
        echo "<div class='alerta'>❌ DNI no encontrado.</div>";
    }

    // Recarga automática a los 3 segundos
    echo "<script>
        setTimeout(() => {
            window.location.href = window.location.href;
        }, 3000);
    </script>";
}
?>
</div>

<script>
// Auto-focus oculto al cargar
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("dni");
    input.focus();

    // Siempre vuelve el foco al input (lector QR)
    setInterval(() => {
        if (document.activeElement !== input) {
            input.focus();
        }
    }, 1000);
});
</script>

</body>
</html>
