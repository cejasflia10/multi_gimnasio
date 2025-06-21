<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dni = trim($_POST['dni']);
    $fecha_actual = date('Y-m-d');
    $hora_actual = date('H:i:s');

    // Buscar al cliente por DNI
    $sql_cliente = "SELECT id, nombre, apellido FROM clientes WHERE dni = ?";
    $stmt_cliente = $conexion->prepare($sql_cliente);
    $stmt_cliente->bind_param("s", $dni);
    $stmt_cliente->execute();
    $resultado_cliente = $stmt_cliente->get_result();

    if ($resultado_cliente->num_rows > 0) {
        $cliente = $resultado_cliente->fetch_assoc();
        $cliente_id = $cliente['id'];

        // Buscar membresía activa
        $sql_membresia = "SELECT id, fecha_vencimiento, clases_restantes FROM membresias WHERE cliente_id = ? ORDER BY id DESC LIMIT 1";
        $stmt_membresia = $conexion->prepare($sql_membresia);
        $stmt_membresia->bind_param("i", $cliente_id);
        $stmt_membresia->execute();
        $resultado_membresia = $stmt_membresia->get_result();

        if ($resultado_membresia->num_rows > 0) {
            $membresia = $resultado_membresia->fetch_assoc();
            $clases_restantes = $membresia['clases_restantes'];
            $fecha_vencimiento = $membresia['fecha_vencimiento'];

            if ($clases_restantes > 0 && $fecha_actual <= $fecha_vencimiento) {
                // Descontar clase
                $clases_actualizadas = $clases_restantes - 1;
                $sql_update = "UPDATE membresias SET clases_restantes = ? WHERE id = ?";
                $stmt_update = $conexion->prepare($sql_update);
                $stmt_update->bind_param("ii", $clases_actualizadas, $membresia['id']);
                $stmt_update->execute();

                // Registrar asistencia
                $sql_asistencia = "INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, ?, ?)";
                $stmt_asistencia = $conexion->prepare($sql_asistencia);
                $stmt_asistencia->bind_param("iss", $cliente_id, $fecha_actual, $hora_actual);
                $stmt_asistencia->execute();

                echo "<div style='color: #0f0; font-size: 20px;'>✅ Ingreso registrado: {$cliente['nombre']} {$cliente['apellido']}<br>Clases restantes: $clases_actualizadas<br>Válido hasta: $fecha_vencimiento</div>";
            } else {
                echo "<div style='color: red; font-size: 20px;'>❌ No se encontró membresía activa o no tiene clases disponibles.</div>";
            }
        } else {
            echo "<div style='color: red; font-size: 20px;'>❌ No se encontró membresía registrada para este cliente.</div>";
        }
    } else {
        echo "<div style='color: red; font-size: 20px;'>❌ Cliente no encontrado.</div>";
    }
} else {
    echo "<form method='POST' style='text-align: center; margin-top: 50px;'>
        <input type='text' name='dni' placeholder='Escaneá el DNI o escribilo' autofocus style='font-size: 24px; padding: 10px;'>
        <br><br>
        <button type='submit' style='font-size: 20px;'>Registrar Ingreso</button>
    </form>";
}
?>
