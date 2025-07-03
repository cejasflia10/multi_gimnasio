<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mensaje = '';
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);

    $cliente = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id")->fetch_assoc();
    if (!$cliente) {
        $mensaje = "Cliente no encontrado.";
    } else {
        $cliente_id = $cliente['id'];

        $membresia = $conexion->query("SELECT * FROM membresias
            WHERE cliente_id = $cliente_id AND clases_disponibles > 0 AND fecha_vencimiento >= CURDATE()
            ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();

        if (!$membresia) {
            $mensaje = "El cliente no tiene membresía activa o sin clases.";
        } else {$fechaHoraCompleta = date("Y-m-d H:i:s");
$fecha = date("Y-m-d");
$hora = date("H:i:s");

$conexion->query("INSERT INTO asistencias_clientes (cliente_id, fecha_hora, fecha, hora, gimnasio_id)
                  VALUES ($cliente_id, '$fechaHoraCompleta', '$fecha', '$hora', $gimnasio_id)");


            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                              WHERE id = {$membresia['id']}");

            $yaIngreso = $conexion->query("SELECT * FROM asistencias_profesor
                WHERE profesor_id = $profesor_id AND fecha = '$hoy'")->fetch_assoc();

            if (!$yaIngreso) {
                $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id)
                                  VALUES ($profesor_id, '$hoy', '$hora_actual', $gimnasio_id)");
            }

            $alumnos_q = $conexion->query("
                SELECT COUNT(DISTINCT ac.cliente_id) AS cantidad
                FROM asistencias_clientes ac
                JOIN reservas_clientes rc ON ac.cliente_id = rc.cliente_id
                WHERE rc.profesor_id = $profesor_id AND ac.fecha = '$hoy' AND rc.gimnasio_id = $gimnasio_id
            ");
            $alumnos = $alumnos_q->fetch_assoc()['cantidad'];

            if ($alumnos >= 5) {
                $monto = 2000;
            } elseif ($alumnos >= 2) {
                $monto = 1500;
            } else {
                $monto = 1000;
            }

            $conexion->query("UPDATE asistencias_profesor 
                              SET monto_turno = $monto 
                              WHERE profesor_id = $profesor_id AND fecha = '$hoy'");

            $mensaje = "✅ {$cliente['apellido']} {$cliente['nombre']} ingresó correctamente. Total alumnos: $alumnos. Monto turno: $$monto";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escáner QR Profesor</title>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        body { background-color: black; color: gold; font-family: Arial; text-align: center; padding: 20px; }
        #reader { width: 300px; margin: auto; }
        .mensaje { margin-top: 20px; color: white; font-size: 18px; }
    </style>
</head>
<body>
    <h2>📲 Escanear QR de Alumno</h2>

    <div id="reader"></div>
    <form id="form_dni" method="POST" style="display:none;">
        <input type="hidden" name="dni" id="dni">
    </form>

    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>

<script>
function onScanSuccess(decodedText) {
    document.getElementById("dni").value = decodedText;
    document.getElementById("form_dni").submit();
    html5QrcodeScanner.clear();
}

let html5QrcodeScanner = new Html5QrcodeScanner("reader", {
    fps: 10,
    qrbox: 250,
    rememberLastUsedCamera: true
});
html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>
