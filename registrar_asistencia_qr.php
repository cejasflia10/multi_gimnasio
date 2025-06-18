<?php
include 'conexion.php';

$dni = $_POST['dni'] ?? '';
$gimnasio_id = $_POST['gimnasio_id'] ?? 0;

if (!$dni || !$gimnasio_id) {
    echo "Datos inválidos.";
    exit;
}

$stmt = $conexion->prepare("SELECT id, apellido, nombre, clases_restantes, fecha_vencimiento FROM clientes WHERE dni = ? AND gimnasio_id = ?");
$stmt->bind_param("si", $dni, $gimnasio_id);
$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    $cliente = $resultado->fetch_assoc();

    $hoy = date("Y-m-d");
    $vencimiento = $cliente['fecha_vencimiento'];

    if ($vencimiento < $hoy) {
        echo "Cuota vencida: " . $vencimiento;
        exit;
    }

    if ($cliente['clases_restantes'] <= 0) {
        echo "Sin clases disponibles.";
        exit;
    }

    $nuevas_clases = $cliente['clases_restantes'] - 1;
    $conexion->query("UPDATE clientes SET clases_restantes = $nuevas_clases WHERE id = " . $cliente['id']);

    $stmt = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, gimnasio_id) VALUES (?, NOW(), ?)");
    $stmt->bind_param("ii", $cliente['id'], $gimnasio_id);
    $stmt->execute();

    echo "Asistencia registrada<br>Nombre: " . $cliente['apellido'] . ", " . $cliente['nombre'] . "<br>Clases restantes: " . $nuevas_clases;
} else {
    echo "Cliente no encontrado.";
}
?>
