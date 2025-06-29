<?php
session_start();
include 'conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');

// Mostrar errores para depuración
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$codigo_qr = $_POST['codigo_qr'] ?? '';

if (empty($codigo_qr)) {
    echo "<div style='color:red; text-align:center; margin-top:30px;'>❌ Código QR vacío<br><a href='scanner_qr.php'>← Volver</a></div>";
    exit;
}

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// ▶️ ESCANEO DE PROFESORES: código comienza con "P-"
if (strpos($codigo_qr, 'P-') === 0) {
    $dni_profesor = substr($codigo_qr, 2); // quitar "P-" para obtener el DNI

    $prof_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni_profesor' AND gimnasio_id = $gimnasio_id");
    if ($prof_q && $prof_q->num_rows > 0) {
        $prof = $prof_q->fetch_assoc();
        $prof_id = $prof['id'];
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        // Verificar si ya tiene una entrada sin salida hoy
        $verificar = $conexion->query("SELECT id FROM asistencias_profesor WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
        if ($verificar->num_rows > 0) {
            // Registrar salida
            $conexion->query("UPDATE asistencias_profesor SET hora_salida = '$hora' WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_salida IS NULL");
            echo "<div style='color:lime; text-align:center; margin-top:30px;'>✅ Egreso registrado correctamente<br><a href='scanner_qr.php'>← Volver</a></div>";
        } else {
            // Registrar ingreso
            $conexion->query("INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso, gimnasio_id) VALUES ($prof_id, '$fecha', '$hora', $gimnasio_id)");
            echo "<div style='color:lime; text-align:center; margin-top:30px;'>✅ Ingreso registrado correctamente<br><a href='scanner_qr.php'>← Volver</a></div>";
        }
    } else {
        echo "<div style='color:orange; text-align:center; margin-top:30px;'>⚠️ QR no válido para profesor<br><a href='scanner_qr.php'>← Volver</a></div>";
    }
    exit;
}

// Si no coincide con ningún patrón
echo "<div style='color:red; text-align:center; margin-top:30px;'>❌ Código QR no válido<br><a href='scanner_qr.php'>← Volver</a></div>";
exit;
?>
