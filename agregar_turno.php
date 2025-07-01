<?php
include 'conexion.php';
include 'menu_horizontal.php';

$dias = $conexion->query("SELECT * FROM dias");
$horarios = $conexion->query("SELECT * FROM horarios");
$profesores = $conexion->query("SELECT * FROM profesores");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Agregar Turno</title>
    <style>
        body { background-color: #111; color: #f1c40f; font-family: Arial; }
        form { max-width: 400px; margin: auto; padding: 20px; background: #222; border-radius: 10px; }
        label, select, input { display: block; width: 100%; margin-bottom: 15px; }
        button { background: #f1c40f; border: none; padding: 10px; width: 100%; color: #000; font-weight: bold; cursor: pointer; }
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
    <form action="guardar_turno.php" method="POST">
        <h2>Nuevo Turno</h2>
        
        <label>Día:</label>
        <select name="id_dia" required>
            <?php while($d = $dias->fetch_assoc()) echo "<option value='{$d['id']}'>{$d['nombre']}</option>"; ?>
        </select>

        <label>Horario:</label>
        <select name="id_horario" required>
            <?php while($h = $horarios->fetch_assoc()) echo "<option value='{$h['id']}'>{$h['rango']}</option>"; ?>
        </select>

        <label>Profesor:</label>
        <select name="id_profesor" required>
            <?php while($p = $profesores->fetch_assoc()) echo "<option value='{$p['id']}'>{$p['apellido']} {$p['nombre']}</option>"; ?>
        </select>

        <label>Cupo máximo:</label>
        <input type="number" name="cupo_maximo" value="20" min="1">

        <button type="submit">Guardar Turno</button>
    </form>
</body>
</html>
