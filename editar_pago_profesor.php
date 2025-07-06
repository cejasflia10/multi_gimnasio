<?php
session_start();
include 'conexion.php';

$mes_actual = date('m');
$anio_actual = date('Y');

// Obtener lista de profesores
$profesores = $conexion->query("SELECT id, apellido, nombre FROM profesores");

function calcularPago($conexion, $profesor_id, $mes, $anio) {
    $pagos = [];

    $asistencias = $conexion->query("
        SELECT fecha, hora_ingreso
        FROM asistencias_profesor
        WHERE profesor_id = $profesor_id
          AND MONTH(fecha) = $mes
          AND YEAR(fecha) = $anio
    ");

    while ($asis = $asistencias->fetch_assoc()) {
        $fecha = $asis['fecha'];

        // Cantidad de alumnos ese día con este profesor
        $alumnos_q = $conexion->query("
            SELECT COUNT(*) AS cantidad
            FROM asistencias_clientes
            WHERE profesor_id = $profesor_id AND fecha = '$fecha'
        ");
        $cantidad = $alumnos_q->fetch_assoc()['cantidad'];

        // Cálculo de pago
        if ($cantidad >= 5) {
            $monto = 2000;
        } elseif ($cantidad >= 2) {
            $monto = 1500;
        } elseif ($cantidad == 1) {
            $monto = 1000;
        } else {
            $monto = 0;
        }

        $pagos[] = [
            'fecha' => $fecha,
            'alumnos' => $cantidad,
            'monto' => $monto
        ];
    }

    return $pagos;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pagos a Profesores</titl
