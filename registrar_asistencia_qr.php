<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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
            padding: 30px;
        }
        #dni {
            display: none;
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
    <input type="text" name="dni" id="dni" autocomplete="off">
</form>

<div id="respuesta">
<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

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
            $clases = $membresia['clases_restantes'];
            $vto = $membresia['fecha_vencimiento'];

            $asistencia_q = $conexion->query("
                SELECT * FROM asistencias 
                WHERE cliente_id = $cliente_id AND fecha = '$fecha'
            ");

            if ($asistencia_q->num_rows > 0) {
                echo "<div class='alerta'>⚠️ $nombre ya registró asistencia hoy.</div>
                      <div class='info'>📅 Vence: $vto<br>🎯 Clases restantes: $clases</div>";
            } else if ($clases > 0 && $vto >= $fecha) {
                $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
                echo "<div class='exito'>✅ $nombre - Asistencia registrada</div>
                      <div class='info'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($clases - 1) . "<br>🕒 $hora</div>";
            } else {
                echo "<div class='alerta'>⚠️ $nombre no tiene clases disponibles o está vencido</div>
                      <div class='info'>📅 Vence: $vto<br>🎯 Clases: $clases</div>";
            }
        } else {
            echo "<div class='alerta'>⚠️ $nombre no tiene membresía registrada</div>";
        }
    } else {
        echo "<div class='alerta'>❌ DNI no encontrado</div>";
    }

    // Agrega reinicio automático luego de 4 segundos
    echo "<script>
        setTimeout(() => {
            window.location.href = window.location.href;
        }, 4000);
    </script>";
}
?>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const input = document.getElementById("dni");
    input.focus();

    input.addEventListener("input", () => {
        if (input.value.trim() !== "") {
            document.getElementById("formulario").submit();
        }
    });

    setInterval(() => {
        if (document.activeElement !== input) {
            input.focus();
        }
    }, 1000);
});
</script>

</body>
</html>
