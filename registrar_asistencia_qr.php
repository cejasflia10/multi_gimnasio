
<?php
include 'conexion.php';
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);
    $fecha_hoy = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar cliente
    $stmt = $conexion->prepare("SELECT id, nombre, apellido, disciplina, gimnasio_id FROM clientes WHERE dni = ?");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $cliente_id = $cliente['id'];
        $gimnasio_id = $cliente['gimnasio_id'];
        $nombre = $cliente['nombre'];
        $apellido = $cliente['apellido'];
        $disciplina = $cliente['disciplina'];

        // Buscar membresía válida
        $stmtM = $conexion->prepare("SELECT id, clases_restantes, fecha_vencimiento FROM membresias 
            WHERE cliente_id = ? AND fecha_vencimiento >= ? AND clases_restantes > 0 
            ORDER BY fecha_vencimiento DESC LIMIT 1");
        $stmtM->bind_param("is", $cliente_id, $fecha_hoy);
        $stmtM->execute();
        $resM = $stmtM->get_result();

        if ($resM->num_rows > 0) {
            $membresia = $resM->fetch_assoc();
            $membresia_id = $membresia['id'];
            $clases_restantes = $membresia['clases_restantes'] - 1;

            // Registrar asistencia
            $stmtA = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)");
            $stmtA->bind_param("iss", $cliente_id, $fecha_hoy, $hora_actual);
            $stmtA->execute();

            // Actualizar clases restantes
            $stmtU = $conexion->prepare("UPDATE membresias SET clases_restantes = ? WHERE id = ?");
            $stmtU->bind_param("ii", $clases_restantes, $membresia_id);
            $stmtU->execute();

            echo "<!DOCTYPE html>
            <html><head><meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { background-color: #111; color: gold; font-family: sans-serif; text-align: center; padding: 40px; }
                .box { background-color: #222; padding: 20px; border-radius: 10px; display: inline-block; }
            </style></head><body>
            <div class='box'>
                <h2>✅ Ingreso registrado</h2>
                <p><strong>$apellido, $nombre</strong></p>
                <p>Disciplina: $disciplina</p>
                <p>Clases restantes: $clases_restantes</p>
                <p><a href='scanner_qr.php' style='color: lightgreen;'>📷 Escanear otro</a></p>
            </div></body></html>";
            exit;
        }
    }
}

// Si no hay cliente o membresía válida
echo "<!DOCTYPE html>
<html><head><meta name='viewport' content='width=device-width, initial-scale=1.0'>
<style>
    body { background-color: #111; color: gold; font-family: sans-serif; text-align: center; padding: 40px; }
</style></head><body>
<p>⚠️ Sin membresía activa o sin clases.</p>
<p><a href='scanner_qr.php' style='color: yellow;'>⬅️ Escanear otro</a></p>
</body></html>";
?>
