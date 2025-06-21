<?php
session_start();
include("conexion.php");
include("menu_moderno.php");

// Suponiendo que el usuario está logueado y tiene gimnasio_id si aplica
$gimnasio_id = $_SESSION["gimnasio_id"] ?? null;

function getMonto($conexion, $tabla, $columna_fecha, $gimnasio_id = null, $periodo = "DIA") {
    $hoy = date("Y-m-d");
    $inicioMes = date("Y-m-01");

    $condicion_fecha = $periodo === "MES" ? "$columna_fecha >= '$inicioMes'" : "$columna_fecha = '$hoy'";
    $condicion_gimnasio = $gimnasio_id ? "AND gimnasio_id = $gimnasio_id" : "";

    $sql = "SELECT SUM(monto) AS total FROM $tabla WHERE $condicion_fecha $condicion_gimnasio";
    $resultado = $conexion->query($sql);
    $fila = $resultado->fetch_assoc();
    return $fila["total"] ?? 0;
}

function getProximosCumpleaños($conexion, $gimnasio_id = null) {
    $mes = date("m");
    $dia = date("d");
    $condicion_gimnasio = $gimnasio_id ? "AND gimnasio_id = $gimnasio_id" : "";
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $mes AND DAY(fecha_nacimiento) >= $dia $condicion_gimnasio 
            ORDER BY DAY(fecha_nacimiento) ASC LIMIT 5";
    return $conexion->query($sql);
}

function getProximosVencimientos($conexion, $gimnasio_id = null) {
    $hoy = date("Y-m-d");
    $limite = date("Y-m-d", strtotime("+10 days"));
    $condicion_gimnasio = $gimnasio_id ? "AND gimnasio_id = $gimnasio_id" : "";
    $sql = "SELECT c.nombre, c.apellido, m.fecha_vencimiento 
            FROM membresias m 
            JOIN clientes c ON m.cliente_id = c.id 
            WHERE m.fecha_vencimiento BETWEEN '$hoy' AND '$limite' $condicion_gimnasio 
            ORDER BY m.fecha_vencimiento ASC LIMIT 5";
    return $conexion->query($sql);
}

// Datos
$pagos_dia = getMonto($conexion, "pagos", "fecha", $gimnasio_id, "DIA");
$pagos_mes = getMonto($conexion, "pagos", "fecha", $gimnasio_id, "MES");
$ventas_dia = getMonto($conexion, "ventas", "fecha", $gimnasio_id, "DIA");
$ventas_mes = getMonto($conexion, "ventas", "fecha", $gimnasio_id, "MES");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Fight Academy</title>
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
            margin-left: 220px;
        }
        h1 {
            color: gold;
        }
        .tarjeta {
            background: #222;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 5px solid gold;
        }
    </style>
</head>
<body>
    <h1>Bienvenido al Panel</h1>

    <div class="tarjeta">💰 Pagos del día: $<?= $pagos_dia ?></div>
    <div class="tarjeta">💳 Pagos del mes: $<?= $pagos_mes ?></div>
    <div class="tarjeta">🛍️ Ventas del día: $<?= $ventas_dia ?></div>
    <div class="tarjeta">📦 Ventas del mes: $<?= $ventas_mes ?></div>

    <h2>🎂 Próximos Cumpleaños</h2>
    <?php
    $cumples = getProximosCumpleaños($conexion, $gimnasio_id);
    while ($row = $cumples->fetch_assoc()) {
        echo "<div class='tarjeta'>{$row['nombre']} {$row['apellido']} - " . date("d/m", strtotime($row['fecha_nacimiento'])) . "</div>";
    }
    ?>

    <h2>📅 Próximos Vencimientos</h2>
    <?php
    $vencimientos = getProximosVencimientos($conexion, $gimnasio_id);
    while ($row = $vencimientos->fetch_assoc()) {
        echo "<div class='tarjeta'>{$row['nombre']} {$row['apellido']} - Vence: " . date("d/m/Y", strtotime($row['fecha_vencimiento'])) . "</div>";
    }
    ?>
</body>
</html>
