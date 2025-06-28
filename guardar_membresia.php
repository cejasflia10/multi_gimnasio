<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

if (!isset($_SESSION['gimnasio_id'])) {
    die("Gimnasio no identificado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cliente_id = $_POST['cliente_id'];
    $plan_id = $_POST['plan_id'];
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $precio = floatval($_POST['precio']);
    $clases_disponibles = intval($_POST['clases_disponibles']);
    $pagos_adicionales = floatval($_POST['pagos_adicionales'] ?? 0);
    $otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
    $forma_pago = $_POST['forma_pago'];
    $total = floatval($_POST['total']);
    $duracion_meses = intval($_POST['duracion_meses']);

    // NUEVOS CAMPOS
    $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
    $saldo_cc = floatval($_POST['saldo_cc'] ?? 0);

    // Insertar la nueva membresía
    $stmt = $conexion->prepare("INSERT INTO membresias (
        cliente_id, plan_id, fecha_inicio, fecha_vencimiento, precio, clases_disponibles,
        pagos_adicionales, otros_pagos, forma_pago, total, monto_pagado, saldo_cc, gimnasio_id, duracion_meses
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("iissdiddsddiii",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $precio, $clases_disponibles,
        $pagos_adicionales, $otros_pagos, $forma_pago, $total, $monto_pagado, $saldo_cc, $gimnasio_id, $duracion_meses
    );

    if ($stmt->execute()) {
        echo "<script>alert('Membresía registrada correctamente'); window.location.href='ver_membresias.php';</script>";
    } else {
        echo "<script>alert('Error al registrar la membresía: " . $stmt->error . "'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='index.php';</script>";
}
?>
