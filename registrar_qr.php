<?php
include("conexion.php");
date_default_timezone_set("America/Argentina/Buenos_Aires");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $_POST["dni"];
    $fecha = date("Y-m-d");
    $hora = date("H:i:s");

    // Buscar cliente y membresía activa
    $query = "SELECT c.id AS cliente_id, m.id AS membresia_id, m.clases_disponibles, m.fecha_vencimiento 
              FROM clientes c 
              JOIN membresias m ON c.id = m.cliente_id 
              WHERE c.dni = ? AND m.fecha_vencimiento >= ? 
              ORDER BY m.fecha_vencimiento DESC LIMIT 1";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("ss", $dni, $fecha);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $cliente_id = $fila["cliente_id"];
        $membresia_id = $fila["membresia_id"];
        $clases = $fila["clases_disponibles"];
        $vencimiento = $fila["fecha_vencimiento"];

        if ($clases > 0) {
            // Descontar clase y registrar ingreso
            $nueva = $clases - 1;
            $conexion->query("UPDATE membresias SET clases_disponibles = $nueva WHERE id = $membresia_id");
            $conexion->query("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES ($cliente_id, '$fecha', '$hora')");
            echo "<script>alert('Ingreso registrado. Clases restantes: $nueva\nVence: $vencimiento'); window.location.href='index.html';</script>";
        } else {
            echo "<script>alert('No tiene clases disponibles.'); window.location.href='index.html';</script>";
        }
    } else {
        echo "<script>alert('Cliente no encontrado o plan vencido.'); window.location.href='index.html';</script>";
    }
}
?>