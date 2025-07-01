<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'conexion.php';
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
$resultado = $conexion->query("SELECT * FROM planes WHERE gimnasio_id = $gimnasio_id");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Planes del Gimnasio</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background-color: #111;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            color: gold;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background-color: #222;
        }
        a, button {
            background: gold;
            color: black;
            padding: 6px 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }
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
<h1>Planes del Gimnasio</h1>
<a href="agregar_plan.php">Crear nuevo plan</a>
<a href="index.php">Volver al menú</a>

<table>
    <tr>
        <th>Nombre</th>
        <th>Precio</th>
        <th>Clases Disponibles</th>
        <th>Días Disponibles</th>
        <th>Duración (meses)</th>
        <th>Acciones</th>
    </tr>
    <?php while ($fila = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $fila['nombre'] ?></td>
            <td>$<?= number_format($fila['precio'], 2, ',', '.') ?></td>
            <td><?= $fila['clases_disponibles'] ?></td>
            <td><?= $fila['dias_disponibles'] ?></td>
            <td><?= $fila['duracion'] ?></td>
            <td>
                <a href="agregar_plan.php?id=<?= $fila['id'] ?>">Editar</a>
                <a href="eliminar_plan.php?id=<?= $fila['id'] ?>" onclick="return confirm('¿Eliminar este plan?')">Eliminar</a>
            </td>
        </tr>
    <?php endwhile; ?>
</table>
</body>
</html>
