<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Asistencias del Día</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
    <style>
        input[type="date"] {
            background: #111; color: gold; border: 1px solid gold;
            padding: 8px; font-size: 16px; border-radius: 5px;
        }
        .boton-exportar {
            background: gold; color: black; padding: 8px 15px;
            text-decoration: none; border-radius: 5px;
            display: inline-block; margin-top: 10px;
        }
    </style>
</head>
<body>
<div class="contenedor">
    <h2>📋 Asistencias del Día - <?= date("d/m/Y", strtotime($fecha)) ?></h2>

    <form method="GET">
        <label for="fecha">Filtrar por fecha:</label>
        <input type="date" id="fecha" name="fecha" value="<?= $fecha ?>" onchange="this.form.submit()">
    </form>

    <a class="boton-exportar" href="exportar_asistencias_excel.php?fecha=<?= $fecha ?>">⬇ Exportar a Excel</a>

    <h3>👥 Clientes</h3>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Hora Ingreso</th></tr>
        <?php if ($clientes_q->num_rows > 0): ?>
            <?php while ($c = $clientes_q->fetch_assoc()): ?>
                <tr><td><?= $c['apellido'] ?></td><td><?= $c['nombre'] ?></td><td><?= $c['hora'] ?></td></tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="3">Sin registros</td></tr>
        <?php endif; ?>
    </table>

    <h3>👨‍🏫 Profesores</h3>
    <table>
        <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
        <?php if ($profesores_q->num_rows > 0): ?>
            <?php while ($p = $profesores_q->fetch_assoc()): ?>
                <tr>
                    <td><?= $p['apellido'] ?></td>
                    <td><?= $p['nombre'] ?></td>
                    <td><?= $p['hora_ingreso'] ?></td>
                    <td><?= $p['hora_egreso'] ?? '—' ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="4">Sin registros</td></tr>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
