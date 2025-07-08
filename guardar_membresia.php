<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id']);
    if ($cliente_id <= 0) {
        echo "❌ Error: cliente_id inválido.";
        exit;
    }

    $plan_id = intval($_POST['plan_id']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_disponibles = intval($_POST['clases_disponibles']);
    $precio = floatval($_POST['precio']);
    $otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
    $descuento = floatval($_POST['descuento'] ?? 0);
    $fecha_actual = date('Y-m-d');

    // Pagos individuales
    $pago_efectivo = floatval($_POST['pago_efectivo'] ?? 0);
    $pago_transferencia = floatval($_POST['pago_transferencia'] ?? 0);
    $pago_debito = floatval($_POST['pago_debito'] ?? 0);
    $pago_credito = floatval($_POST['pago_credito'] ?? 0);
    $pago_cuenta_corriente = floatval($_POST['pago_cuenta_corriente'] ?? 0);

    // Total calculado
    $total_pagar = $precio + $otros_pagos - $descuento;
    $total_abonado = $pago_efectivo + $pago_transferencia + $pago_debito + $pago_credito;

    // Si no se paga completo, la diferencia va como deuda
    $saldo_cc = $total_pagar - $total_abonado;

    // Guardar membresía
    $metodo_pago = 'varios';
    $stmt = $conexion->prepare("INSERT INTO membresias 
        (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, gimnasio_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissiddddsdi",
        $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles,
        $precio, $otros_pagos, $descuento, $total_abonado, $metodo_pago, $saldo_cc, $gimnasio_id);
    $stmt->execute();
    $membresia_id = $conexion->insert_id;

    // Guardar deuda en cuenta corriente si corresponde
    if ($saldo_cc > 0) {
        $conexion->query("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto)
                          VALUES ($cliente_id, $gimnasio_id, '$fecha_actual', 'Deuda por membresía', -$saldo_cc)");
    }

    // Guardar adicionales si hay
    if (!empty($_POST['adicionales'])) {
        foreach ($_POST['adicionales'] as $adicional_id) {
            $adicional_id = intval($adicional_id);
            $conexion->query("INSERT INTO membresia_adicionales (membresia_id, adicional_id)
                              VALUES ($membresia_id, $adicional_id)");
        }
    }

    header("Location: ver_membresias.php");
    exit;
} else {
    echo "Acceso no permitido";
}
?>
