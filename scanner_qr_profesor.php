<?php
include 'conexion.php';

if (!isset($_GET['codigo'])) {
    die("Código inválido.");
}

$codigo = $_GET['codigo'];

// Verificar que comience con 'P' y extraer DNI
if (strpos($codigo, 'P') !== 0) {
    die("Código inválido.");
}

$dni = substr($codigo, 1);
$dni = intval($dni);

// Buscar profesor
$query = $conexion->query("SELECT * FROM profesores WHERE dni = $dni");
if ($query->num_rows === 0) {
    die("Profesor no encontrado (DNI: $dni).");
}

$profesor = $query->fetch_assoc();
$profesor_id = $profesor['id'];
$gimnasio_id = $profesor['gimnasio_id'];

// Buscar asistencia del día
$hoy = date('Y-m-d');
$asistencia = $conexion->query("
    SELECT * FROM asistencias_profesor 
    WHERE profesor_id = $profesor_id 
    AND fecha = '$hoy'
")->fetch_assoc();

if ($asistencia) {
    // Registrar salida
    $hora = date('H:i:s');
    $conexion->query("
        UPDATE asistencias_profesor 
        SET hora_salida = '$hora' 
        WHERE id = {$asistencia['id']}
    ");
    echo "<script>alert('✅ Salida registrada correctamente.'); location.href='ver_asistencias_dia.php';</script>";
} else {
    // Registrar ingreso
    $hora = date('H:i:s');
    $conexion->query("
        INSERT INTO asistencias_profesor (profesor_id, hora_entrada, gimnasio_id, fecha) 
        VALUES ($profesor_id, '$hora', $gimnasio_id, '$hoy')
    ");
    echo "<script>alert('✅ Ingreso registrado correctamente.'); location.href='ver_asistencias_dia.php';</script>";
}
?>
