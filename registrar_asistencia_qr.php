
<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

// POST tras escaneo QR
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $fecha_hoy = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar cliente por DNI
    $queryCliente = $conexion->prepare("SELECT id, nombre, apellido, disciplina, gimnasio_id FROM clientes WHERE dni = ?");
    $queryCliente->bind_param("s", $dni);
    $queryCliente->execute();
    $resultadoCliente = $queryCliente->get_result();

    if ($resultadoCliente->num_rows > 0) {
        $cliente = $resultadoCliente->fetch_assoc();
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];

        // Buscar membresía activa con clases disponibles
        $queryMembresia = $conexion->prepare("SELECT id, clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? AND fecha_vencimiento >= ? AND clases_disponibles > 0 ORDER BY fecha_vencimiento DESC LIMIT 1");
        $queryMembresia->bind_param("is", $cliente_id, $fecha_hoy);
        $queryMembresia->execute();
        $resultadoMembresia = $queryMembresia->get_result();

        if ($resultadoMembresia->num_rows > 0) {
            $membresia = $resultadoMembresia->fetch_assoc();
            $membresia_id = $membresia['id'];
            $clases_restantes = $membresia['clases_disponibles'] - 1;

            // Registrar asistencia
            $insertAsistencia = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)");
            $insertAsistencia->bind_param("iss", $cliente_id, $fecha_hoy, $hora_actual);
            $insertAsistencia->execute();

            // Descontar clase
            $updateClases = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE id = ?");
            $updateClases->bind_param("ii", $clases_restantes, $membresia_id);
            $updateClases->execute();

            echo "<div style='color:lime;font-size:22px;font-family:sans-serif;background:black;padding:20px;text-align:center'>";
            echo "<h2>✅ Ingreso registrado</h2>";
            echo "<p><strong>{$cliente['apellido']}, {$cliente['nombre']}</strong></p>";
            echo "<p>Disciplina: <strong>{$cliente['disciplina']}</strong></p>";
            echo "<p>Clases restantes: <strong>{$clases_restantes}</strong></p>";
            echo "<p>Vence: <strong>{$membresia['fecha_vencimiento']}</strong></p>";
            echo "<p>Hora: <strong>{$hora_actual}</strong></p>";
            echo "</div>";
        } else {
            echo "<div style='color:orange;font-size:20px;text-align:center;padding:20px;'>⚠️ Sin membresía activa o sin clases.</div>";
        }
    } else {
        echo "<div style='color:red;font-size:20px;text-align:center;padding:20px;'>❌ Cliente no encontrado.</div>";
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR</title>
    <style>
        body {
            background-color: black;
            color: yellow;
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
        }
        video {
            width: 90%;
            max-width: 400px;
            margin-top: 20px;
            border: 2px solid yellow;
        }
    </style>
</head>
<body>
    <h2>Escaneo QR para Ingreso</h2>
    <div id="reader" style="width:100%; display: flex; justify-content: center;"></div>

    <form id="formulario" method="POST" style="display: none;">
        <input type="hidden" name="dni" id="dni_input">
    </form>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const qrScanner = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: 250 };

        qrScanner.start(
            { facingMode: "environment" }, config,
            (decodedText) => {
                qrScanner.stop();
                document.getElementById("dni_input").value = decodedText.trim();
                document.getElementById("formulario").submit();
            },
            (errorMessage) => {}
        ).catch((err) => {
            alert("Error al acceder a la cámara: " + err);
        });
    </script>
</body>
</html>
