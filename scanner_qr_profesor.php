<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$mensaje = "";
$datos_cliente = null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $fecha_hoy = date("Y-m-d");
    $hora_actual = date("H:i:s");

    $q = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni'");
    if ($q->num_rows == 0) {
        $mensaje = "Cliente no encontrado.";
    } else {
        $cliente = $q->fetch_assoc();
        $cliente_id = $cliente['id'];

        $membresia = $conexion->query("
            SELECT id, clases_disponibles, fecha_vencimiento
            FROM membresias
            WHERE cliente_id = $cliente_id
              AND fecha_vencimiento >= '$fecha_hoy'
              AND clases_disponibles > 0
            ORDER BY fecha_inicio DESC
            LIMIT 1
        ");

        if ($membresia->num_rows == 0) {
            $mensaje = "No tiene clases disponibles o la membresía está vencida.";
        } else {
            $mem = $membresia->fetch_assoc();
            $membresia_id = $mem['id'];
            $clases_disponibles = $mem['clases_disponibles'] - 1;
            $vencimiento = $mem['fecha_vencimiento'];

            $conexion->query("
                INSERT INTO asistencias_clientes (cliente_id, fecha, hora, profesor_id)
                VALUES ($cliente_id, '$fecha_hoy', '$hora_actual', $profesor_id)
            ");

            $conexion->query("
                UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                WHERE id = $membresia_id
            ");

            $ya = $conexion->query("
                SELECT id FROM asistencias_profesor
                WHERE profesor_id = $profesor_id AND fecha = '$fecha_hoy'
            ");
            if ($ya->num_rows == 0) {
                $conexion->query("
                    INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso)
                    VALUES ($profesor_id, '$fecha_hoy', '$hora_actual')
                ");
            }

            $mensaje = "Asistencia registrada correctamente.";
            $datos_cliente = [
                'nombre' => $cliente['nombre'],
                'apellido' => $cliente['apellido'],
                'clases_restantes' => $clases_disponibles,
                'vencimiento' => $vencimiento
            ];
        }
    }
}
?>
