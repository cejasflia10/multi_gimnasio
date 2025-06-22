<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$hoy = date("Y-m-d");

// Función general para sumar montos según tabla y campo de fecha
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES' ? "MONTH($campo_fecha) = MONTH(CURDATE())" : "$campo_fecha = CURDATE()";

    // Ajuste: usar el campo correcto para cada tabla
    if ($tabla === 'ventas') {
        $columna = 'monto_total';
    } elseif ($tabla === 'pagos') {
        $columna = 'monto';
    } else {
        $columna = 'monto'; // valor por defecto
    }

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}


// Cumpleaños del mes
function obtenerCumpleaños($conexion, $gimnasio_id) {
    $mes = date('m');
    $query = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
              WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

// Vencimientos próximos (10 días)
function obtenerVencimientos($conexion, $gimnasio_id) {
    $query = "SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m
              JOIN clientes c ON c.id = m.cliente_id
              WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
              AND m.id_gimnasio = $gimnasio_id
              ORDER BY m.fecha_vencimiento ASC";
    return $conexion->query($query);
}

// Asistencias del día
function obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha) {
    $query = "SELECT c.nombre, c.apellido, c.disciplina, r.hora FROM registros_clientes r
              JOIN clientes c ON c.id = r.cliente_id
              WHERE r.fecha = '$fecha' AND r.gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

// Ingresos profesores del día
function obtenerIngresosProfesores($conexion, $gimnasio_id, $fecha) {
    $query = "SELECT p.apellido, r.hora_entrada, r.hora_salida FROM registros_profesores r
              JOIN profesores p ON p.id = r.profesor_id
              WHERE r.fecha = '$fecha' AND r.gimnasio_id = $gimnasio_id";
    return $conexion->query($query);
}

// Monto total
$pagos_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');

$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$asistencias = obtenerAsistenciasClientes($conexion, $gimnasio_id, $hoy);
$ingresos_profesores = obtenerIngresosProfesores($conexion, $gimnasio_id, $hoy);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel - Multi Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: #ffd700;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
        }
        h2 {
            color: #fff;
            margin-top: 40px;
        }
        .tarjetas {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .tarjeta {
            background-color: #222;
            padding: 20px;
            border-radius: 10px;
            flex: 1 1 200px;
            text-align: center;
            box-shadow: 0 0 10px #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            background-color: #222;
        }
        th, td {
            padding: 10px;
            border: 1px solid #444;
            color: #fff;
            text-align: left;
        }
        th {
            background-color: #333;
        }
        @media (max-width: 768px) {
            .tarjetas {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <h1>Bienvenido al Panel General</h1>

    <div class="tarjetas">
        <div class="tarjeta">
            <h3>Pagos del Día</h3>
            <p>$<?= number_format($pagos_dia, 2, ',', '.') ?></p>
        </div>
        <div class="tarjeta">
            <h3>Pagos del Mes</h3>
            <p>$<?= number_format($pagos_mes, 2, ',', '.') ?></p>
        </div>
        <div class="tarjeta">
            <h3>Ventas del Día</h3>
            <p>$<?= number_format($ventas_dia, 2, ',', '.') ?></p>
        </div>
        <div class="tarjeta">
            <h3>Ventas del Mes</h3>
            <p>$<?= number_format($ventas_mes, 2, ',', '.') ?></p>
        </div>
    </div>

    <h2>Cumpleaños del Mes</h2>
    <table>
        <tr><th>Nombre</th><th>Apellido</th><th>Fecha</th></tr>
        <?php while($c = $cumples->fetch_assoc()): ?>
            <tr>
                <td><?= $c['nombre'] ?></td>
                <td><?= $c['apellido'] ?></td>
                <td><?= date("d/m", strtotime($c['fecha_nacimiento'])) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Próximos Vencimientos</h2>
    <table>
        <tr><th>Nombre</th><th>Apellido</th><th>Vencimiento</th></tr>
        <?php while($v = $vencimientos->fetch_assoc()): ?>
            <tr>
                <td><?= $v['nombre'] ?></td>
                <td><?= $v['apellido'] ?></td>
                <td><?= date("d/m/Y", strtotime($v['fecha_vencimiento'])) ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Asistencias del Día (Clientes)</h2>
    <table>
        <tr><th>Nombre</th><th>Apellido</th><th>Disciplina</th><th>Hora</th></tr>
        <?php while($a = $asistencias->fetch_assoc()): ?>
            <tr>
                <td><?= $a['nombre'] ?></td>
                <td><?= $a['apellido'] ?></td>
                <td><?= $a['disciplina'] ?></td>
                <td><?= $a['hora'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>

    <h2>Ingresos Profesores del Día</h2>
    <table>
        <tr><th>Apellido</th><th>Entrada</th><th>Salida</th></tr>
        <?php while($p = $ingresos_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $p['apellido'] ?></td>
                <td><?= $p['hora_entrada'] ?></td>
                <td><?= $p['hora_salida'] ?></td>
            </tr>
        <?php endwhile; ?>
    </table>
</body>
</html>
