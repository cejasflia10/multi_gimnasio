<?php
include 'conexion.php';

$id_dia = $_POST['id_dia'];
$id_horario = $_POST['id_horario'];
$id_profesor = $_POST['id_profesor'];
$cupo = $_POST['cupo_maximo'] ?? 20;

$stmt = $conexion->prepare("INSERT INTO turnos (id_dia, id_horario, id_profesor, cupo_maximo) VALUES (?, ?, ?, ?)");
$stmt->bind_param("iiii", $id_dia, $id_horario, $id_profesor, $cupo);

if ($stmt->execute()) {
    echo "<script>alert('Turno guardado correctamente'); location.href='agregar_turno.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}
