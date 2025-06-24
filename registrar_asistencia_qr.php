<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$gimnasio_id = $_SESSION["gimnasio_id"] ?? 0;

header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Asistencia QR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 20px;
        }
        .mensaje {
            font-size: 18px;
            margin-top: 20px;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin-top: 30px;
            background-color: gold;
            color: #111;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<?php
$dni = $_GET['dni'] ?? '';

if (!$dni || !$gimnasio_id) {
    echo "<p class='mensaje'>❌ Error: DNI o gimnasio no válidos.</p>";
    exit;
}

$consulta = "SELECT c.id, c.nombre, c.apellido, c.disciplina, m.clases_disponibles, m.fecha_vencimiento
             FROM clientes c
             JOIN membresias m ON c.id = m.cliente_id
             WHERE c.dni = '$dni' AND c.gimnasio_id = $gimnasio_id
             AND m.fecha_vencimiento >= CURDATE() AND m.clases_disponibles > 0
             ORDER BY m.fecha_vencimiento DESC LIMIT 1";
$resultado = $conexion->query($consulta);

if ($resultado && $resultado->num_rows > 0) {
    $cliente = $resultado->fetch_assoc();

    // Registrar asistencia
    $id_cliente = $cliente['id'];
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES ($id_cliente, '$fecha', '$hora', $gimnasio_id)");

    // Descontar clase
    $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE cliente_id = $id_cliente AND fecha_vencimiento >= CURDATE() ORDER BY fecha_vencimiento DESC LIMIT 1");

    echo "<h2>✅ Asistencia registrada</h2>";
    echo "<div class='mensaje'>";
    echo "👤 <strong>{$cliente['apellido']}, {$cliente['nombre']}</strong><br>";
    echo "🥋 Disciplina: {$cliente['disciplina']}<br>";
    echo "📅 Vencimiento: {$cliente['fecha_vencimiento']}<br>";

    // Reconsultar clases disponibles
    $nuevo = $conexion->query("SELECT clases_disponibles FROM membresias WHERE cliente_id = $id_cliente ORDER BY fecha_vencimiento DESC LIMIT 1");
    $nueva = $nuevo->fetch_assoc();
    $restantes = $nueva['clases_disponibles'];

    echo "📉 Clases restantes: $restantes";
    echo "</div>";
} else {
    echo "<p class='mensaje'>⚠️ Sin membresía activa o sin clases.</p>";
}
?>
<br><br>
<a href='scanner_qr.php' class='btn'>🔄 Escanear otro</a>
</body>
</html>
