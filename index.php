<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
include 'menu.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$fecha_actual = date("Y-m-d");

// FUNCIONES
function getMonto($conexion, $tabla, $columna_fecha, $gimnasio_id, $tipo = 'DIA') {
    $fecha = ($tipo === 'DIA') ? date('Y-m-d') : date('Y-m');
    $operador = ($tipo === 'DIA') ? '=' : 'LIKE';
    $stmt = $conexion->prepare("SELECT SUM(monto) as total FROM $tabla WHERE gimnasio_id = ? AND $columna_fecha $operador ?");
    $param = ($tipo === 'DIA') ? $fecha : "$fecha%";
    $stmt->bind_param("is", $gimnasio_id, $param);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $fecha = date("Y-m-d");
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora
            FROM asistencias_clientes a
            JOIN clientes c ON c.id = a.cliente_id
            WHERE a.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha'
            ORDER BY a.fecha_hora DESC";
    return $conexion->query($sql);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha_actual) {
    $sql = "SELECT p.apellido, p.nombre, r.ingreso, r.salida
            FROM registro_profesores r
            JOIN profesores p ON p.id = r.profesor_id
            WHERE r.gimnasio_id = $gimnasio_id AND r.fecha = '$fecha_actual'
            ORDER BY r.ingreso DESC";
    return $conexion->query($sql);
}

$pagosDia = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagosMes = getMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventasDia = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventasMes = getMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

$asistenciasClientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$asistenciasProfesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha_actual);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { margin: 0; font-family: Arial, sans-serif; background-color: #111; color: #fff; }
        .panel-container { margin-left: 220px; padding: 20px; }
        .tarjeta { background: #222; border-radius: 10px; padding: 15px; margin: 10px 0; box-shadow: 0 0 5px #444; }
        .tarjeta h2 { margin-top: 0; font-size: 18px; color: #f1c40f; }
        .tarjeta p { font-size: 24px; font-weight: bold; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; }
        .col { flex: 1; min-width: 200px; }
        table { width: 100%; background: #000; color: #fff; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #444; text-align: left; font-size: 14px; }
        th { background: #333; color: #f1c40f; }
        @media screen and (max-width: 768px) {
            .panel-container { margin-left: 0; padding: 10px; }
            .row { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="panel-container">
    <h1>Bienvenido al Panel</h1>
    <div class="row">
        <div class="col tarjeta">
            <h2>Pagos del Día</h2>
            <p>$<?= number_format($pagosDia, 2) ?></p>
        </div>
        <div class="col tarjeta">
            <h2>Pagos del Mes</h2>
            <p>$<?= number_format($pagosMes, 2) ?></p>
        </div>
        <div class="col tarjeta">
            <h2>Ventas del Día</h2>
            <p>$<?= number_format($ventasDia, 2) ?></p>
        </div>
        <div class="col tarjeta">
            <h2>Ventas del Mes</h2>
            <p>$<?= number_format($ventasMes, 2) ?></p>
        </div>
    </div>

    <div class="tarjeta">
        <h2>Asistencias de Clientes - <?= $fecha_actual ?></h2>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
            <?php while ($row = $asistenciasClientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="tarjeta">
        <h2>Asistencias de Profesores - <?= $fecha_actual ?></h2>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
            <?php while ($row = $asistenciasProfesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['ingreso'] ?></td>
                <td><?= $row['salida'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>
