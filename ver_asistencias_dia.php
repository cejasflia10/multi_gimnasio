<?php
session_start();
include 'conexion.php';

$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if (!$gimnasio_id) {
    die("Acceso no autorizado.");
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');

// Convertimos fecha para mostrar en dd/mm/aaaa
$fecha_formateada = date('d/m/Y', strtotime($fecha));

// Consultas
$clientes_q = $conexion->query("
    SELECT c.apellido, c.nombre, a.hora
    FROM asistencias_clientes a
    JOIN clientes c ON a.cliente_id = c.id
    WHERE a.fecha = '$fecha' AND a.gimnasio_id = $gimnasio_id
    ORDER BY a.hora ASC
");

$profesores_q = $conexion->query("
    SELECT p.apellido, p.nombre, a.hora_ingreso, a.hora_egreso
    FROM asistencias_profesor a
    JOIN profesores p ON a.profesor_id = p.id
    WHERE a.fecha = '$fecha' AND a.gimnasio_id = $gimnasio_id
    ORDER BY a.hora_ingreso ASC
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📋 Asistencias del Día</title>
    <link rel="stylesheet" href="estilo_unificado.css">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
    <div class="contenedor">
    <h2>📋 Asistencias del Día - <?= $fecha_formateada ?></h2>

    <form method="GET" class="filtro">
        <label for="fecha">Filtrar por fecha:</label>
        <input type="date" name="fecha" id="fecha" value="<?= $fecha ?>">
        <input type="submit" value="Filtrar">
        <a href="exportar_asistencias_excel.php?fecha=<?= $fecha ?>" class="boton-exportar">⬇ Exportar a Excel</a>
    </form>

    <h3>👥 Clientes</h3>
    <table>
        <thead>
            <tr><th>Apellido</th><th>Nombre</th><th>Hora Ingreso</th></tr>
        </thead>
        <tbody>
            <?php if ($clientes_q && $clientes_q->num_rows > 0): ?>
                <?php while ($c = $clientes_q->fetch_assoc()): ?>
                    <tr>
                        <td><?= $c['apellido'] ?></td>
                        <td><?= $c['nombre'] ?></td>
                        <td><?= $c['hora'] ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="3">Sin registros</td></tr>
            <?php endif; ?>
        </tbody>
    </table>

    <h3>👨‍🏫 Profesores</h3>
    <table>
        <thead>
            <tr><th>Apellido</th><th>Nombre</th><th>Ingreso</th><th>Egreso</th></tr>
        </thead>
        <tbody>
            <?php if ($profesores_q && $profesores_q->num_rows > 0): ?>
                <?php while ($p = $profesores_q->fetch_assoc()): ?>
                    <tr>
                        <td><?= $p['apellido'] ?></td>
                        <td><?= $p['nombre'] ?></td>
                        <td><?= $p['hora_ingreso'] ?></td>
                        <td><?= $p['hora_egreso'] ?? '-' ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">Sin registros</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>
