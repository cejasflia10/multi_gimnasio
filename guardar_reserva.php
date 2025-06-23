<?php
include 'conexion.php';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $turno_id = $_POST['turno_id'];
    $cliente_id = $_POST['cliente_id'];
    $fecha = date('Y-m-d');

    // Evitar reservas duplicadas del mismo día
    $verificar = $conexion->query("SELECT * FROM reservas WHERE id_turno = $turno_id AND fecha = '$fecha' AND id_cliente = $cliente_id");
    if ($verificar->num_rows > 0) {
        echo "<script>alert('Ya reservaste este turno.'); window.history.back();</script>";
        exit;
    }

    $stmt = $conexion->prepare("INSERT INTO reservas (id_turno, id_cliente, fecha) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $turno_id, $cliente_id, $fecha);
    if ($stmt->execute()) {
        echo "<script>alert('Reserva realizada con éxito.'); window.location.href='reservar_turno.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }
}
