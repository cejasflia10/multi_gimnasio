<?php
include 'menu.php';
include 'conexion.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['gimnasio_id'])) {
    die("Acceso denegado.");
}

$gimnasio_id = $_SESSION['gimnasio_id'];

// FUNCIONES

function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = ($modo === 'MES') ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())" : "$campo_fecha = CURDATE()";
    $columna = ($tabla === 'ventas') ? 'monto_total' : 'monto';

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $query = "SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = MONTH(CURDATE()) AND gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $query = "SELECT c.nombre, c.apellido, m.fecha_vencimiento FROM membresias m
              JOIN clientes c ON m.cliente_id = c.id
              WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
              AND m.gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $query = "SELECT a.fecha, a.hora, c.nombre, c.apellido, c.dni, c.disciplina
              FROM asistencias a
              JOIN clientes c ON a.cliente_id = c.id
              WHERE a.fecha = CURDATE() AND a.tipo = 'cliente' AND a.gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $query = "SELECT p.nombre, p.apellido, a.fecha, a.hora, a.tipo
              FROM asistencias a
              JOIN profesores p ON a.profesor_id = p.id
              WHERE a.fecha = CURDATE() AND a.tipo IN ('ingreso','egreso') AND a.gimnasio_id = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("i", $gimnasio_id);
    $stmt->execute();
    return $stmt->get_result();
}

// DATOS
$pagosDia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagosMes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventasDia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventasMes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$ingresosClientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$ingresosProfesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel General</title>
    <link rel="stylesheet" href="estilos.css"> <!-- Asegurate de tener tu CSS moderno -->
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin-left: 250px;
        }
        .contenedor {
            padding: 20px;
        }
        .tarjeta {
            background-color: #222;
            padding: 20px;
            margin: 10px 0;
            border-radius: 10px;
            box-shadow: 0 0 10px gold;
        }
        h2 {
            color: gold;
        }
        table {
            width: 100%;
            background-color: #000;
            color: white;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid gold;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #222;
        }
    </style>
</head>
<body>
    <div class="contenedor">
        <h1>📊 Panel General del Gimnasio</h1>

        <div class="tarjeta">
            <h2>💰 Pagos y Ventas</h2>
            <p>Pagos del día: $<?= number_format($pagosDia, 2) ?></p>
            <p>Pagos del mes: $<?= number_format($pagosMes, 2) ?></p>
            <p>Ventas del día: $<?= number_format($ventasDia, 2) ?></p>
            <p>Ventas del mes: $<?= number_format($ventasMes, 2) ?></p>
        </div>

        <div class="tarjeta">
            <h2>🎉 Cumpleaños del mes</h2>
            <table>
                <tr><th>Nombre</th><th>Fecha de Nacimiento</th></tr>
                <?php while ($cumple = $cumples->fetch_assoc()): ?>
                    <tr>
                        <td><?= $cumple['nombre'] . " " . $cumple['apellido'] ?></td>
                        <td><?= date('d/m', strtotime($cumple['fecha_nacimiento'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="tarjeta">
            <h2>📅 Próximos Vencimientos (10 días)</h2>
            <table>
                <tr><th>Nombre</th><th>Fecha de Vencimiento</th></tr>
                <?php while ($ven = $vencimientos->fetch_assoc()): ?>
                    <tr>
                        <td><?= $ven['nombre'] . " " . $ven['apellido'] ?></td>
                        <td><?= date('d/m/Y', strtotime($ven['fecha_vencimiento'])) ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="tarjeta">
            <h2>👥 Ingresos de Clientes Hoy</h2>
            <table>
                <tr><th>Nombre</th><th>DNI</th><th>Disciplina</th><th>Hora</th></tr>
                <?php while ($cliente = $ingresosClientes->fetch_assoc()): ?>
                    <tr>
                        <td><?= $cliente['nombre'] . " " . $cliente['apellido'] ?></td>
                        <td><?= $cliente['dni'] ?></td>
                        <td><?= $cliente['disciplina'] ?></td>
                        <td><?= $cliente['hora'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>

        <div class="tarjeta">
            <h2>🧑‍🏫 Ingresos/Egresos de Profesores Hoy</h2>
            <table>
                <tr><th>Nombre</th><th>Tipo</th><th>Hora</th></tr>
                <?php while ($prof = $ingresosProfesores->fetch_assoc()): ?>
                    <tr>
                        <td><?= $prof['nombre'] . " " . $prof['apellido'] ?></td>
                        <td><?= ucfirst($prof['tipo']) ?></td>
                        <td><?= $prof['hora'] ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</body>
</html>
