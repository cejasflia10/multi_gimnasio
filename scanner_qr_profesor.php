<?php
if (session_status() === PHP_SESSION_NONE) session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires'); // ✅ Agregado

include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mensaje = '';
$hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);

    // Buscar cliente con membresía activa desde ver_membresias.php
    $consulta = $conexion->query("
        SELECT c.id AS cliente_id, c.apellido, c.nombre, m.id AS membresia_id, m.clases_disponibles
        FROM clientes c
        JOIN membresias m ON c.id = m.cliente_id
        WHERE c.dni = '$dni'
          AND c.gimnasio_id = $gimnasio_id
          AND m.clases_disponibles > 0
          AND m.fecha_vencimiento >= CURDATE()
        ORDER BY m.fecha_inicio DESC
        LIMIT 1
    ");

    $datos = $consulta->fetch_assoc();

    if (!$datos) {
        $mensaje = "❌ Cliente no encontrado o sin membresía activa.";
    } else {
        $cliente_id = $datos['cliente_id'];
        $apellido = $datos['apellido'];
        $nombre = $datos['nombre'];
        $membresia_id = $datos['membresia_id'];

        // Ya ingresó hoy
        $yaIngreso = $conexion->query("
            SELECT id FROM asistencias_clientes
            WHERE cliente_id = $cliente_id AND fecha = '$hoy'
        ")->num_rows;

        if ($yaIngreso > 0) {
            $mensaje = "✅ $apellido $nombre ya ingresó hoy.";
        } else {
            $fechaHoraCompleta = date("Y-m-d H:i:s");
            $dia_semana = date("l");

            // Registrar asistencia
            $conexion->query("
                INSERT INTO asistencias_clientes (cliente_id, fecha_hora, fecha, hora, gimnasio_id)
                VALUES ($cliente_id, '$fechaHoraCompleta', '$hoy', '$hora_actual', $gimnasio_id)
            ");

            // Descontar clase
            $conexion->query("
                UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                WHERE id = $membresia_id
            ");

            // Ingreso profesor (una sola vez)
            $yaIngresoProf = $conexion->query("
                SELECT id FROM asistencias_profesor
                WHERE profesor_id = $profesor_id AND fecha = '$hoy'
            ")->num_rows;

            if ($yaIngresoProf == 0) {
                $conexion->query("
                    INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id)
                    VALUES ($profesor_id, '$hoy', '$hora_actual', $gimnasio_id)
                ");
            }

            // Calcular total alumnos del día
            $alumnos_q = $conexion->query("
                SELECT COUNT(DISTINCT cliente_id) AS cantidad
                FROM asistencias_clientes
                WHERE fecha = '$hoy' AND gimnasio_id = $gimnasio_id
            ");
            $alumnos = $alumnos_q->fetch_assoc()['cantidad'];

            // Buscar turno actual
            $turno_q = $conexion->query("
                SELECT * FROM turnos_profesor
                WHERE profesor_id = $profesor_id
                  AND dia = '$dia_semana'
                  AND hora_inicio <= '$hora_actual' AND hora_fin > '$hora_actual'
                  AND gimnasio_id = $gimnasio_id
                LIMIT 1
            ");

            if ($turno_q->num_rows > 0) {
                $turno = $turno_q->fetch_assoc();
                $h_inicio = $turno['hora_inicio'];
                $h_fin = $turno['hora_fin'];

                $cuantos = $conexion->query("
                    SELECT COUNT(*) AS total
                    FROM asistencias_profesor
                    WHERE profesor_id = $profesor_id AND fecha = '$hoy'
                      AND hora_ingreso BETWEEN '$h_inicio' AND '$h_fin'
                ")->fetch_assoc()['total'];

                $monto_turno = ($cuantos >= 10) ? 3000 : (($cuantos >= 5) ? 2000 : 1000);

                $yaExiste = $conexion->query("
                    SELECT id FROM asistencias_profesor
                    WHERE profesor_id = $profesor_id AND cliente_id = $cliente_id AND fecha = '$hoy'
                ")->num_rows;

                if ($yaExiste == 0) {
                    $conexion->query("
                        INSERT INTO asistencias_profesor
                        (profesor_id, cliente_id, fecha, hora_ingreso, gimnasio_id, monto_turno)
                        VALUES ($profesor_id, $cliente_id, '$hoy', '$hora_actual', $gimnasio_id, $monto_turno)
                    ");
                }

                $conexion->query("
                    UPDATE asistencias_profesor
                    SET monto_turno = $monto_turno
                    WHERE profesor_id = $profesor_id AND fecha = '$hoy'
                ");
            }

            $mensaje = "✅ $apellido $nombre ingresó correctamente. Total alumnos: $alumnos.";
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
