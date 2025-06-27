
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST["dni"]);
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    $cliente_q = $conexion->query("SELECT * FROM clientes WHERE dni = '$dni' LIMIT 1");

    if ($cliente_q && $cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];

        echo "<div style='color:#00f;'>✅ Cliente encontrado: $nombre</div>";

        $membresia_q = $conexion->query("
            SELECT m.*, p.nombre AS plan_nombre FROM membresias m
            JOIN planes p ON m.plan_id = p.id
            WHERE m.cliente_id = $cliente_id
            ORDER BY m.fecha_vencimiento DESC
            LIMIT 1
        ");

        if ($membresia_q && $membresia_q->num_rows > 0) {
            $membresia = $membresia_q->fetch_assoc();
            $clases = (int)$membresia['clases_restantes'];
            $vto = $membresia['fecha_vencimiento'];
            $plan_nombre = $membresia['plan_nombre'];

            $asistencia_q = $conexion->query("
                SELECT * FROM asistencias 
                WHERE cliente_id = $cliente_id AND fecha = '$fecha'
            ");

            if ($asistencia_q->num_rows > 0) {
                echo "<div style='color:orange;'>⚠️ $nombre ya registró asistencia hoy.</div>
                      <div style='color:#ccc;'>📅 Vence: $vto<br>🎯 Clases: $clases</div>";
            } else if (($clases > 0 || $plan_nombre === 'FREE PASS') && $vto >= $fecha) {
                if ($plan_nombre !== 'FREE PASS') {
                    $conexion->query("UPDATE membresias SET clases_restantes = clases_restantes - 1 WHERE id = {$membresia['id']}");
                    $clases--;
                }

                $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
                echo "<div style='color:lime;'>✅ $nombre - Asistencia registrada</div>
                      <div style='color:#ccc;'>📅 Vence: $vto<br>🎯 Clases restantes: " . ($plan_nombre === 'FREE PASS' ? '∞' : $clases) . "<br>🕒 $hora</div>";
            } else {
                echo "<div style='color:yellow;'>⚠️ $nombre no tiene clases disponibles o está vencido</div>
                      <div style='color:#ccc;'>📅 Vence: $vto<br>🎯 Clases: $clases</div>";
            }
        } else {
            echo "<div style='color:orange;'>⚠️ $nombre no tiene membresía registrada</div>";
        }
    } else {
        echo "<div style='color:red;'>❌ DNI no encontrado</div>";
    }
}
?>
