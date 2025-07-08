<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu_horizontal.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$resultado = $conexion->query("
    SELECT cc.cliente_id, c.nombre, c.apellido, SUM(cc.monto) AS saldo
    FROM cuentas_corrientes cc
    JOIN clientes c ON cc.cliente_id = c.id
    WHERE cc.gimnasio_id = $gimnasio_id
    GROUP BY cc.cliente_id
    HAVING saldo < 0
    ORDER BY saldo ASC
");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cuentas Corrientes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
    <h2>Clientes con Deuda (Cuenta Corriente)</h2>
    <table border="1" cellpadding="8">
        <tr><th>Cliente</th><th>Saldo</th><th>Acción</th></tr>
        <?php while($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $fila['apellido'] . ', ' . $fila['nombre'] ?></td>
            <td>$<?= number_format($fila['saldo'], 2) ?></td>
            <td><a href="registrar_pago_cc.php?cliente_id=<?= $fila['cliente_id'] ?>">Registrar Pago</a></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
