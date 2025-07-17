<?php
session_start();
include 'conexion.php'; // Asegúrate de que esta ruta sea correcta
date_default_timezone_set('America/Argentina/Buenos_Aires');

$mensaje = '';
$tipo = '';
$sonido = '';

// Recuperar datos de sesión del profesor para el escáner
$profesor_id_esc_sesion = $_SESSION['profesor_id_esc_sesion'] ?? null;
$profesor_nombre_esc_sesion = $_SESSION['profesor_nombre_esc_sesion'] ?? null;
$gimnasio_id_esc_sesion = $_SESSION['gimnasio_id_esc_sesion'] ?? null;

$fecha = date('Y-m-d');
$hora = date('H:i:s');

$codigo_ingresado = ''; // Para mantener el valor en el campo DNI manual si hay un error

// --- Procesamiento del QR o DNI Manual (si se envió) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo_qr = trim($_POST['codigo_qr'] ?? '');
    $dni_manual = trim($_POST['dni_manual'] ?? '');

    $codigo_procesar = !empty($codigo_qr) ? $codigo_qr : $dni_manual;
    $codigo_ingresado = $dni_manual;

    error_log("DEBUG scanner_qr_profesor: Gimnasio ID Sesión (esc_sesion): " . ($gimnasio_id_esc_sesion ?? 'NULL'));
    error_log("DEBUG scanner_qr_profesor: Profesor ID Sesión (esc_sesion): " . ($profesor_id_esc_sesion ?? 'NULL'));
    error_log("DEBUG scanner_qr_profesor: Código a procesar: " . $codigo_procesar);

    if (empty($codigo_procesar)) {
        $mensaje = "❌ Código o DNI vacío. Intente de nuevo.";
        $tipo = 'error';
        $sonido = 'error';
    } else {
        $prof_q = $conexion->query("SELECT id, apellido, nombre, gimnasio_id FROM profesores WHERE dni = '$codigo_procesar'");
        if ($prof_q && $prof_q->num_rows > 0) {
            $prof = $prof_q->fetch_assoc();
            $prof_id_escaneado = $prof['id'];
            $prof_gimnasio_escaneado = $prof['gimnasio_id'];

            if ($profesor_id_esc_sesion === $prof_id_escaneado) {
                $verificar_turno_activo = $conexion->query("SELECT id FROM asistencias_profesores WHERE profesor_id = $prof_id_escaneado AND fecha = '$fecha' AND hora_salida IS NULL");
                if ($verificar_turno_activo->num_rows > 0) {
                    $conexion->query("UPDATE asistencias_profesores SET hora_salida = '$hora' WHERE profesor_id = $prof_id_escaneado AND fecha = '$fecha' AND hora_salida IS NULL");
                    $mensaje = "✅ Egreso del profesor <b>{$prof['apellido']} {$prof['nombre']}</b>. Sesión del escáner cerrada.";
                    $_SESSION['profesor_id_esc_sesion'] = null;
                    $_SESSION['profesor_nombre_esc_sesion'] = null;
                    $_SESSION['gimnasio_id_esc_sesion'] = null;
                    $tipo = 'success';
                    $sonido = 'ok';
                } else {
                    $mensaje = "⚠️ Profesor <b>{$prof['apellido']} {$prof['nombre']}</b> ya registró ingreso hoy o no tiene un turno abierto.";
                    $tipo = 'warning';
                    $sonido = 'error';
                }
            } else {
                $verificar_turno_activo = $conexion->query("SELECT id FROM asistencias_profesores WHERE profesor_id = $prof_id_escaneado AND fecha = '$fecha' AND hora_salida IS NULL");
                if ($verificar_turno_activo->num_rows > 0) {
                    $mensaje = "✅ Profesor <b>{$prof['apellido']} {$prof['nombre']}</b> ya tenía un turno abierto. Escáner listo para clientes.";
                } else {
                    $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso, gimnasio_id) VALUES ($prof_id_escaneado, '$fecha', '$hora', $prof_gimnasio_escaneado)");
                    $mensaje = "✅ Ingreso del profesor <b>{$prof['apellido']} {$prof['nombre']}</b>. Escáner listo para clientes.";
                }
                $_SESSION['profesor_id_esc_sesion'] = $prof_id_escaneado;
                $_SESSION['profesor_nombre_esc_sesion'] = $prof['apellido'] . ' ' . $prof['nombre'];
                $_SESSION['gimnasio_id_esc_sesion'] = $prof_gimnasio_escaneado;
                $profesor_id_esc_sesion = $_SESSION['profesor_id_esc_sesion'];
                $profesor_nombre_esc_sesion = $_SESSION['profesor_nombre_esc_sesion'];
                $gimnasio_id_esc_sesion = $_SESSION['gimnasio_id_esc_sesion'];
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
                          AND (fecha_vencimiento >= '$fecha' OR clases_disponibles > 0)
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
                            $clases_restantes = $membresia['clases_disponibles'] - 1;

                            $mensaje = "✅ Ingreso del cliente <b>{$cli['apellido']} {$cli['nombre']}</b><br>
                                         🗓️ Vence: <b>{$membresia['fecha_vencimiento']}</b><br>
                                         🎟️ Clases restantes: <b>{$clases_restantes}</b>";
                            $tipo = 'success';
                            $sonido = 'ok';
                        }
                    } else {
                        $mensaje = "⚠️ Cliente <b>{$cli['apellido']} {$cli['nombre']}</b> sin membresía activa.";
                        $tipo = 'warning';
                        $sonido = 'error';
                    }
                } else {
                    $mensaje = "❌ DNI de cliente no encontrado en este gimnasio.";
                    $tipo = 'error';
                    $sonido = 'error';
                }
            } else {
                $mensaje = "❌ Por favor, un profesor debe identificarse primero escaneando o ingresando su DNI.";
                $tipo = 'error';
                $sonido = 'error';
            }
        }
    }
} elseif (isset($_GET['cerrar_sesion_esc'])) {
    $_SESSION['profesor_id_esc_sesion'] = null;
    $_SESSION['profesor_nombre_esc_sesion'] = null;
    $_SESSION['gimnasio_id_esc_sesion'] = null;
    $profesor_id_esc_sesion = null;
    $profesor_nombre_esc_sesion = null;
    $gimnasio_id_esc_sesion = null;
    $mensaje = "✔️ Sesión de escáner cerrada.";
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
    <script src="instascan.min.js"></script> 
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 20px;
            box-sizing: border-box;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .contenedor {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            max-width: 600px;
            padding: 20px;
            box-sizing: border-box;
        }
        .cuadro-escanner {
            background-color: #222;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            text-align: center;
            margin-bottom: 20px;
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
        }
        video {
            width: 100%;
            max-width: 400px;
            height: auto;
            border: 2px solid gold;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .mensaje-info {
            color: #ccc;
            font-size: 1em;
            margin-bottom: 15px;
        }
        .mensaje-resultado {
            font-size: 1.2em;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
        }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .warning { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background-color: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }

        .form-manual {
            margin-top: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .form-manual input[type="text"] {
            width: calc(100% - 20px);
            padding: 10px;
            border: 1px solid #555;
            border-radius: 5px;
            background-color: #333;
            color: white;
            font-size: 1em;
            text-align: center;
        }
        .form-manual button {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        .form-manual button:hover {
            background-color: #0056b3;
        }
        .btn-cerrar-sesion {
            background-color: #dc3545;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            margin-top: 20px;
            transition: background-color 0.3s ease;
        }
        .btn-cerrar-sesion:hover {
            background-color: #c82333;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <div class="cuadro-escanner">
        <?php if ($profesor_id_esc_sesion): ?>
            <h3>Escáner listo para Clientes</h3>
            <p class="mensaje-info">👨‍🏫 Profesor: <b><?= htmlspecialchars($profesor_nombre_esc_sesion) ?></b> (Gimnasio ID: <?= htmlspecialchars($gimnasio_id_esc_sesion) ?>)</p>
            <p class="mensaje-info">Escanee el QR del cliente o ingrese su DNI.</p>
        <?php else: ?>
            <h3>Identificación del Profesor</h3>
            <p class="mensaje-info">Por favor, escanea tu QR de profesor o ingresa tu DNI para iniciar tu turno y activar el escáner.</p>
        <?php endif; ?>

        <video id="preview"></video>

        <form id="qrForm" method="POST" style="display: none;">
            <input type="hidden" name="codigo_qr" id="codigo_qr_input">
        </form>

        <div class="form-manual">
            <input type="text" id="dni_manual_input" name="dni_manual" placeholder="O ingresar DNI manualmente" value="<?= htmlspecialchars($codigo_ingresado) ?>" pattern="\d*" title="Solo números para el DNI">
            <button type="button" onclick="submitManualDNI();">Ingresar DNI</button>
        </div>
        <form id="manualForm" method="POST" style="display: none;">
            <input type="hidden" name="dni_manual" id="dni_manual_hidden">
            <input type="hidden" name="codigo_qr" value="">
        </form>


        <?php if (!empty($mensaje)): ?>
            <div class="mensaje-resultado <?= $tipo ?>">
                <?= $mensaje ?>
            </div>
        <?php endif; ?>

        <?php if ($profesor_id_esc_sesion): ?>
            <button class="btn-cerrar-sesion" onclick="location.href='scanner_qr_profesor.php?cerrar_sesion_esc=1'">Cerrar Sesión de Escáner</button>
        <?php endif; ?>
    </div>
</div>

<script type="text/javascript">
    let scanner = new Instascan.Scanner({ video: document.getElementById('preview'), scanPeriod: 5 });

    scanner.addListener('scan', function (content) {
        document.getElementById('codigo_qr_input').value = content;
        document.getElementById('dni_manual_input').value = ''; // Limpiar campo manual al escanear
        document.getElementById('dni_manual_hidden').value = '';
        document.getElementById('qrForm').submit();
    });

    function submitManualDNI() {
        document.getElementById('dni_manual_hidden').value = document.getElementById('dni_manual_input').value;
        document.getElementById('codigo_qr_input').value = ''; // Limpiar campo QR al enviar manual
        document.getElementById('manualForm').submit();
    }

    document.querySelector('.form-manual button').addEventListener('click', submitManualDNI);

    document.getElementById('dni_manual_input').addEventListener('keypress', function(event) {
        if (event.keyCode === 13) {
            event.preventDefault();
            submitManualDNI();
        }
    });

    Instascan.Camera.getCameras().then(function (cameras) {
        if (cameras.length > 0) {
            scanner.start(cameras[0]);
        } else {
            console.error('No cameras found.');
            document.getElementById('preview').style.display = 'none';
            let noCamMessage = document.createElement('p');
            noCamMessage.style.color = 'red';
            noCamMessage.innerText = 'No se detectaron cámaras. Por favor, use el ingreso de DNI manual.';
            document.querySelector('.cuadro-escanner').insertBefore(noCamMessage, document.getElementById('qrForm'));
        }
    }).catch(function (e) {
        console.error(e);
        alert('Error al acceder a la cámara: ' + e);
        document.getElementById('preview').style.display = 'none';
    });

    <?php if ($sonido === 'ok'): ?>
    var audio = new Audio('success.mp3');
    audio.play();
    <?php elseif ($sonido === 'error'): ?>
    var audio = new Audio('error.mp3');
    audio.play();
    <?php endif; ?>

    window.onload = function() {
        <?php if (!$profesor_id_esc_sesion): ?>
            document.getElementById('dni_manual_input').focus();
        <?php endif; ?>
    };
</script>
</body>
</html>