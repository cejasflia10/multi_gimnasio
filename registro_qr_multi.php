<?php
include 'conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['codigo'])) {
    $codigo = trim($_POST['codigo']);
} elseif (isset($_GET['codigo'])) {
    $codigo = trim($_GET['codigo']);
} else {
    die("Código QR no recibido.");
}

$fecha = date('Y-m-d');
$hora = date('H:i:s');

if (str_starts_with($codigo, 'C-')) {
    $dni = substr($codigo, 2);
    $cliente_q = $conexion->query("SELECT id FROM clientes WHERE dni = '$dni'");
    if ($cliente_q->num_rows > 0) {
        $cliente = $cliente_q->fetch_assoc();
        $cliente_id = $cliente['id'];
        $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
        echo "Asistencia de cliente registrada correctamente.";
    } else {
        echo "Cliente no encontrado.";
    }
} elseif (str_starts_with($codigo, 'P-')) {
    $dni = substr($codigo, 2);
    $profesor_q = $conexion->query("SELECT id FROM profesores WHERE dni = '$dni'");
    if ($profesor_q->num_rows > 0) {
        $profesor = $profesor_q->fetch_assoc();
        $profesor_id = $profesor['id'];
        $conexion->query("INSERT INTO asistencias_profesores (profesor_id, fecha, hora_ingreso) VALUES ($profesor_id, '$fecha', '$hora')");
        echo "Ingreso de profesor registrado correctamente.";
    } else {
        echo "Profesor no encontrado.";
    }
} else {
    echo "Código no reconocido.";
}
?>
