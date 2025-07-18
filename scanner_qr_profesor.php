<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = '';
$tipo = '';
$sonido = '';
$codigo_ingresado = '';

$profesor_id_esc_sesion = $_SESSION['profesor_id_esc_sesion'] ?? null;
$profesor_nombre_esc_sesion = $_SESSION['profesor_nombre_esc_sesion'] ?? null;
$gimnasio_id_esc_sesion = $_SESSION['gimnasio_id_esc_sesion'] ?? null;

$fecha = date('Y-m-d');
$hora = date('H:i:s');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_qr = trim($_POST['codigo_qr'] ?? '');
    $dni_manual = trim($_POST['dni_manual'] ?? '');
    $codigo_procesar = !empty($codigo_qr) ? $codigo_qr : $dni_manual;
    $codigo_ingresado = $dni_manual;

    if (empty($codigo_procesar)) {
        $mensaje = "❌ Código o DNI vacío.";
        $tipo = 'error';
        $sonido = 'error';
    } else {
        $prof_q = $conexion->query("SELECT id, apellido, nombre, gimnasio_id FROM profesores WHERE dni = '$codigo_procesar'");
        if ($prof_q && $prof_q->num_rows > 0) {
            $prof = $prof_q->fetch_assoc();
            $prof_id = $prof['id'];
            $gimnasio_prof = $prof['gimnasio_id'];

            if ($profesor_id_esc_sesion == $prof_id) {
                $verif = $conexion->query("SELECT id FROM asistencias_profesores WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
                if ($verif->num_rows > 0) {
                    $conexion->query("UPDATE asistencias_profesores SET hora_salida = '$hora' WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
                    $mensaje = "✅ Egreso del profesor <b>{$prof['apellido']} {$prof['nombre']}</b>. Sesión cerrada.";
                    $_SESSION['profesor_id_esc_sesion'] = null;
                    $_SESSION['profesor_nombre_esc_sesion'] = null;
                    $_SESSION['gimnasio_id_esc_sesion'] = null;
                    $tipo = 'success';
                    $sonido = 'ok';
                } else {
                    $mensaje = "⚠️ Ya registró ingreso o no tiene un turno abierto.";
                    $tipo = 'warning';
                    $sonido = 'error';
                }
            } else {
                $verif = $conexion->query("SELECT id FROM asistencias_profesores WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
                if ($verif->num_rows > 0) {
                    $mensaje = "✅ Profesor ya tenía turno abierto. Escáner listo.";
                } else {
                    $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso, gimnasio_id) VALUES ($prof_id, '$fecha', '$hora', $gimnasio_prof)");
                    $mensaje = "✅ Ingreso de profesor <b>{$prof['apellido']} {$prof['nombre']}</b>. Escáner listo.";
                }

                $_SESSION['profesor_id_esc_sesion'] = $prof_id;
                $_SESSION['profesor_nombre_esc_sesion'] = $prof['apellido'] . ' ' . $prof['nombre'];
                $_SESSION['gimnasio_id_esc_sesion'] = $gimnasio_prof;

                $profesor_id_esc_sesion = $prof_id;
                $profesor_nombre_esc_sesion = $_SESSION['profesor_nombre_esc_sesion'];
                $gimnasio_id_esc_sesion = $gimnasio_prof;
                $tipo = 'success';
                $sonido = 'ok';
            }

        } else {
            if ($profesor_id_esc_sesion && $gimnasio_id_esc_sesion) {
                $cli_q = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$codigo_procesar' AND gimnasio_id = $gimnasio_id_esc_sesion");
                if ($cli_q && $cli_q->num_rows > 0) {
                    $cli = $cli_q->fetch_assoc();
                    $cliente_id = $cli['id'];

                    $membresia_q = $conexion->query("
                        SELECT id, clases_disponibles, fecha_vencimiento
                        FROM membresias
                        WHERE cliente_id = $cliente_id
                          AND fecha_vencimiento >= '$fecha'
                          AND clases_disponibles > 0
                        ORDER BY fecha_vencimiento DESC
                        LIMIT 1
                    ");

                    if ($membresia_q && $membresia_q->num_rows > 0) {
                        $membresia = $membresia_q->fetch_assoc();

                        $ya_asistio = $conexion->query("SELECT id FROM asistencias WHERE cliente_id = $cliente_id AND fecha = '$fecha' AND gimnasio_id = $gimnasio_id_esc_sesion");
                        if ($ya_asistio && $ya_asistio->num_rows > 0) {
                            $mensaje = "⚠️ Cliente <b>{$cli['apellido']} {$cli['nombre']}</b> ya registró asistencia hoy.";
                            $tipo = 'warning';
                            $sonido = 'error';
                        } else {
                            $conexion->query("INSERT INTO asistencias (cliente_id, profesor_id, fecha, hora, gimnasio_id) VALUES ($cliente_id, $profesor_id_esc_sesion, '$fecha', '$hora', $gimnasio_id_esc_sesion)");
                            $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = {$membresia['id']}");
                            $restantes = $membresia['clases_disponibles'] - 1;

                            $mensaje = "✅ Ingreso de <b>{$cli['apellido']} {$cli['nombre']}</b><br>🗓️ Vence: <b>{$membresia['fecha_vencimiento']}</b><br>🎟️ Clases restantes: <b>$restantes</b>";
                            $tipo = 'success';
                            $sonido = 'ok';
                        }

                    } else {
                        $mensaje = "⚠️ Cliente sin membresía activa.";
                        $tipo = 'warning';
                        $sonido = 'error';
                    }
                } else {
                    $mensaje = "❌ Cliente no encontrado.";
                    $tipo = 'error';
                    $sonido = 'error';
                }
            } else {
                $mensaje = "❌ Primero debe identificarse un profesor.";
                $tipo = 'error';
                $sonido = 'error';
            }
        }
    }
} elseif (isset($_GET['cerrar_sesion_esc'])) {
    $_SESSION['profesor_id_esc_sesion'] = null;
    $_SESSION['profesor_nombre_esc_sesion'] = null;
    $_SESSION['gimnasio_id_esc_sesion'] = null;
    $mensaje = "✔️ Sesión cerrada.";
    $tipo = 'success';
    $sonido = 'ok';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escáner QR / DNI - Asistencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="estilo_unificado.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { background-color: #000; color: gold; font-family: Arial; text-align: center; padding-top: 20px; }
        .contenedor { max-width: 600px; margin: auto; padding: 20px; }
        .cuadro-escanner { background-color: #222; padding: 20px; border-radius: 10px; }
        #reader { width: 100%; max-width: 400px; margin: auto; }
        .form-manual input, .form-manual button { margin-top: 10px; padding: 10px; border-radius: 5px; width: 90%; }
        .form-manual button { background-color: #007bff; color: white; border: none; }
        .btn-cerrar-sesion { margin-top: 20px; background-color: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; }
        .mensaje-resultado { margin-top: 20px; padding: 15px; border-radius: 10px; font-weight: bold; }
        .success { background-color: #d4edda; color: #155724; }
        .warning { background-color: #fff3cd; color: #856404; }
        .error { background-color: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cuadro-escanner">
        <?php if ($profesor_id_esc_sesion): ?>
            <h3>Escáner listo para Clientes</h3>
            <p>👨‍🏫 <b><?= htmlspecialchars($profesor_nombre_esc_sesion) ?></b> (Gimnasio <?= $gimnasio_id_esc_sesion ?>)</p>
        <?php else: ?>
            <h3>Identificación del Profesor</h3>
            <p>Escanea tu QR o ingresa tu DNI para iniciar tu turno</p>
        <?php endif; ?>

        <div id="reader"></div>

        <form id="qrForm" method="POST" style="display:none;">
            <input type="hidden" name="codigo_qr" id="codigo_qr_input">
        </form>

        <div class="form-manual">
            <input type="text" id="dni_manual_input" name="dni_manual" placeholder="Ingresar DNI" value="<?= htmlspecialchars($codigo_ingresado) ?>" pattern="\d*">
            <button type="button" onclick="submitManualDNI();">Ingresar DNI</button>
        </div>

        <form id="manualForm" method="POST" style="display: none;">
            <input type="hidden" name="dni_manual" id="dni_manual_hidden">
            <input type="hidden" name="codigo_qr" value="">
        </form>

        <?php if (!empty($mensaje)): ?>
            <div class="mensaje-resultado <?= $tipo ?>"><?= $mensaje ?></div>
        <?php endif; ?>

        <?php if ($profesor_id_esc_sesion): ?>
            <button class="btn-cerrar-sesion" onclick="location.href='scanner_qr_profesor.php?cerrar_sesion_esc=1'">Cerrar sesión</button>
        <?php endif; ?>
    </div>
</div>

<script>
function onScanSuccess(decodedText, decodedResult) {
    document.getElementById('codigo_qr_input').value = decodedText;
    document.getElementById('qrForm').submit();
}
function onScanFailure(error) {}

new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 }).render(onScanSuccess, onScanFailure);

function submitManualDNI() {
    document.getElementById('dni_manual_hidden').value = document.getElementById('dni_manual_input').value;
    document.getElementById('codigo_qr_input').value = '';
    document.getElementById('manualForm').submit();
}

<?php if ($sonido === 'ok'): ?>
    new Audio('success.mp3').play();
<?php elseif ($sonido === 'error'): ?>
    new Audio('error.mp3').play();
<?php endif; ?>

window.onload = () => {
    document.getElementById('dni_manual_input').focus();
};
</script>
</body>
</html>
