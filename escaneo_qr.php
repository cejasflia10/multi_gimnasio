<?php
session_start();
if (!isset($_GET['dni'])) {
    echo "<p style='color:red;'>DNI no recibido.</p>";
    exit();
}

include "conexion.php";
$dni = $_GET['dni'];
$fecha_actual = date("Y-m-d");
$hora_actual = date("H:i:s");

// Buscar cliente
$sql_cliente = "SELECT * FROM clientes WHERE dni = ?";
$stmt_cliente = $conexion->prepare($sql_cliente);
$stmt_cliente->bind_param("s", $dni);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();

if ($result_cliente->num_rows === 0) {
    echo "<p style='color:red;'>Cliente no encontrado.</p>";
    exit();
}

$cliente = $result_cliente->fetch_assoc();
$cliente_id = $cliente['id'];
$nombre = $cliente['nombre'] . ' ' . $cliente['apellido'];

// Buscar membresía activa
$sql_membresia = "SELECT * FROM membresias WHERE cliente_id = ? AND fecha_vencimiento >= ? AND clases_disponibles > 0 ORDER BY fecha_vencimiento DESC LIMIT 1";
$stmt_membresia = $conexion->prepare($sql_membresia);
$stmt_membresia->bind_param("is", $cliente_id, $fecha_actual);
$stmt_membresia->execute();
$result_membresia = $stmt_membresia->get_result();

if ($result_membresia->num_rows === 0) {
    echo "<p style='color:red;'>❌ No se encontró membresía activa.</p>";
    exit();
}

$membresia = $result_membresia->fetch_assoc();
$membresia_id = $membresia['id'];
$clases_restantes = $membresia['clases_disponibles'] - 1;
$fecha_vencimiento = $membresia['fecha_vencimiento'];

// Actualizar clases disponibles
$update_sql = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = ?";
$stmt_update = $conexion->prepare($update_sql);
$stmt_update->bind_param("i", $membresia_id);
$stmt_update->execute();

// Registrar asistencia
$sql_asistencia = "INSERT INTO asistencias (cliente_id, fecha, hora, metodo) VALUES (?, ?, ?, 'QR')";
$stmt_asistencia = $conexion->prepare($sql_asistencia);
$stmt_asistencia->bind_param("iss", $cliente_id, $fecha_actual, $hora_actual);
$stmt_asistencia->execute();

// Mostrar resultado
?>
<div style="color:white; text-align:center; font-size:20px;">
    <p>✅ <strong>Ingreso registrado correctamente</strong></p>
    <p>Cliente: <strong><?php echo $nombre; ?></strong></p>
    <p>Clases restantes: <strong><?php echo $clases_restantes; ?></strong></p>
    <p>Vencimiento: <strong><?php echo date("d/m/Y", strtotime($fecha_vencimiento)); ?></strong></p>
</div>
