<?php
ob_start(); // Evita errores de encabezado
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $fecha_hoy = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar cliente
    $queryCliente = $conexion->prepare("SELECT id, nombre, apellido, disciplina, gimnasio_id FROM clientes WHERE dni = ?");
    $queryCliente->bind_param("s", $dni);
    $queryCliente->execute();
    $resultadoCliente = $queryCliente->get_result();

    if ($resultadoCliente->num_rows > 0) {
        $cliente = $resultadoCliente->fetch_assoc();
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];

        // Verificar membresía activa
        $queryMembresia = $conexion->prepare("SELECT id, clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? AND fecha_vencimiento >= ? AND clases_disponibles > 0 ORDER BY fecha_vencimiento DESC LIMIT 1");
        $queryMembresia->bind_param("is", $cliente_id, $fecha_hoy);
        $queryMembresia->execute();
        $resultadoMembresia = $queryMembresia->get_result();

        if ($resultadoMembresia->num_rows > 0) {
            $membresia = $resultadoMembresia->fetch_assoc();
            $membresia_id = $membresia['id'];
            $clases_restantes = $membresia['clases_disponibles'] - 1;

            // Registrar asistencia
            $insertAsistencia = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES (?, ?, ?, ?)");
            $insertAsistencia->bind_param("issi", $cliente_id, $fecha_hoy, $hora_actual, $gimnasio_id);
            $insertAsistencia->execute();

            // Descontar clase
            $updateClases = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE id = ?");
            $updateClases->bind_param("ii", $clases_restantes, $membresia_id);
            $updateClases->execute();

            echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Ingreso exitoso</title><style>
            body { background:black; color:lime; font-family:sans-serif; text-align:center; padding:30px; }
            .card { border:2px solid lime; padding:20px; border-radius:10px; display:inline-block; }
            </style></head><body>";

            echo "<div class='card'>";
            echo "<h2>✅ Ingreso registrado</h2>";
            echo "<p><strong>{$cliente['apellido']}, {$cliente['nombre']}</strong></p>";
            echo "<p>Disciplina: <strong>{$cliente['disciplina']}</strong></p>";
            echo "<p>Clases restantes: <strong>{$clases_restantes}</strong></p>";
            echo "<p>Vence: <strong>{$membresia['fecha_vencimiento']}</strong></p>";
            echo "<p>Hora: <strong>{$hora_actual}</strong></p>";
            echo "<br><a href='scanner_qr.php' style='color:yellow;'>⬅️ Escanear otro</a>";
            echo "</div></body></html>";
        } else {
            mostrarError("⚠️ Sin membresía activa o sin clases.");
        }
    } else {
        mostrarError("❌ Cliente no encontrado.");
    }
    exit;
}

function mostrarError($mensaje) {
    echo "<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Error</title><style>
    body { background:black; color:red; font-family:sans-serif; text-align:center; padding:30px; }
    .card { border:2px solid red; padding:20px; border-radius:10px; display:inline-block; }
    a { color:orange; display:block; margin-top:15px; }
    </style></head><body>";
    echo "<div class='card'>";
    echo "<h2>$mensaje</h2>";
    echo "<a href='scanner_qr.php'>⬅️ Volver a escanear</a>";
    echo "</div></body></html>";
}
?>
