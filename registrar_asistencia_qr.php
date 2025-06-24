<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body {
            background-color: black;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .resultado {
            margin-top: 20px;
            padding: 15px;
            border: 2px solid gold;
            border-radius: 10px;
        }
        .boton {
            margin-top: 20px;
            padding: 10px 20px;
            font-size: 16px;
            background-color: gold;
            color: black;
            border: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR - Asistencia</h1>
    <div id="reader" style="width: 300px; margin: auto;"></div>
    <div class="resultado" id="resultado"></div>
    <button class="boton" onclick="window.location.href='index.php'">Volver al menú</button>

    <script>
        function onScanSuccess(decodedText, decodedResult) {
            // Detener el escáner
            html5QrcodeScanner.clear().then(_ => {
                // Enviar el DNI por AJAX
                fetch("registrar_asistencia_qr.php", {
                    method: "POST",
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: "dni=" + encodeURIComponent(decodedText)
                })
                .then(response => response.text())
                .then(data => {
                    document.getElementById("resultado").innerHTML = data;
                });
            });
        }

        var html5QrcodeScanner = new Html5QrcodeScanner("reader", {
            fps: 10,
            qrbox: 250
        });
        html5QrcodeScanner.render(onScanSuccess);
    </script>
</body>
</html>

<?php
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = $conexion->real_escape_string($_POST['dni']);

    $query = "SELECT c.id AS cliente_id, c.nombre, c.apellido, m.id AS membresia_id, 
                     m.clases_disponibles, m.fecha_vencimiento
              FROM clientes c
              JOIN membresias m ON c.id = m.cliente_id
              WHERE c.dni = '$dni'
                AND c.gimnasio_id = $gimnasio_id
                AND m.fecha_vencimiento >= CURDATE()
                AND m.clases_disponibles > 0
              ORDER BY m.fecha_vencimiento DESC
              LIMIT 1";

    $resultado = $conexion->query($query);

    if ($resultado && $resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $cliente_id = $fila['cliente_id'];
        $membresia_id = $fila['membresia_id'];
        $clases_disponibles = $fila['clases_disponibles'] - 1;

        // Actualizar clases disponibles
        $conexion->query("UPDATE membresias SET clases_disponibles = $clases_disponibles WHERE id = $membresia_id");

        // Registrar asistencia
        $fecha_actual = date("Y-m-d");
        $hora_actual = date("H:i:s");
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) 
                          VALUES ($cliente_id, '$fecha_actual', '$hora_actual', $gimnasio_id)");

        echo "<strong>✔ Asistencia registrada</strong><br>
              DNI: <strong>$dni</strong><br>
              Nombre: {$fila['nombre']} {$fila['apellido']}<br>
              Clases restantes: <strong>$clases_disponibles</strong><br>
              Válida hasta: <strong>{$fila['fecha_vencimiento']}</strong>";
    } else {
        echo "<div style='color: yellow; font-weight: bold;'>
                ⚠️ El DNI <strong>$dni</strong> no tiene una membresía activa o clases disponibles.
              </div>";
    }
}
?>
