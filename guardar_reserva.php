
<?php
session_start();
include 'conexion.php';

$cliente_id = $_SESSION['cliente_id'] ?? 0;
$turno_id = $_POST['turno_id'] ?? 0;
$hoy = date('Y-m-d');

$turno = $conexion->query("SELECT * FROM turnos WHERE id = $turno_id")->fetch_assoc();
if (!$turno) {
    die("Turno no válido.");
}

$membresia = $conexion->query("
    SELECT * FROM membresias 
    WHERE cliente_id = $cliente_id 
      AND fecha_vencimiento >= '$hoy'
      AND clases_disponibles > 0
    ORDER BY fecha_vencimiento DESC LIMIT 1
")->fetch_assoc();

if (!$membresia) {
    die("No tenés membresía activa o no tenés clases disponibles.");
}

$reserva_existente = $conexion->query("
    SELECT * FROM reservas 
    WHERE cliente_id = $cliente_id 
      AND turno_id = $turno_id 
      AND fecha = '$hoy'
")->num_rows;

if ($reserva_existente > 0) {
    die("Ya reservaste este turno hoy.");
}

$cupo_max = $turno['cupo_maximo'];
$cupo_actual = $conexion->query("
    SELECT COUNT(*) AS total FROM reservas 
    WHERE turno_id = $turno_id 
      AND fecha = '$hoy'
")->fetch_assoc()['total'];

if ($cupo_actual >= $cupo_max) {
    die("No hay más cupos disponibles para este turno.");
}

$conexion->query("
    INSERT INTO reservas (cliente_id, turno_id, fecha)
    VALUES ($cliente_id, $turno_id, '$hoy')
");

$membresia_id = $membresia['id'];
$conexion->query("
    UPDATE membresias 
    SET clases_disponibles = clases_disponibles - 1 
    WHERE id = $membresia_id
");

echo "<h2 style='color: gold; background: black; padding: 20px; text-align: center;'>¡Reserva realizada con éxito!</h2>";
echo "<meta http-equiv='refresh' content='2;url=reservar_turno.php'>";
