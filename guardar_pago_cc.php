<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$monto = floatval($_POST['monto'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$fecha = date('Y-m-d');

// Validar datos
if ($gimnasio_id > 0 && $cliente_id > 0 && $monto > 0) {
    $stmt = $conexion->prepare("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha, $descripcion, $monto);
    
    if ($stmt->execute()) {
        echo "<div style='color:lime;'>✅ Pago registrado correctamente.</div>";
        echo "<a href='ver_cuentas_corrientes.php'>Volver</a>";
    } else {
        echo "<div style='color:red;'>❌ Error al registrar el pago: " . $stmt->error . "</div>";
    }

    $stmt->close();
} else {
    echo "<div style='color:red;'>❌ Error: Datos incompletos o inválidos.</div>";
}
?>
