<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = $_POST["dni"] ?? '';

    if (empty($dni)) {
        echo "<p style='color:red'>No se recibió el DNI</p>";
        exit;
    }

    // Buscar cliente por DNI
    $query = "SELECT id FROM clientes WHERE dni = ?";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        echo "<p style='color:red'>❌ Cliente no encontrado</p>";
        exit;
    }

    $stmt->bind_result($cliente_id);
    $stmt->fetch();
    $stmt->close();

    // Buscar membresía activa
    $hoy = date('Y-m-d');
    $queryM = "SELECT id, clases_disponibles, fecha_fin FROM membresias 
               WHERE cliente_id = ? AND fecha_inicio <= ? AND fecha_fin >= ? 
               ORDER BY fecha_fin DESC LIMIT 1";
    $stmtM = $conexion->prepare($queryM);
    $stmtM->bind_param("iss", $cliente_id, $hoy, $hoy);
    $stmtM->execute();
    $resultado = $stmtM->get_result();

    if ($resultado->num_rows == 0) {
        echo "<p style='color:red'>❌ No se encontró membresía activa.</p>";
        exit;
    }

    $membresia = $resultado->fetch_assoc();

    if ($membresia['clases_disponibles'] <= 0) {
        echo "<p style='color:red'>❌ No tiene clases disponibles.</p>";
        exit;
    }

    // Registrar asistencia
    $queryA = "INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)";
    $stmtA = $conexion->prepare($queryA);
    $fecha = date('Y-m-d');
    $hora = date('H:i:s');
    $stmtA->bind_param("iss", $cliente_id, $fecha, $hora);
    $stmtA->execute();
    $stmtA->close();

    // Descontar una clase
    $queryU = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = ?";
    $stmtU = $conexion->prepare($queryU);
    $stmtU->bind_param("i", $membresia['id']);
    $stmtU->execute();
    $stmtU->close();

    echo "<p style='color:lime'>✅ Asistencia registrada correctamente</p>";
}
?>
