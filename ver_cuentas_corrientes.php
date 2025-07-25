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
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuentas Corrientes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        body {
            background-color: #000;
            color: gold;
            font-family: Arial, sans-serif;
        }
        .contenedor {
            max-width: 900px;
            margin: auto;
            padding: 20px;
        }
        table {
            width: 100%;
            background-color: #111;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        .btn {
            padding: 6px 12px;
            font-weight: bold;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            margin: 2px;
        }
        .btn-pago {
            background-color: green;
            color: white;
        }
        .btn-eliminar {
            background-color: red;
            color: white;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>🧾 Clientes con Deuda (Cuenta Corriente)</h2>

    <table>
        <tr>
            <th>Cliente</th>
            <th>Saldo</th>
            <th>Acción</th>
        </tr>
        <?php while($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($fila['apellido'] . ', ' . $fila['nombre']) ?></td>
            <td>$<?= number_format($fila['saldo'], 2) ?></td>
            <td>
                <a href="registrar_pago_cc.php?cliente_id=<?= $fila['cliente_id'] ?>" class="btn btn-pago">Registrar Pago</a>
                <a href="eliminar_deuda_cc.php?cliente_id=<?= $fila['cliente_id'] ?>" class="btn btn-eliminar" onclick="return confirm('¿Estás seguro de eliminar la deuda de este cliente?')">Eliminar</a>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
</body>
</html>
