<?php
session_start();
if (!isset($_SESSION['gimnasio_id'])) {
    header('Location: login.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nueva Disciplina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { background-color: #111; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        form { max-width: 400px; margin: auto; }
        input[type="text"] { width: 100%; padding: 10px; margin-top: 10px; background-color: #222; color: white; border: 1px solid gold; }
        input[type="submit"] { background-color: gold; color: black; padding: 10px 20px; margin-top: 20px; font-weight: bold; border: none; cursor: pointer; }
    </style>
</head>
<script>
// Reactivar pantalla completa con el primer clic
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', e => e.preventDefault());

// Bloquear combinaciones como F12, Ctrl+Shift+I
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
</script>

<body>
    <h2 style="text-align:center;">Agregar Nueva Disciplina</h2>
    <form action="guardar_disciplina.php" method="POST">
        <label>Nombre de la Disciplina:</label>
        <input type="text" name="nombre" required>
        <input type="submit" value="Guardar">
    </form>
</body>
</html>
