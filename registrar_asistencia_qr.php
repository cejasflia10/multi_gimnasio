<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$dni = trim($_POST['dni'] ?? '');
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$fecha = date('Y-m-d');
$hora = date('H:i:s');

// Validación
if ($dni === '' || !is_numeric($dni)) {
    echo "<span style='color:red;'>❌ DNI inválido</span>";
    exit;
}

// 1. Buscar Cliente
$cliente_q = $conexion->query("SELECT id, apellido, nombre FROM clientes WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
if ($cliente_q && $cliente_q->num_rows > 0) {
    $cliente = $cliente_q->fetch_assoc();
    $cliente_id = $cliente['id'];

    // Verificar membresía activa
    $membresia_q = $conexion->query("SELECT * FROM membresias 
        WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id 
        AND fecha_vencimiento >= '$fecha' AND clases_disponibles > 0 
        ORDER BY fecha_inicio DESC LIMIT 1");

    if ($membresia_q && $membresia_q->num_rows > 0) {
        $membresia = $membresia_q->fetch_assoc();
        $nuevas_clases = $membresia['clases_disponibles'] - 1;
        $membresia_id = $membresia['id'];

        // Descontar clase
        $conexion->query("UPDATE membresias SET clases_disponibles = $nuevas_clases WHERE id = $membresia_id");

        // Registrar asistencia
        $conexion->query("INSERT INTO asistencias_clientes (cliente_id, gimnasio_id, fecha, hora_ingreso) 
            VALUES ($cliente_id, $gimnasio_id, '$fecha', '$hora')");

        echo "<span style='color:lightgreen;'>✅ Ingreso registrado: {$cliente['apellido']} {$cliente['nombre']}<br>Clases restantes: $nuevas_clases</span>";
    } else {
        echo "<span style='color:orange;'>⚠️ Membresía vencida o sin clases disponibles</span>";
    }

    exit;
}

// 2. Buscar Profesor
$prof_q = $conexion->query("SELECT id, apellido, nombre FROM profesores WHERE dni = '$dni' AND gimnasio_id = $gimnasio_id");
if ($prof_q && $prof_q->num_rows > 0) {
    $prof = $prof_q->fetch_assoc();
    $prof_id = $prof['id'];

    // Verificar si ya tiene ingreso hoy sin egreso
    $check = $conexion->query("SELECT id FROM asistencias_profesores 
        WHERE profesor_id = $prof_id AND fecha = '$fecha' AND hora_egreso IS NULL 
        LIMIT 1");

    if ($check && $check->num_rows > 0) {
        // Registrar salida
        $asistencia = $check->fetch_assoc();
        $asistencia_id = $asistencia['id'];

        $conexion->query("UPDATE asistencias_profesores 
            SET hora_egreso = '$hora' WHERE id = $asistencia_id");

        echo "<span style='color:lightblue;'>📤 Salida registrada: {$prof['apellido']} {$prof['nombre']}</span>";
    } else {
        // Registrar ingreso
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, gimnasio_id, fecha, hora_ingreso) 
            VALUES ($prof_id, $gimnasio_id, '$fecha', '$hora')");

        echo "<span style='color:lightgreen;'>📥 Ingreso registrado: {$prof['apellido']} {$prof['nombre']}</span>";
    }

    exit;
}

// 3. No encontrado
echo "<span style='color:red;'>❌ DNI no registrado como cliente ni profesor</span>";
