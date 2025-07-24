<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = intval($_POST['cliente_id'] ?? 0);
$monto = floatval($_POST['monto'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? 'Pago cuenta corriente');
$fecha = date('Y-m-d');

// Validar datos
if ($gimnasio_id > 0 && $cliente_id > 0 && $monto > 0) {
    // 1. Insertar en cuentas_corrientes (abono = monto positivo)
    $stmt = $conexion->prepare("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissd", $cliente_id, $gimnasio_id, $fecha, $descripcion, $monto);

    if ($stmt->execute()) {
        // 2. Insertar también en la tabla de pagos
        $stmt_pago = $conexion->prepare("INSERT INTO pagos (cliente_id, monto, fecha_pago, metodo_pago, gimnasio_id) VALUES (?, ?, ?, ?, ?)");
        $metodo_pago = "Cuenta Corriente";
        $stmt_pago->bind_param("idssi", $cliente_id, $monto, $fecha, $metodo_pago, $gimnasio_id);
        $stmt_pago->execute();
        $stmt_pago->close();

        // Mensaje de éxito
        echo "<!DOCTYPE html>
        <html lang='es'>
        <head>
            <meta charset='UTF-8'>
            <title>Pago Registrado</title>
            <link rel='stylesheet' href='estilo_unificado.css'>
        </head>
        <body>
        <div class='contenedor'>
            <h2 style='color:lime;'>✅ Pago registrado correctamente.</h2>
            <a class='btn-principal' href='ver_cuentas_corrientes.php'>Volver</a>
        </div>
        </body></html>";
    } else {
        echo "<div style='color:red;'>❌ Error al registrar el pago: " . $stmt->error . "</div>";
    }

    $stmt->close();
} else {
    echo "<div style='color:red;'>❌ Error: Datos incompletos o inválidos.</div>";
}
?>
