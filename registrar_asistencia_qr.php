<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $fecha_hoy = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar cliente
    $queryCliente = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ?");
    $queryCliente->bind_param("s", $dni);
    $queryCliente->execute();
    $resultadoCliente = $queryCliente->get_result();

    if ($resultadoCliente->num_rows > 0) {
        $cliente = $resultadoCliente->fetch_assoc();
        $cliente_id = $cliente['id'];

        // Buscar membresía activa
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

            // Actualizar clases disponibles
            $updateClases = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE id = ?");
            $updateClases->bind_param("ii", $clases_restantes, $membresia_id);
            $updateClases->execute();

            // Mostrar confirmación
            echo "<div style='color:lime;font-size:22px;font-family:sans-serif;background:black;padding:20px;text-align:center'>";
            echo "<h2>✅ Ingreso registrado</h2>";
            echo "<p>Nombre: <strong>" . $cliente['apellido'] . ", " . $cliente['nombre'] . "</strong></p>";
            echo "<p>Clases restantes: <strong>" . $clases_restantes . "</strong></p>";
            echo "<p>Fecha de vencimiento: <strong>" . $membresia['fecha_vencimiento'] . "</strong></p>";
            echo "<p>Hora: <strong>" . $hora_actual . "</strong></p>";
            echo "</div>";
        } else {
            echo "<div style='color:orange;font-size:20px;font-family:sans-serif;text-align:center;padding:20px;'>⚠️ No tiene membresía activa o clases disponibles.</div>";
        }
    } else {
        echo "<div style='color:red;font-size:20px;font-family:sans-serif;text-align:center;padding:20px;'>❌ Cliente no encontrado.</div>";
    }
} else {
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo QR para Ingreso</title>
    <style>
        body {
            background-color: black;
            color: yellow;
            text-align: center;
            font-family: Arial, sans-serif;
        }
        video {
            width: 80%;
            max-width: 500px;
            margin-top: 20px;
            border: 2px solid yellow;
        }
        #formulario {
            display: none;
        }
    </style>
</head>
<body>
    <h1>Escaneo QR para Ingreso</h1>
    <video id="preview"></video>
    <form id="formulario" method="POST">
        <input type="hidden" name="dni" id="dni_input">
    </form>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const scanner = new Html5QrcodeScanner("preview", { fps: 10, qrbox: 250 });
        scanner.render((decodedText) => {
            document.getElementById("dni_input").value = decodedText.trim();
            document.getElementById("formulario").submit();
        });
    </script>
</body>
</html>
<?php } ?>
