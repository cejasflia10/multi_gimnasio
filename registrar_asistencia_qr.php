<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $dni = $_POST["dni"];

    // Buscar cliente
    $clienteQuery = $conexion->prepare("SELECT id, nombre, apellido FROM clientes WHERE dni = ?");
    $clienteQuery->bind_param("s", $dni);
    $clienteQuery->execute();
    $clienteResult = $clienteQuery->get_result();

    if ($clienteResult->num_rows === 0) {
        echo "<h3 style='color: orange;'>⚠️ Cliente no encontrado.</h3>";
        exit;
    }

    $cliente = $clienteResult->fetch_assoc();
    $clienteId = $cliente['id'];

    // Verificar membresía activa
    $membresiaQuery = $conexion->prepare("SELECT id, clases_disponibles, fecha_vencimiento FROM membresias WHERE cliente_id = ? ORDER BY fecha_vencimiento DESC LIMIT 1");
    $membresiaQuery->bind_param("i", $clienteId);
    $membresiaQuery->execute();
    $membresiaResult = $membresiaQuery->get_result();

    if ($membresiaResult->num_rows === 0) {
        echo "<h3 style='color: orange;'>⚠️ Sin membresía registrada.</h3>";
        exit;
    }

    $membresia = $membresiaResult->fetch_assoc();
    $clases = $membresia['clases_disponibles'];
    $vencimiento = $membresia['fecha_vencimiento'];
    $hoy = date('Y-m-d');

    if ($clases <= 0 || $vencimiento < $hoy) {
        echo "<h3 style='color: orange;'>⚠️ Sin membresía activa o sin clases.</h3>";
        exit;
    }

    // Descontar una clase
    $nuevasClases = $clases - 1;
    $updateQuery = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE id = ?");
    $updateQuery->bind_param("ii", $nuevasClases, $membresia['id']);
    $updateQuery->execute();

    // Registrar asistencia
    $insertQuery = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, CURDATE(), CURTIME())");
    $insertQuery->bind_param("i", $clienteId);
    $insertQuery->execute();

    echo "<h2 style='color: lightgreen;'>✅ Ingreso registrado: {$cliente['nombre']} {$cliente['apellido']}</h2>";
    echo "<p>Clases restantes: $nuevasClases</p>";
    echo "<p>Válido hasta: $vencimiento</p>";
}
?>
