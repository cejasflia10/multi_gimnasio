<?php
include 'conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["dni"])) {
    $dni = trim($_POST["dni"]);

    // Buscar cliente y membresía activa con clases disponibles
    $sql = "SELECT c.id AS cliente_id, c.nombre, c.apellido, m.id AS membresia_id, m.clases_disponibles, m.vencimiento
            FROM clientes c
            INNER JOIN membresias m ON c.id = m.cliente_id
            WHERE c.dni = ? AND m.vencimiento >= CURDATE() AND m.clases_disponibles > 0
            ORDER BY m.vencimiento DESC
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $row = $resultado->fetch_assoc();
        $cliente_id = $row['cliente_id'];
        $membresia_id = $row['membresia_id'];
        $nombre = $row['nombre'];
        $apellido = $row['apellido'];
        $clases = $row['clases_disponibles'] - 1;
        $vencimiento = $row['vencimiento'];
        $fecha = date("Y-m-d");
        $hora = date("H:i:s");

        // Descontar una clase
        $sqlUpdate = "UPDATE membresias SET clases_disponibles = clases_disponibles - 1 WHERE id = ?";
        $stmtUpdate = $conexion->prepare($sqlUpdate);
        $stmtUpdate->bind_param("i", $membresia_id);
        $stmtUpdate->execute();

        // Registrar asistencia
        $sqlAsistencia = "INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)";
        $stmtAsistencia = $conexion->prepare($sqlAsistencia);
        $stmtAsistencia->bind_param("iss", $cliente_id, $fecha, $hora);
        $stmtAsistencia->execute();

        // Mostrar mensaje
        echo "<div style='text-align:center; color:#fff; font-family:Arial;'>
                <h2>Ingreso registrado</h2>
                <p><strong>Cliente:</strong> $apellido, $nombre</p>
                <p><strong>Clases restantes:</strong> $clases</p>
                <p><strong>Vencimiento del plan:</strong> $vencimiento</p>
              </div>";
    } else {
        echo "<script>alert('Cliente no encontrado o plan vencido.'); window.history.back();</script>";
    }

    $stmt->close();
    $conexion->close();
} else {
    echo "<script>alert('No se recibió DNI.'); window.history.back();</script>";
}
?>
