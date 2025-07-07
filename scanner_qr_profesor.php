<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$codigo_qr = trim($_POST['codigo_qr'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$mensaje = '';
$tipo = ''; // success | warning | error
$sonido = '';

$fecha = date('Y-m-d');
$hora = date('H:i:s');

if (empty($codigo_qr)) {
    $mensaje = "❌ Código QR vacío";
    $tipo = 'error';
} else {
    // PROFESOR
    $prof_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE dni = '$codigo_qr' AND gimnasio_id = $gimnasio_id");
    if ($prof_q && $prof_q->num_rows > 0) {
        $prof = $prof_q->fetch_assoc();
        $prof_id = $prof['id'];

        $verificar = $conexion->query("SELECT id FROM asistencias_profesores WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
        if ($verificar->num_rows > 0) {
            $conexion->query("UPDATE asistencias_profesores SET hora_salida = '$hora' WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
            $mensaje = "✅ Egreso del profesor <b>{$prof['apellido']} {$prof['nombre']}</b>";
        } else {
            $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso, gimnasio_id) VALUES ($prof_id, '$fecha', '$hora', $gimnasio_id)");
            $mensaje = "✅ Ingreso del profesor <b>{$prof['apellido']} {$prof['nombre']}</b>";
        }
        $tipo = 'success';
        $sonido = 'ok';
    } else {
        // CLIENTE
        $cli_q = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$codigo_qr' AND gimnasio_id = $gimnasio_id");
        if ($cli_q && $cli_q->num_rows > 0) {
            $cli = $cli_q->fetch_assoc();
            $cliente_id = $cli['id'];

            $membresia_q = $conexion->query("
                SELECT id, clases_disponibles, fecha_vencimiento 
                FROM membresias 
                WHERE cliente_id = $cliente_id 
                AND fecha_vencimiento >= '$fecha' 
                ORDER BY fecha_vencimiento DESC 
                LIMIT 1
            ");

            if ($membresia_q && $membresia_q->num_rows > 0) {
                $membresia = $membresia_q->fetch_assoc();
// Verificar si ya se registró ingreso hoy
$ya_asistio = $conexion->query("SELECT id FROM asistencias_clientes WHERE cliente_id = $cliente_id AND fecha = '$fecha'");

if ($ya_asistio && $ya_asistio->num_rows > 0) {
    $mensaje = "⚠️ Cliente <b>{$cli['apellido']} {$cli['nombre']}</b> ya registró asistencia hoy.";
    $tipo = 'warning';
    $sonido = 'error';
} else {
    // Registrar asistencia y descontar clase
    $conexion->query("INSERT INTO asistencias_clientes (cliente_id, fecha, hora_ingreso, gimnasio_id) VALUES ($cliente_id, '$fecha', '$hora', $gimnasio_id)");
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
            $mensaje = "❌ DNI no encontrado.";
            $tipo = 'error';
            $sonido = 'error';
        }
    }
}
// ▶️ Registrar asistencia del profesor (si está logueado)
if (isset($_SESSION['profesor_id'])) {
    $profesor_id = $_SESSION['profesor_id'];
    $gimnasio_id = $_SESSION['gimnasio_id'];
    
    $verificar = $conexion->query("
        SELECT id 
        FROM asistencias_profesores 
        WHERE profesor_id = $profesor_id 
          AND fecha = '$fecha' 
          AND hora_salida IS NULL
    ");

    if ($verificar->num_rows > 0) {
        // Ya tiene ingreso → registrar salida
        $conexion->query("UPDATE asistencias_profesores 
                          SET hora_salida = '$hora' 
                          WHERE profesor_id = $profesor_id 
                            AND fecha = '$fecha' 
                            AND hora_salida IS NULL");
    } else {
        // No tiene ingreso → registrar nuevo ingreso
        $conexion->query("INSERT INTO asistencias_profesores 
                          (profesor_id, fecha, hora_ingreso, gimnasio_id) 
                          VALUES ($profesor_id, '$fecha', '$hora', $gimnasio_id)");
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Asistencia</title>
    <meta http-equiv="refresh" content="3;url=scanner_qr.php">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
        }
        .mensaje {
            font-size: 22px;
            padding: 30px;
            margin: auto;
            width: 90%;
            border-radius: 10px;
            background-color: #111;
            border: 2px solid #555;
        }
        .success { border-color: lime; color: lime; }
        .warning { border-color: orange; color: orange; }
        .error { border-color: red; color: red; }
    </style>
</head>
<body>

<div class="mensaje <?= $tipo ?>">
    <?= $mensaje ?>
    <br><br><small>Redirigiendo en 3 segundos...</small>
</div>

<?php if ($sonido === 'ok'): ?>
<audio autoplay><source src="success.mp3" type="audio/mpeg"></audio>
<?php elseif ($sonido === 'error'): ?>
<audio autoplay><source src="error.mp3" type="audio/mpeg"></audio>
<?php endif; ?>

</body>
</html>
