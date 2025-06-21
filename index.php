<?php
include("menu.php");
include("conexion.php");

function getMonto($conexion, $tabla, $columnaFecha, $cantidad, $tipo)
{
    $fechaInicio = ($tipo == 'DIA') ? "CURDATE()" : "DATE_SUB(CURDATE(), INTERVAL $cantidad MONTH)";
    $query = "SELECT SUM(monto) as total FROM $tabla WHERE DATE($columnaFecha) >= $fechaInicio";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

$pagosDia = getMonto($conexion, 'pagos', 'fecha', 1, 'DIA');
$pagosMes = getMonto($conexion, 'pagos', 'fecha', 1, 'MES');
$ventasDia = getMonto($conexion, 'ventas', 'fecha', 1, 'DIA');
$ventasMes = getMonto($conexion, 'ventas', 'fecha', 1, 'MES');

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <style>
        body {
            background-color: #111;
            color: #f1f1f1;
            font-family: Arial, sans-serif;
            margin: 0;
        }
        .panel {
            padding: 20px;
        }
        .tarjeta {
            background-color: #222;
            border: 1px solid #555;
            padding: 20px;
            margin: 10px;
            border-radius: 12px;
            display: inline-block;
            width: 200px;
            text-align: center;
        }
        .titulo {
            font-size: 1.1em;
            margin-bottom: 10px;
            color: #ffcc00;
        }
        .valor {
            font-size: 1.8em;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="panel">
    <div class="tarjeta">
        <div class="titulo">Pagos del Día</div>
        <div class="valor">$<?= number_format($pagosDia, 2) ?></div>
    </div>
    <div class="tarjeta">
        <div class="titulo">Pagos del Mes</div>
        <div class="valor">$<?= number_format($pagosMes, 2) ?></div>
    </div>
    <div class="tarjeta">
        <div class="titulo">Ventas del Día</div>
        <div class="valor">$<?= number_format($ventasDia, 2) ?></div>
    </div>
    <div class="tarjeta">
        <div class="titulo">Ventas del Mes</div>
        <div class="valor">$<?= number_format($ventasMes, 2) ?></div>
    </div>
</div>

</body>
</html>
