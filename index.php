<?php
session_start();
include("conexion.php");

if (!isset($_SESSION['usuario'])) {
    $_SESSION['usuario'] = 'Invitado';
}
if (!isset($_SESSION['rol'])) {
    $_SESSION['rol'] = 'invitado';
}
if (!isset($_SESSION['gimnasio_id'])) {
    $_SESSION['gimnasio_id'] = 1; // Valor por defecto si no se cargó sesión
}
$gimnasio_id = $_SESSION['gimnasio_id'];

function getMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $periodo) {
    $condicion_fecha = "";
    if ($periodo == 'DIA') {
        $condicion_fecha = "AND DATE($campo_fecha) = CURDATE()";
    } elseif ($periodo == 'MES') {
        $condicion_fecha = "AND MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())";
    }

    $query = "SELECT SUM(monto) as total FROM $tabla WHERE gimnasio_id = $gimnasio_id $condicion_fecha";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

$pago_dia = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pago_mes = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

?>
<?php include("menu.php"); ?>
<div class="contenido">
    <h2>Bienvenido, <?php echo $_SESSION['usuario']; ?> (<?php echo $_SESSION['rol']; ?>)</h2>
    <h3>Panel de control de Fight Academy</h3>

    <div class="tarjeta">💵 Pagos del día: $<?php echo $pago_dia; ?></div>
    <div class="tarjeta">💳 Pagos del mes: $<?php echo $pago_mes; ?></div>
    <div class="tarjeta">🛒 Ventas del día: $<?php echo $ventas_dia; ?></div>
    <div class="tarjeta">🛍️ Ventas del mes: $<?php echo $ventas_mes; ?></div>

    <?php include("asistencias_index.php"); ?>
</div>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background-color: #111;
    color: gold;
}
.contenido {
    margin-left: 250px;
    padding: 20px;
}
.tarjeta {
    background: #222;
    color: gold;
    padding: 15px;
    margin: 10px 0;
    border-left: 5px solid gold;
    font-size: 18px;
    border-radius: 6px;
}
</style>
