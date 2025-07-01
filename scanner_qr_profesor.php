<?php
session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$mensaje = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['qr'])) {
    $dni = trim($_POST['qr']);
    $fecha_hoy = date("Y-m-d");
    $hora_actual = date("H:i:s");

    // Buscar cliente
    $q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($q->num_rows == 0) {
        $mensaje = "Cliente no encontrado.";
    } else {
        $cliente_id = $q->fetch_assoc()['id'];

        // Verificar membresía
        $membresia = $conexion->query("
            SELECT id FROM membresias
            WHERE cliente_id = $cliente_id AND clases_disponibles > 0 AND fecha_vencimiento >= '$fecha_hoy'
            ORDER BY fecha_inicio DESC LIMIT 1
        ");

        if ($membresia->num_rows == 0) {
            $mensaje = "El cliente no tiene clases o membresía vigente.";
        } else {
            $mem = $membresia->fetch_assoc();
            $membresia_id = $mem['id'];

            // Registrar asistencia
            $conexion->query("
                INSERT INTO asistencias_clientes (cliente_id, fecha, hora, profesor_id)
                VALUES ($cliente_id, '$fecha_hoy', '$hora_actual', $profesor_id)
            ");

            $conexion->query("
                UPDATE membresias SET clases_disponibles = clases_disponibles - 1
                WHERE id = $membresia_id
            ");

            // Registrar asistencia profesor si no existe
            $ya = $conexion->query("
                SELECT id FROM asistencias_profesor
                WHERE profesor_id = $profesor_id AND fecha = '$fecha_hoy'
            ");
            if ($ya->num_rows == 0) {
                $conexion->query("
                    INSERT INTO asistencias_profesor (profesor_id, fecha, hora_ingreso)
                    VALUE
