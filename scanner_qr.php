<?php
include 'conexion.php';

if (!empty($_POST['dni'])) {
    $dni = trim($_POST['dni']);
    $stmt = $conexion->prepare("SELECT c.id, c.nombre, c.apellido, c.disciplina, m.clases_disponibles, m.fecha_vencimiento 
                                FROM clientes c 
                                JOIN membresias m ON c.id = m.cliente_id 
                                WHERE c.dni = ? AND m.fecha_vencimiento >= CURDATE() AND m.clases_disponibles > 0 
                                ORDER BY m.fecha_vencimiento DESC LIMIT 1");
    $stmt->bind_param("s", $dni);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($fila = $resultado->fetch_assoc()) {
        $cliente_id = $fila['id'];
        $clases = $fila['clases_disponibles'] - 1;
        $update = $conexion->prepare("UPDATE membresias SET clases_disponibles = ? WHERE cliente_id = ?");
        $update->bind_param("ii", $clases, $cliente_id);
        $update->execute();

        $insert = $conexion->prepare("INSERT INTO asistencias (cliente_id, fecha, hora) VALUES (?, CURDATE(), CURTIME())");
        $insert->bind_param("i", $cliente_id);
        $insert->execute();

        echo "<p>✅ Ingreso registrado</p>";
        echo "<p><strong>{$fila['nombre']} {$fila['apellido']}</strong></p>";
        echo "<p>Disciplina: {$fila['disciplina']}</p>";
        echo "<p>Clases restantes: {$clases}</p>";
        echo "<p>Válido hasta: {$fila['fecha_vencimiento']}</p>";
    } else {
        echo "<p style='color:red;'>❌ No se encontró membresía activa.</p>";
    }
}
?>
