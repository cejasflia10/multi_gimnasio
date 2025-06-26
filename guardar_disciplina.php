<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php'; // Asegurate de tener este archivo con tu conexión

// Validar que se recibió el dato
if (isset($_POST['nombre']) && !empty($_POST['nombre'])) {
    $nombre = trim($_POST['nombre']);

    // Escapar caracteres para evitar SQL injection
    $nombre = $conexion->real_escape_string($nombre);

    // Insertar en la tabla disciplinas
    $sql = "INSERT INTO disciplinas (nombre) VALUES ('$nombre')";

    if ($conexion->query($sql) === TRUE) {
        echo "<p style='color: lime;'>Disciplina guardada correctamente.</p>";
        echo "<a href='ver_disciplinas.php' style='color: gold;'>Volver</a>";
    } else {
        echo "<p style='color: red;'>Error: " . $conexion->error . "</p>";
    }
} else {
    echo "<p style='color: red;'>Debe ingresar el nombre de la disciplina.</p>";
}
?>
