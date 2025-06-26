<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

if (isset($_POST['nombre']) && !empty($_POST['nombre'])) {
    $nombre = trim($_POST['nombre']);
    $nombre = $conexion->real_escape_string($nombre);

    // Tomar el id del gimnasio desde la sesión
    $gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

    // Validar que haya gimnasio
    if ($gimnasio_id > 0) {
        $sql = "INSERT INTO disciplinas (nombre, id_gimnasio) VALUES ('$nombre', $gimnasio_id)";
        if ($conexion->query($sql) === TRUE) {
            echo "<p style='color: lime;'>Disciplina guardada correctamente.</p>";
            echo "<a href='ver_disciplinas.php' style='color: gold;'>Volver</a>";
        } else {
            echo "<p style='color: red;'>Error: " . $conexion->error . "</p>";
        }
    } else {
        echo "<p style='color: red;'>Gimnasio no identificado.</p>";
    }
} else {
    echo "<p style='color: red;'>Debe ingresar el nombre de la disciplina.</p>";
}
?>
