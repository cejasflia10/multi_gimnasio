<?php
include 'permisos.php';

if (!tiene_permiso('profesores')) {
    echo "<h2 style='color:red;'>⛔ Acceso denegado</h2>";
    exit;
}
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES'
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    $columna = match ($tabla) {
        'ventas' => 'monto_total',
        'membresias' => 'total',
        default => 'monto'
    };

    $sql = "SELECT SUM($columna) AS total FROM $tabla 
            WHERE $condicion AND gimnasio_id = $gimnasio_id";
    $resultado = $conexion->query($sql);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes_actual = date('m');
    $sql = "SELECT nombre, apellido, fecha_nacimiento FROM clientes 
            WHERE MONTH(fecha_nacimiento) = $mes_actual AND gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    $hoy = date('Y-m-d');
    $diez_dias_despues = date('Y-m-d', strtotime('+10 days'));
    $sql = "SELECT clientes.nombre, clientes.apellido, membresias.fecha_vencimiento 
            FROM membresias 
            JOIN clientes ON clientes.id = membresias.cliente_id 
            WHERE membresias.fecha_vencimiento BETWEEN '$hoy' AND '$diez_dias_despues'
            AND membresias.gimnasio_id = $gimnasio_id";
    return $conexion->query($sql);
}
?>
