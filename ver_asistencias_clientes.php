<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Asistencias del Mes</title>
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<script src="fullscreen.js"></script>

<body>
<div class="contenedor">
<h2>📅 Asistencias del Mes - <?= date('F Y') ?></h2>

<div class="tabla-contenedor">
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Apellido</th>
                <th>Disciplina</th>
                <th>Fecha</th>
                <th>Hora</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($resultado->num_rows > 0): ?>
                <?php while ($row = $resultado->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td><?= htmlspecialchars($row['apellido']) ?></td>
                        <td><?= htmlspecialchars($row['disciplina']) ?></td>
                        <td><?= htmlspecialchars($row['fecha']) ?></td>
                        <td><?= htmlspecialchars($row['hora']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">Sin asistencias registradas este mes.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<a href='index.php' class='volver'>← Volver al Menú</a>
</div>
</body>
</html>
