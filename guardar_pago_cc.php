<?php
include 'conexion.php';
session_start();
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$cliente_id = intval($_POST['cliente_id'] ?? 0);
$monto = floatval($_POST['monto'] ?? 0);
$descripcion = trim($_POST['descripcion'] ?? '');
$fecha = date('Y-m-d');

if ($cliente_id && $monto > 0) {
    $conexion->query("INSERT INTO cuentas_corrientes (cliente_id, gimnasio_id, fecha, descripcion, monto)
                      VALUES ($cliente_id, $gimnasio_id, '$fecha', '$descripcion', $monto)");
    echo "<div style='color:lime;'>✅ Pago registrado</div>";
    echo "<a href='ver_cuentas_corrientes.php'>Volver</a>";
} else {
    echo "<div style='color:red;'>❌ Error en los datos enviados</div>";
}
?>
