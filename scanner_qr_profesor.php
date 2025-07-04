
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
        $mensaje = "❌ Cliente no encontrado.";
    } else {
        $cliente_id = $cliente['id'];
        $apellido = $cliente['apellido'];
        $nombre = $cliente['nombre'];

        // Verificar si ya ingresó hoy
        $yaIngreso = $conexion->query("SELECT * FROM asistencias_clientes 
            WHERE cliente_id = $cliente_id AND fecha = '$hoy'")->num_rows;

        if ($yaIngreso > 0) {
            $mensaje = "✅ $apellido $nombre ya ingresó hoy.";
        } else {
            // Buscar membresía activa
            $membresia = $conexion->query("SELECT * FROM membresias
                WHERE cliente_id = $cliente_id AND clases_disponibles > 0 AND fecha_vencimiento >= CURDATE()
                ORDER BY fecha_inicio DESC LIMIT 1")->fetch_assoc();

            if (!$membresia) {
                $mensaje = "❌ Sin membresía activa o sin clases.";
            } else {
                $fechaHoraCompleta = date("Y-m-d H:i:s");
                $fecha = date("Y-m-d");
                $hora = date("H:i:s");
                $dia_semana = date("l");

                // Registrar asistencia cliente
                $conexion->query("INSERT INTO asistencias_clientes (cliente_id, fecha_hora, fecha, hora, gimnasio_id)
                                  VALUES ($cliente_id, '$fechaHoraCompleta', '$fecha', '$hora', $gimnasio_id)");

                $conexion->query("UPDATE membresias 
                                  SET clases_disponibles = clases_disponibles - 1 
                                  WHERE id = {$membresia['id']}");

                // Registrar ingreso de profesor si no está aún
                $yaIngresoProf = $conexion->query("SELECT * FROM asistencias_profesor
                    WHERE profesor_id = $profesor_id AND fecha = '$hoy'")->fetch_assoc();

                if (!$yaIngresoProf) {
                    $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id)
                                      VALUES ($profesor_id, '$hoy', '$hora_actual', $gimnasio_id)");
                }

                // Calcular cantidad de alumnos
                $alumnos_q = $conexion->query("
                    SELECT COUNT(DISTINCT ac.cliente_id) AS cantidad
                    FROM asistencias_clientes ac
                    WHERE ac.fecha = '$hoy' AND ac.gimnasio_id = $gimnasio_id
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

                // Asistencia por turno
                $turnos_prof = $conexion->query("SELECT * FROM turnos_profesor 
                    WHERE profesor_id = $profesor_id 
                    AND dia = '$dia_semana' 
                    AND hora_inicio <= '$hora_actual' AND hora_fin > '$hora_actual' 
                    AND gimnasio_id = $gimnasio_id 
                    LIMIT 1");

                if ($turnos_prof && $turnos_prof->num_rows > 0) {
                    $turno = $turnos_prof->fetch_assoc();
                    $h_inicio = $turno['hora_inicio'];
                    $h_fin = $turno['hora_fin'];

                    $cuantos_q = $conexion->query("SELECT COUNT(*) AS total 
                        FROM asistencias_profesor 
                        WHERE profesor_id = $profesor_id AND fecha = '$hoy'
                        AND hora_ingreso BETWEEN '$h_inicio' AND '$h_fin'");

                    $cuantos = $cuantos_q->fetch_assoc()['total'];

                    if ($cuantos >= 10) {
                        $monto_turno = 3000;
                    } elseif ($cuantos >= 5) {
                        $monto_turno = 2000;
                    } else {
                        $monto_turno = 1000;
                    }

                    $yaExiste = $conexion->query("SELECT * FROM asistencias_profesor
                        WHERE profesor_id = $profesor_id AND cliente_id = $cliente_id AND fecha = '$hoy'")->num_rows;

                    if ($yaExiste == 0) {
                        $conexion->query("INSERT INTO asistencias_profesor 
                            (profesor_id, cliente_id, fecha, hora_ingreso, gimnasio_id, monto_turno)
                            VALUES ($profesor_id, $cliente_id, '$hoy', '$hora_actual', $gimnasio_id, $monto_turno)");
                    }
                }

                $mensaje = "✅ $apellido $nombre ingresó correctamente. Total alumnos: $alumnos. Monto turno: $$monto";
            }
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
