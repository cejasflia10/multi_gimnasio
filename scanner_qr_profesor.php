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
} else {
    // NUEVO BLOQUE: calcular monto turno y registrar asistencia profesor
$hora_actual = date('H:i:s');
$dia_actual = date('l'); // ej: Monday
$dias = [
    'Monday'=>'Lunes', 'Tuesday'=>'Martes', 'Wednesday'=>'Miércoles',
    'Thursday'=>'Jueves', 'Friday'=>'Viernes', 'Saturday'=>'Sábado'
];
$dia_semana = $dias[$dia_actual] ?? '';

$turno = $conexion->query("
    SELECT * FROM turnos_profesor 
    WHERE profesor_id = $profesor_id 
    AND gimnasio_id = $gimnasio_id 
    AND dia = '$dia_semana'
    AND '$hora_actual' BETWEEN hora_inicio AND hora_fin
    LIMIT 1
")->fetch_assoc();

if ($turno) {
    $hora_inicio = $turno['hora_inicio'];
    $hora_fin = $turno['hora_fin'];

    // Contar alumnos únicos que ingresaron en ese turno
    $total_alumnos = $conexion->query("
        SELECT COUNT(DISTINCT cliente_id) AS total
        FROM asistencias_clientes
        WHERE fecha = CURDATE()
        AND hora BETWEEN '$hora_inicio' AND '$hora_fin'
    ")->fetch_assoc()['total'];

    // Calcular monto del turno
    $monto_turno = 0;
    if ($total_alumnos >= 1 && $total_alumnos <= 3) {
        $monto_turno = 1000;
    } elseif ($total_alumnos >= 4 && $total_alumnos <= 10) {
        $monto_turno = 2000;
    } elseif ($total_alumnos > 10) {
        $monto_turno = 3000;
    }

    // Verificar si ya fue registrada la asistencia del profesor en ese turno
    $ya_existe = $conexion->query("
        SELECT id FROM asistencias_profesor 
        WHERE profesor_id = $profesor_id 
        AND fecha = CURDATE()
        AND hora_ingreso BETWEEN '$hora_inicio' AND '$hora_fin'
    ");

    if ($ya_existe->num_rows == 0 && $monto_turno > 0) {
        $conexion->query("
            INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id, monto_turno)
            VALUES ($profesor_id, CURDATE(), '$hora_actual', $gimnasio_id, $monto_turno)
        ");
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
