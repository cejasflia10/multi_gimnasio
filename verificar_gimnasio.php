<?php include 'verificar_sesion.php'; ?>

<?php
session_start();
include 'conexion.php';

// Supongamos que el gimnasio activo se guarda en la sesión
$id_gimnasio = $_SESSION['gimnasio_id'];
$datos = $conexion->query("SELECT * FROM gimnasios WHERE id = $id_gimnasio")->fetch_assoc();

$clientes = $conexion->query("SELECT COUNT(*) AS total FROM clientes WHERE gimnasio_id = $id_gimnasio")->fetch_assoc();
$total_clientes = $clientes['total'];

if (date('Y-m-d') > $datos['fecha_vencimiento']) {
    echo "<div style='background:red;color:white;padding:10px;text-align:center;'>❌ El plan está vencido</div>";
} elseif ($total_clientes > $datos['cantidad_clientes']) {
    echo "<div style='background:orange;color:black;padding:10px;text-align:center;'>⚠️ Superó el límite de clientes del plan</div>";
}
?>
