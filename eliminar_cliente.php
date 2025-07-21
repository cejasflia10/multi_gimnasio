<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

if (isset($_GET['id'])) {
    $cliente_id = intval($_GET['id']);

    // Verificar que el cliente pertenece al gimnasio
    $verificar = $conexion->prepare("SELECT id FROM clientes WHERE id = ? AND gimnasio_id = ?");
    $verificar->bind_param("ii", $cliente_id, $gimnasio_id);
    $verificar->execute();
    $verificar->store_result();

    if ($verificar->num_rows === 0) {
        echo "<div style='background:#111; color:gold; padding:30px; font-family:Arial; text-align:center;'>
                <h2>❌ Cliente no encontrado</h2>
                <p>Este cliente no pertenece a tu gimnasio.</p>
                <a href='ver_clientes.php' style='color:gold; font-weight:bold;'>🔙 Volver</a>
              </div>";
        exit;
    }

    // 🔄 1. Eliminar asistencias primero
    $conexion->query("DELETE FROM asistencias WHERE cliente_id = $cliente_id");

    // 🔄 2. Eliminar membresías
    $conexion->query("DELETE FROM membresias WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

    // 🔄 3. Eliminar pagos
    $conexion->query("DELETE FROM pagos WHERE cliente_id = $cliente_id AND gimnasio_id = $gimnasio_id");

    // 🔄 4. Eliminar reservas (si la tabla existe y no tiene gimnasio_id)
    @$conexion->query("DELETE FROM reservas WHERE cliente_id = $cliente_id");

    // 🔄 5. Finalmente eliminar el cliente
    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ? AND gimnasio_id = ?");
    $stmt->bind_param("ii", $cliente_id, $gimnasio_id);

    if ($stmt->execute()) {
        header("Location: ver_clientes.php?mensaje=Cliente eliminado correctamente");
        exit();
    } else {
        echo "<div style='background:#111; color:red; padding:30px; font-family:Arial; text-align:center;'>
                <h2>❌ Error</h2>
                <p>No se pudo eliminar el cliente. Detalles: {$stmt->error}</p>
                <a href='ver_clientes.php' style='color:red; font-weight:bold;'>🔙 Volver</a>
              </div>";
    }

    $stmt->close();
} else {
    echo "ID de cliente no válido.";
}
?>
