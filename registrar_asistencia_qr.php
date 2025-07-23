<?php
session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$dni = trim($_POST['dni'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (!$dni || !$gimnasio_id) {
    echo "<span style='color:red;'>❌ Datos inválidos.</span>";
    exit;
}

// Buscar cliente
$cliente_q = $conexion->query("
    SELECT c.id, c.apellido, c.nombre, c.dni, m.clases_disponibles, m.fecha_vencimiento
    FROM clientes c
    INNER JOIN membresias m ON c.id = m.cliente_id
    WHERE c.dni = '$dni' AND c.gimnasio_id = $gimnasio_id
    ORDER BY m.fecha_inicio DESC
    LIMIT 1
");

if ($cliente_q && $cliente_q->num_rows > 0) {
    $cliente = $cliente_q->fetch_assoc();

    $cliente_id = $cliente['id'];
    $nombre = $cliente['apellido'] . ' ' . $cliente['nombre'];
    $clases = $cliente['clases_disponibles'];
    $vencimiento = $cliente['fecha_vencimiento'];

    $hoy = date('Y-m-d');
    $hora = date('H:i:s');

    if ($vencimiento < $hoy || $clases <= 0) {
        echo "<span style='color:red;'>❌ Membresía vencida o sin clases.<br>Cliente: $nombre<br>Vencimiento: $vencimiento<br>Clases: $clases</span>";
        exit;
    }

    // Registrar asistencia
    $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora, gimnasio_id) VALUES ($cliente_id, '$hoy', '$hora', $gimnasio_id)");

    // Descontar clase
    $conexion->query("UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE cliente_id = $cliente_id AND fecha_vencimiento >= '$hoy'");

    echo "<span style='color:lightgreen;'>✅ Ingreso registrado.<br>Cliente: $nombre<br>Clases restantes: " . ($clases - 1) . "<br>Vencimiento: $vencimiento</span>";
} else {
    echo "<span style='color:red;'>❌ Cliente no encontrado o sin membresía activa.</span>";
}
?>
