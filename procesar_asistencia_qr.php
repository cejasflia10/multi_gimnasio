<?php
include 'conexion.php';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["codigo_qr"])) {
    $codigo = trim($_POST["codigo_qr"]);
    $fecha_hoy = date("Y-m-d");
    $hora = date("H:i:s");

    // Buscar cliente por DNI, RFID o ID en el código QR
    $stmt = $conexion->prepare("SELECT id, dni, clases_restantes, fecha_vencimiento FROM clientes WHERE dni = ? OR rfid_uid = ? OR id = ?");
    $stmt->bind_param("sss", $codigo, $codigo, $codigo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $cliente = $resultado->fetch_assoc();
        $cliente_id = $cliente["id"];
        $clases_restantes = $cliente["clases_restantes"];
        $vencimiento = $cliente["fecha_vencimiento"];

        // Verificar vencimiento
        if ($vencimiento >= $fecha_hoy && $clases_restantes > 0) {
            // Registrar asistencia
            $insert = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $cliente_id, $fecha_hoy, $hora);
            $insert->execute();

            // Descontar una clase
            $nueva_cantidad = $clases_restantes - 1;
            $update = $conexion->prepare("UPDATE clientes SET clases_restantes = ? WHERE id = ?");
            $update->bind_param("ii", $nueva_cantidad, $cliente_id);
            $update->execute();

            echo "<script>alert('Asistencia registrada correctamente.'); window.location.href='registrar_asistencia_qr.php';</script>";
        } else {
            echo "<script>alert('Plan vencido o sin clases disponibles.'); window.location.href='registrar_asistencia_qr.php';</script>";
        }
    } else {
        echo "<script>alert('Cliente no encontrado.'); window.location.href='registrar_asistencia_qr.php';</script>";
    }
    $stmt->close();
} else {
    echo "Acceso inválido.";
}
?>
