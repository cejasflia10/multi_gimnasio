<?php
include 'conexion.php';

$resultado = $conexion->query("
    SELECT t.id, t.dia, h.hora_inicio, h.hora_fin, p.apellido AS profesor, t.cupo_maximo
    FROM turnos t
    JOIN horarios h ON t.id_horario = h.id
    JOIN profesores p ON t.id_profesor = p.id
    ORDER BY FIELD(t.dia, 'Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'), h.hora_inicio
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver Turnos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            background: #000;
            color: gold;
            font-family: Arial, sans-serif;
            padding: 20px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border: 1px solid gold;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #222;
        }
        a.boton {
            background: gold;
            color: black;
            padding: 6px 12px;
            text-decoration: none;
            font-weight: bold;
            border-radius: 6px;
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

<h1>📋 Lista de Turnos</h1>

<table>
    <thead>
        <tr>
            <th>Día</th>
            <th>Horario</th>
            <th>Profesor</th>
            <th>Cupo Máximo</th>
            <th>Acción</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($t = $resultado->fetch_assoc()): ?>
        <tr>
            <td><?= $t['dia'] ?></td>
            <td><?= $t['hora_inicio'] ?> - <?= $t['hora_fin'] ?></td>
            <td><?= $t['profesor'] ?></td>
            <td><?= $t['cupo_maximo'] ?></td>
            <td><a class="boton" href="editar_turno.php?id=<?= $t['id'] ?>">Editar</a></td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>

</body>
</html>
