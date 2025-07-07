<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($profesor_id == 0 || $gimnasio_id == 0) {
    echo "Acceso denegado.";
    exit;
}

$mensaje = "";
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    $fecha_hora = date('Y-m-d H:i:s');

    $buscar = $conexion->query("SELECT id, nombre, apellido FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
    if ($buscar->num_rows > 0) {
        $cliente = $buscar->fetch_assoc();
        $cliente_id = $cliente['id'];

        // Registrar el ingreso del alumno al turno del profesor
        $conexion->query("
            INSERT INTO alumnos_turno_profesor 
            (profesor_id, cliente_id, fecha, hora, fecha_hora, gimnasio_id) 
            VALUES ($profesor_id, $cliente_id, '$fecha', '$hora', '$fecha_hora', $gimnasio_id)
        ");

        $mensaje = "✅ Ingreso registrado: " . $cliente['apellido'] . ", " . $cliente['nombre'];
    } else {
        $mensaje = "❌ Cliente no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escaneo Profesor</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background: #000; color: gold; text-align: center; font-family: Arial; padding: 20px; }
        .msg { font-size: 18px; margin: 10px 0; }
        #reader { width: 300px; margin: auto; }
    </style>
</head>
<body>

<h2>📲 Escanear Cliente</h2>

<div class="msg"><?= $mensaje ?></div>

<div id="reader"></div>

<form method="POST" id="formulario" style="display:none;">
    <input type="hidden" name="dni" id="dni">
</form>

<script>
function escanearQR() {
    const scanner = new Html5Qrcode("reader");
    scanner.start(
        { facingMode: "environment" },
        {
            fps: 10,
            qrbox: 250
        },
        (decodedText, decodedResult) => {
            scanner.stop().then(() => {
                document.getElementById('dni').value = decodedText;
                document.getElementById('formulario').submit();
            });
        },
        error => {}
    ).catch(err => {
        console.error("Error al iniciar escáner: ", err);
    });
}

window.onload = escanearQR;
</script>

</body>
</html>
