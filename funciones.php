<?php
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES' ? "MONTH($campo_fecha) = MONTH(CURDATE())" : "$campo_fecha = CURDATE()";
    $columna = ($tabla === 'ventas') ? 'monto_total' : (($tabla === 'membresias') ? 'total' : 'monto');
    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND id_gimnasio = $gimnasio_id";
    $res = $conexion->query($query);
    return $res->fetch_assoc()['total'] ?? 0;
}

function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, a.hora
        FROM asistencias a
        INNER JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN membresias m ON m.cliente_id = c.id
        WHERE DATE(a.fecha) = CURDATE() AND c.gimnasio_id = $gimnasio_id
    ");
}

function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT p.apellido, ap.fecha, ap.hora_entrada, ap.hora_salida
        FROM asistencias_profesores ap
        INNER JOIN profesores p ON ap.profesor_id = p.id
        WHERE DATE(ap.fecha) = CURDATE() AND p.gimnasio_id = $gimnasio_id
    ");
}

function obtenerCumpleanios($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT nombre, apellido, fecha_nacimiento 
        FROM clientes 
        WHERE gimnasio_id = $gimnasio_id AND MONTH(fecha_nacimiento) = MONTH(CURDATE()) 
        ORDER BY DAY(fecha_nacimiento)
    ");
}

function obtenerVencimientos($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT c.nombre, c.apellido, m.fecha_vencimiento 
        FROM membresias m 
        INNER JOIN clientes c ON m.cliente_id = c.id 
        WHERE m.fecha_vencimiento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY) 
        AND c.gimnasio_id = $gimnasio_id 
        ORDER BY m.fecha_vencimiento ASC
    ");
}

function obtenerDisciplinas($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT disciplina, COUNT(*) as cantidad 
        FROM clientes 
        WHERE gimnasio_id = $gimnasio_id 
        GROUP BY disciplina
    ");
}

function obtenerPagosPorMetodo($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT metodo_pago, COUNT(*) as cantidad 
        FROM pagos 
        WHERE id_gimnasio = $gimnasio_id 
        AND MONTH(fecha) = MONTH(CURDATE()) 
        GROUP BY metodo_pago
    ");
}
?>
