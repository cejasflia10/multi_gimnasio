<?php
include 'conexion.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Intentar eliminar el cliente
    $stmt = $conexion->prepare("DELETE FROM clientes WHERE id = ?");
    $stmt->bind_param("i", $id);

    try {
        if ($stmt->execute()) {
            header("Location: ver_clientes.php");
            exit();
        } else {
            throw new Exception("No se pudo eliminar.");
        }
    } catch (mysqli_sql_exception $e) {
        // Error por clave foránea: mostrar mensaje
        echo "<div style='background:#111; color:gold; padding:30px; font-family:Arial; text-align:center;'>
                <h2>⚠️ No se puede eliminar el cliente</h2>
                <p>Este cliente tiene asistencias registradas. Elimine primero sus asistencias si desea borrarlo completamente.</p>
                <a href='ver_clientes.php' style='color:gold; display:inline-block; margin-top:20px; font-weight:bold;'>🔙 Volver</a>
              </div>";
    }

    $stmt->close();
} else {
    echo "ID de cliente no válido.";
}
?>
