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
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        #reader {
            width: 100%;
            max-width: 400px;
            margin: auto;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 18px;
        }
        .datos-cliente {
            color: white;
            margin-top: 10px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <div id="reader"></div>
    <div id="resultado" class="mensaje"></div>

    <script>
        function iniciarQR() {
            let lector = new Html5Qrcode("reader");
            lector.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                qrCodeMessage => {
                    lector.stop().then(() => {
                        fetch("registrar_asistencia_qr.php", {
                            method: "POST",
                            headers: { "Content-Type": "application/x-www-form-urlencoded" },
                            body: "dni=" + encodeURIComponent(qrCodeMessage.trim())
                        })
                        .then(response => response.text())
                        .then(data => {
                            document.getElementById("resultado").innerHTML = data;
                            setTimeout(() => {
                                document.getElementById("resultado").innerHTML = "";
                                iniciarQR();
                            }, 5000);
                        });
                    });
                },
                errorMessage => {}
            ).catch(err => {
                document.getElementById("resultado").innerHTML = "<div class='mensaje' style='color:red;'>No se pudo iniciar cámara</div>";
            });
        }
        iniciarQR();
    </script>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    include 'conexion.php';
    $dni = $conexion->real_escape_string($_POST["dni"]);
    $fecha_actual = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $clientes_result = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni'");
    if ($clientes_result->num_rows === 0) {
        echo "<div class='mensaje' style='color:red;'>❌ DNI no encontrado.</div>";
        exit;
    }

    $cliente = $clientes_result->fetch_assoc();
    $cliente_id = $cliente['id'];
    $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

    $membresia_result = $conexion->query("
        SELECT * FROM membresias 
        WHERE cliente_id = $cliente_id 
        AND fecha_vencimiento >= CURDATE() 
        AND clases_restantes > 0 
        ORDER BY fecha_vencimiento DESC 
        LIMIT 1
    ");

    if ($membresia_result->num_rows === 0) {
        echo "<div class='mensaje' style='color:orange;'>⚠️ Sin membresía activa o sin clases disponibles.</div>";
        exit;
    }

    $membresia = $membresia_result->fetch_assoc();
    $id_membresia = $membresia['id'];
    $clases_restantes = $membresia['clases_restantes'] - 1;
    $fecha_vencimiento = $membresia['fecha_vencimiento'];

    $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

    echo "<div class='mensaje' style='color:lime;'>✅ Asistencia registrada correctamente.</div>";
    echo "<div class='datos-cliente'>👤 $nombre<br>📅 Vencimiento: $fecha_vencimiento<br>📉 Clases restantes: $clases_restantes</div>";
    exit;
}
?>
