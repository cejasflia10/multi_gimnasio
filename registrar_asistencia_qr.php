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
            padding: 20px;
        }
        video {
            width: 100%;
            max-width: 400px;
            border: 2px solid gold;
            margin-top: 10px;
        }
        .mensaje {
            margin-top: 20px;
            font-size: 20px;
        }
    </style>
</head>
<body>
    <h2>Escaneá tu QR</h2>
    <video id="preview"></video>
    <div class="mensaje" id="mensaje"></div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        const mensaje = document.getElementById("mensaje");

        function procesarQR(dni) {
            fetch("registrar_asistencia_qr.php?dni=" + dni)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mensaje.innerHTML = `
                            ✅ ${data.nombre}<br>
                            🗓️ Vence: ${data.vencimiento}<br>
                            🎟️ Clases restantes: ${data.clases}<br>
                        `;
                    } else {
                        mensaje.innerHTML = "⚠️ " + data.mensaje;
                    }
                    setTimeout(() => {
                        mensaje.innerHTML = "";
                        scanner.resume();
                    }, 5000);
                })
                .catch(error => {
                    mensaje.innerHTML = "❌ Error al procesar QR";
                    setTimeout(() => {
                        mensaje.innerHTML = "";
                        scanner.resume();
                    }, 5000);
                });
        }

        const scanner = new Html5Qrcode("preview");
        scanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: 250 },
            (decodedText) => {
                scanner.pause();
                procesarQR(decodedText.trim());
            },
            (errorMessage) => { /* Ignorar errores de escaneo */ }
        );
    </script>

<?php
// Si recibe solicitud por GET con el DNI
if (isset($_GET['dni'])) {
    $dni = $conexion->real_escape_string($_GET["dni"]);
    $fecha_actual = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $clientes_result = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni'");
    if ($clientes_result->num_rows === 0) {
        echo json_encode(["success" => false, "mensaje" => "DNI no encontrado."]);
        exit;
    }

    $cliente = $clientes_result->fetch_assoc();
    $cliente_id = $cliente['id'];

    $membresia_result = $conexion->query("
        SELECT * FROM membresias 
        WHERE cliente_id = $cliente_id
        AND fecha_vencimiento >= CURDATE()
        AND clases_restantes > 0
        ORDER BY fecha_vencimiento DESC
        LIMIT 1
    ");

    if ($membresia_result->num_rows === 0) {
        echo json_encode(["success" => false, "mensaje" => "Sin membresía activa o sin clases."]);
        exit;
    }

    $membresia = $membresia_result->fetch_assoc();
    $id_membresia = $membresia['id'];
    $clases_restantes = $membresia['clases_restantes'] - 1;
    $vencimiento = $membresia['fecha_vencimiento'];

    // Descontar clase
    $conexion->query("UPDATE membresias SET clases_restantes = $clases_restantes WHERE id = $id_membresia");

    // Registrar asistencia
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha_actual', '$hora_actual')");

    echo json_encode([
        "success" => true,
        "nombre" => $cliente['apellido'] . " " . $cliente['nombre'],
        "clases" => $clases_restantes,
        "vencimiento" => $vencimiento
    ]);
    exit;
}
?>
</body>
</html>
