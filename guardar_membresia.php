<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = intval($_POST['cliente_id']);
    $plan_id = intval($_POST['plan_id']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_vencimiento = $_POST['fecha_vencimiento'];
    $clases_disponibles = intval($_POST['clases_disponibles']);
    $precio = floatval($_POST['precio']);
    $otros_pagos = floatval($_POST['otros_pagos'] ?? 0);
    $descuento = floatval($_POST['descuento'] ?? 0);
    $total_pagar = floatval($_POST['total_pagar']);

echo \"Total recibido: \$total_pagar\";
exit;
    $metodo_pago = $_POST['metodo_pago'];

    $saldo_cc = ($metodo_pago === 'cuenta_corriente') ? -$total_pagar : 0;

    $stmt = $conexion->prepare("INSERT INTO membresias (cliente_id, plan_id, fecha_inicio, fecha_vencimiento, clases_disponibles, precio, otros_pagos, descuento, total_pagado, metodo_pago, saldo_cc, gimnasio_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissiddddsdi", $cliente_id, $plan_id, $fecha_inicio, $fecha_vencimiento, $clases_disponibles, $precio, $otros_pagos, $descuento, $total_pagar, $metodo_pago, $saldo_cc, $gimnasio_id);
    $stmt->execute();

    $membresia_id = $conexion->insert_id;

    if (!empty($_POST['adicionales'])) {
        foreach ($_POST['adicionales'] as $adicional_id) {
            $adicional_id = intval($adicional_id);
            $conexion->query("INSERT INTO membresia_adicionales (membresia_id, adicional_id) VALUES ($membresia_id, $adicional_id)");
        }
    }

    header("Location: ver_membresias.php");
    exit;
} else {
    echo "Acceso no permitido";
}
?>
