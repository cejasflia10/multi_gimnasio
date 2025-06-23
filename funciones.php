<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';

// Obtener montos (pagos, ventas, membresías)
function obtenerMonto($conexion, $tabla, $campo_fecha, $gimnasio_id, $modo = 'DIA') {
    $condicion = $modo === 'MES' 
        ? "MONTH($campo_fecha) = MONTH(CURDATE()) AND YEAR($campo_fecha) = YEAR(CURDATE())"
        : "$campo_fecha = CURDATE()";

    $columna = match($tabla) {
        'ventas' => 'monto_total',
        'pagos' => 'monto',
        'membresias' => 'total',
        default => 'monto'
    };

    $query = "SELECT SUM($columna) AS total FROM $tabla WHERE $condicion AND id_gimnasio = $gimnasio_id";
    $resultado = $conexion->query($query);
    $fila = $resultado->fetch_assoc();
    return $fila['total'] ?? 0;
}

// Asistencias de clientes del día
function obtenerAsistenciasClientes($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT c.nombre, c.apellido, c.dni, c.disciplina, m.fecha_vencimiento, a.hora
        FROM asistencias a
        INNER JOIN clientes c ON a.cliente_id = c.id
        LEFT JOIN membresias m ON m.cliente_id = c.id
        WHERE a.fecha = CURDATE() AND a.id_gimnasio = $gimnasio_id
        ORDER BY a.hora DESC
    ");
}

// Asistencias de profesores del día
function obtenerAsistenciasProfesores($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT p.apellido, r.fecha, r.hora_entrada, r.hora_salida
        FROM registro_asistencias_profesores r
        INNER JOIN profesores p ON r.profesor_id = p.id
        WHERE r.fecha = CURDATE() AND r.gimnasio_id = $gimnasio_id
        ORDER BY r.hora_entrada DESC
    ");
}

// Gráfico de disciplinas
function obtenerDisciplinas($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT disciplina, COUNT(*) as cantidad
        FROM clientes
        WHERE gimnasio_id = $gimnasio_id
        GROUP BY disciplina
    ");
}

// Gráfico de métodos de pago
function obtenerPagosPorMetodo($conexion, $gimnasio_id) {
    return $conexion->query("
        SELECT metodo_pago, COUNT(*) AS cantidad
        FROM pagos
        WHERE MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
        AND id_gimnasio = $gimnasio_id
        GROUP BY metodo_pago
    ");
}

// Próximos cumpleaños del mes
function obtenerCumpleanios($conexion, $gimnasio_id) {
    $mes = date('m');
    return $conexion->query("
        SELECT nombre, apellido, fecha_nacimiento
        FROM clientes
        WHERE MONTH(fecha_nacimiento) = $mes AND gimnasio_id = $gimnasio_id
        ORDER BY DAY(fecha_nacimiento)
    ");
}

// Próximos vencimientos de membresías
function obtenerVencimientos($conexion, $gimnasio_id) {
    $fecha_limite = date('Y-m-d', strtotime('+10 days'));
    return $conexion->query("
        SELECT c.nombre, c.apellido, m.fecha_vencimiento
        FROM membresias m
        INNER JOIN clientes c ON m.cliente_id = c.id
        WHERE m.fecha_vencimiento BETWEEN CURDATE() AND '$fecha_limite'
        AND m.id_gimnasio = $gimnasio_id
        ORDER BY m.fecha_vencimiento
    ");
}
?>
