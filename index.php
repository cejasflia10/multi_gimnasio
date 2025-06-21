<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    die("Error: No hay gimnasio activo.");
}
$gimnasio_id = $_SESSION['gimnasio_id'];
include 'conexion.php';
$hoy = date('Y-m-d');

function obtenerAsistenciasClientes($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT c.apellido, c.nombre, a.fecha, a.hora 
            FROM asistencias_clientes a 
            INNER JOIN clientes c ON a.cliente_id = c.id 
            WHERE c.id_gimnasio = $gimnasio_id AND a.fecha = '$fecha' 
            ORDER BY a.fecha_hora DESC";
    return $conexion->query($sql);
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id, $fecha) {
    $sql = "SELECT p.apellido, p.nombre, a.ingreso, a.salida 
            FROM asistencias_profesores a 
            INNER JOIN profesores p ON a.profesor_id = p.id 
            WHERE p.gimnasio_id = $gimnasio_id AND a.fecha = '$fecha' 
            ORDER BY a.ingreso DESC";
    return $conexion->query($sql);
}

function obtenerCumpleaniosProximos($conexion, $gimnasio_id) {
    $hoy = date('m-d');
    $sql = "SELECT apellido, nombre, DATE_FORMAT(fecha_nacimiento, '%d-%m') as cumple 
            FROM clientes 
            WHERE id_gimnasio = $gimnasio_id 
            AND DATE_FORMAT(fecha_nacimiento, '%m-%d') >= '$hoy'
            ORDER BY fecha_nacimiento ASC LIMIT 10";
    return $conexion->query($sql);
}

function obtenerVencimientosProximos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $proximo = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT c.apellido, c.nombre, m.fecha_vencimiento 
            FROM membresias m 
            INNER JOIN clientes c ON m.cliente_id = c.id 
            WHERE c.id_gimnasio = $gimnasio_id AND m.fecha_vencimiento BETWEEN '$hoy' AND '$proximo' 
            ORDER BY m.fecha_vencimiento ASC";
    return $conexion->query($sql);
}

$asistenciasClientes = obtenerAsistenciasClientes($conexion, $gimnasio_id, $hoy);
$asistenciasProfesores = obtenerAsistenciasProfesores($conexion, $gimnasio_id, $hoy);
$cumples = obtenerCumpleaniosProximos($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientosProximos($conexion, $gimnasio_id);
?>

<?php include 'menu.php'; ?>

<div class="contenido-panel">
    <h2 class="titulo">📅 Asistencias de Clientes <span>(<?php echo $hoy; ?>)</span></h2>
    <?php if ($asistenciasClientes->num_rows > 0): ?>
    <table class="tabla">
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th><th>Hora</th></tr>
        <?php while($row = $asistenciasClientes->fetch_assoc()): ?>
        <tr><td><?php echo $row['apellido']; ?></td><td><?php echo $row['nombre']; ?></td><td><?php echo $row['fecha']; ?></td><td><?php echo $row['hora']; ?></td></tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?><p class="sin-datos">No se registraron asistencias de clientes hoy.</p><?php endif; ?>

    <h2 class="titulo">👨‍🏫 Asistencias de Profesores <span>(<?php echo $hoy; ?>)</span></h2>
    <?php if ($asistenciasProfesores->num_rows > 0): ?>
    <table class="tabla">
        <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Salida</th></tr>
        <?php while($row = $asistenciasProfesores->fetch_assoc()): ?>
        <tr><td><?php echo $row['apellido']; ?></td><td><?php echo $row['nombre']; ?></td><td><?php echo $row['ingreso']; ?></td><td><?php echo $row['salida']; ?></td></tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?><p class="sin-datos">No se registraron asistencias de profesores hoy.</p><?php endif; ?>

    <h2 class="titulo">🎂 Próximos Cumpleaños</h2>
    <?php if ($cumples->num_rows > 0): ?>
    <table class="tabla">
        <tr><th>Apellido</th><th>Nombre</th><th>Fecha</th></tr>
        <?php while($row = $cumples->fetch_assoc()): ?>
        <tr><td><?php echo $row['apellido']; ?></td><td><?php echo $row['nombre']; ?></td><td><?php echo $row['cumple']; ?></td></tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?><p class="sin-datos">No hay cumpleaños próximos.</p><?php endif; ?>

    <h2 class="titulo">⚠️ Vencimientos Próximos</h2>
    <?php if ($vencimientos->num_rows > 0): ?>
    <table class="tabla">
        <tr><th>Apellido</th><th>Nombre</th><th>Vencimiento</th></tr>
        <?php while($row = $vencimientos->fetch_assoc()): ?>
        <tr><td><?php echo $row['apellido']; ?></td><td><?php echo $row['nombre']; ?></td><td><?php echo $row['fecha_vencimiento']; ?></td></tr>
        <?php endwhile; ?>
    </table>
    <?php else: ?><p class="sin-datos">No hay vencimientos en los próximos días.</p><?php endif; ?>
</div>

<style>
.contenido-panel {
    margin-left: 220px;
    padding: 20px;
    color: #f1f1f1;
    background-color: #111;
    font-family: Arial, sans-serif;
}
.titulo {
    color: gold;
    margin-top: 40px;
    font-size: 18px;
}
.tabla {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    margin-bottom: 20px;
    font-size: 14px;
}
.tabla th, .tabla td {
    border: 1px solid gold;
    padding: 6px;
    text-align: left;
}
.sin-datos {
    color: #ccc;
    font-size: 14px;
    margin-left: 10px;
}
@media screen and (max-width: 768px) {
    .contenido-panel {
        margin-left: 0;
        padding: 10px;
    }
    .tabla {
        font-size: 12px;
    }
}
</style>
