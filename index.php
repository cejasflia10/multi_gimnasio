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
    $condicion = $modo === 'MES' ? "MONTH($campo_fecha) = MONTH(CURDATE())" : "$campo_fecha = CURDATE()";
    switch ($tabla) {
        case 'ventas': $columna = 'monto_total'; break;
        case 'pagos': $columna = 'monto'; break;
        case 'membresias': $columna = 'total'; break;
        default: $columna = 'monto';
    }
    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    return $conexion->query("SELECT nombre, apellido, fecha_nacimiento FROM clientes WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id ORDER BY DAY(fecha_nacimiento)");
}
function obtenerVencimientos($conexion, $gimnasio_id) {
    $fecha_limite = date('Y-m-d', strtotime('+10 days'));
    return $conexion->query("SELECT c.nombre, c.apellido, m.fecha_vencimiento 
        FROM membresias m 
        INNER JOIN clientes c ON m.cliente_id = c.id 
        WHERE m.fecha_vencimiento BETWEEN CURDATE() AND '$fecha_limite' 
        AND m.id_gimnasio = $gimnasio_id 
        ORDER BY m.fecha_vencimiento");
}
function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    // ✅ Corrección importante: se asegura que `a.fecha` sea exactamente hoy
    $query = "SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, a.hora 
        FROM asistencias a 
        INNER JOIN clientes c ON a.cliente_id = c.id 
        LEFT JOIN membresias m ON m.cliente_id = c.id 
        WHERE a.fecha = CURDATE() AND c.gimnasio_id = $gimnasio_id 
        ORDER BY a.hora DESC";
    return $conexion->query($query);
}
function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    return $conexion->query("SELECT p.apellido, r.fecha, r.hora_entrada, r.hora_salida 
        FROM registro_asistencias_profesores r 
        INNER JOIN profesores p ON r.profesor_id = p.id 
        WHERE r.fecha = CURDATE() AND p.gimnasio_id = $gimnasio_id 
        ORDER BY r.hora_entrada DESC");
}

// DATOS
$pagos_dia = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'DIA');
$pagos_mes = obtenerMonto($conexion, 'pagos', 'fecha', $gimnasio_id, 'MES');
$ventas_dia = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'DIA');
$ventas_mes = obtenerMonto($conexion, 'ventas', 'fecha', $gimnasio_id, 'MES');
$membresias_dia = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'DIA');
$membresias_mes = obtenerMonto($conexion, 'membresias', 'fecha_inicio', $gimnasio_id, 'MES');
$cumples = obtenerCumpleanios($conexion, $gimnasio_id);
$vencimientos = obtenerVencimientos($conexion, $gimnasio_id);
$clientes_dia = obtenerAsistenciasClientes($conexion, $gimnasio_id);
$profesores_dia = obtenerAsistenciasProfesores($conexion, $gimnasio_id);
?>

<!-- HTML igual al tuyo, conservado -->
