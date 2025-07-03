<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

$fecha = date('Y-m-d');
$hora_actual = date('H:i:s');
$mensaje = "";
$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dni'])) {
    $dni = $conexion->real_escape_string($_POST['dni']);
    $cliente = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id")->fetch_assoc();

    if ($cliente) {
        $cliente_id = $cliente['id'];
        $apellido = $cliente['apellido'];
        $nombre = $cliente['nombre'];

        // Verificar si tiene membresía activa
        $membresia = $conexion->query("SELECT * FROM membresias WHERE cliente_id = $cliente_id AND fecha_vencimiento >= CURDATE() AND clases_disponibles > 0 ORDER BY id DESC LIMIT 1")->fetch_assoc();

        if ($membresia) {
            // Descontar clase
            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = {$membresia['id']}");

            // Registrar ingreso
            $conexion->query("INSERT INTO asistencias_clientes (cliente_id, gimnasio_id, fecha, hora) VALUES ($cliente_id, $gimnasio_id, '$fecha', '$hora_actual')");

            // Contar alumnos del turno actual (según hora exacta ± 30 minutos)
            $hora_inicio = date("H:i:s", strtotime("-30 minutes"));
            $hora_fin = date("H:i:s", strtotime("+30 minutes"));
            $alumnos_q = $conexion->query("SELECT COUNT(*) as total FROM asistencias_clientes WHERE fecha = '$fecha' AND hora BETWEEN '$hora_inicio' AND '$hora_fin' AND gimnasio_id = $gimnasio_id");
            $total_alumnos = $alumnos_q->fetch_assoc()['total'];

            // Obtener monto
            $monto_q = $conexion->query("SELECT monto FROM pago_profesor WHERE cantidad_alumnos = $total_alumnos LIMIT 1");
            $monto_turno = $monto_q->fetch_assoc()['monto'] ?? 0;

            // Registrar asistencia profesor si no está
            $ya_registrado = $conexion->query("SELECT * FROM asistencias_profesor WHERE profesor_id = $profesor_id AND fecha = '$fecha' AND hora_ingreso = '$hora_actual'")->num_rows;
            if ($ya_registrado == 0) {
                $conexion->query("INSERT INTO asistencias_profesor (profesor_id, gimnasio_id, fecha, hora_ingreso, monto_turno) VALUES ($profesor_id, $gimnasio_id, '$fecha', '$hora_actual', $monto_turno)");
            }

            $mensaje = "✅ $apellido $nombre ingresó correctamente. Total alumnos: $total_alumnos. Monto turno: \$$monto_turno";
        } else {
            $mensaje = "❌ $apellido $nombre no tiene clases disponibles o membresía activa.";
        }
    } else {
        $mensaje = "❌ DNI no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear QR</title>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background: black; color: gold; text-align: center; font-family: Arial; }
        .contenedor { margin-top: 30px; }
    </style>
</head>
<body>
<h3>📲 Escanear QR de Alumno</h3>
<div class="contenedor">
    <div id="reader" style="width:300px; margin:auto;"></div>
</div>
<?php if ($mensaje): ?>
    <p style="color: white; margin-top: 20px;"><?= $mensaje ?></p>
<?php endif; ?>
<script>
    function onScanSuccess(decodedText, decodedResult) {
        const form = new FormData();
        form.append("dni", decodedText);
        fetch("", { method: "POST", body: form })
            .then(() => location.reload());
        html5QrcodeScanner.clear();
    }

    const html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
    html5QrcodeScanner.render(onScanSuccess);
</script>
</body>
</html>
