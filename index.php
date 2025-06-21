
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include("conexion.php");

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$nombre_gimnasio = $_SESSION['nombre_gimnasio'] ?? 'ACADEMY';

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    $fecha_hoy = date("Y-m-d");
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora
            FROM asistencias a
            INNER JOIN clientes c ON a.id_cliente = c.id
            WHERE a.fecha = CURDATE() AND c.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    $sql = "SELECT p.apellido, p.nombre, a.fecha, a.hora_ingreso, a.hora_salida
            FROM asistencias_profesores a
            INNER JOIN profesores p ON a.profesor_id = p.id
            WHERE a.fecha = CURDATE() AND p.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date("m");
    $sql = "SELECT apellido, nombre, fecha_nacimiento
            FROM clientes
            WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date("Y-m-d");
    $limite = date("Y-m-d", strtotime("+10 days"));
    $sql = "SELECT c.apellido, c.nombre, m.fecha_vencimiento
            FROM membresias m
            INNER JOIN clientes c ON m.cliente_id = c.id
            WHERE m.fecha_vencimiento BETWEEN '$hoy' AND '$limite'
            AND c.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

$asistencias_clientes = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$asistencias_profesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
$cumpleanios = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Control</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }
        h2, h3 {
            text-align: center;
            margin-top: 20px;
        }
        .contenedor {
            padding: 15px;
            width: 100%;
            box-sizing: border-box;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        th, td {
            border: 1px solid gold;
            padding: 6px;
            text-align: center;
            font-size: 14px;
        }
        th {
            background-color: #222;
        }
        .titulo-seccion {
            font-size: 18px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include("menu.php"); ?>
    <div class="contenedor">
        <h2>🏋️ Fight Academy - <?php echo strtoupper($nombre_gimnasio); ?></h2>
        <h3>📈 Panel de Control</h3>

        <div class="titulo-seccion">👥 Asistencias de Clientes - <?php echo date("Y-m-d"); ?></div>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
            <?php while($row = $asistencias_clientes->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['hora'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <div class="titulo-seccion">🧑‍🏫 Asistencias de Profesores - <?php echo date("Y-m-d"); ?></div>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
            <?php while($row = $asistencias_profesores->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['hora_ingreso'] ?></td>
                <td><?= $row['hora_salida'] ?? '-' ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <div class="titulo-seccion">🎂 Próximos Cumpleaños</div>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th></tr>
            <?php while($row = $cumpleanios->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha_nacimiento'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>

        <div class="titulo-seccion">⏳ Vencimientos Próximos</div>
        <table>
            <tr><th>Apellido</th><th>Nombre</th><th>Vencimiento</th></tr>
            <?php while($row = $vencimientos->fetch_assoc()): ?>
            <tr>
                <td><?= $row['apellido'] ?></td>
                <td><?= $row['nombre'] ?></td>
                <td><?= $row['fecha_vencimiento'] ?></td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
