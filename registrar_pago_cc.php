<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$cliente_id = intval($_GET['cliente_id'] ?? 0);

$cliente = $conexion->query("SELECT nombre, apellido FROM clientes WHERE id = $cliente_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registrar Pago</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
    <h2>Registrar Pago a Cuenta Corriente</h2>
    <p>Cliente: <strong><?= $cliente['apellido'] . ', ' . $cliente['nombre'] ?></strong></p>
    <form method="POST" action="guardar_pago_cc.php">
        <input type="hidden" name="cliente_id" value="<?= $cliente_id ?>">
        <label>Monto a pagar: $</label>
        <input type="number" step="0.01" name="monto" required><br><br>
        <label>Descripción:</label><br>
        <textarea name="descripcion" rows="3" cols="40">Pago cuenta corriente</textarea><br><br>
        <input type="submit" value="Guardar Pago">
    </form>
</div>
</body>
</html>
