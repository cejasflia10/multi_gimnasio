<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

$alumnos = $conexion->query("
    SELECT id, apellido, nombre
    FROM clientes
    WHERE gimnasio_id = $gimnasio_id
    ORDER BY apellido
");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Graduación</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">

</head>
<body>
<div class="contenedor">
<div class="formulario">
    <h2>🎓 Registrar Graduación del Alumno</h2>
    <form action="guardar_graduacion.php" method="POST">
        <label for="cliente_id">Alumno:</label>
        <select name="cliente_id" required>
            <option value="">-- Elegir alumno --</option>
            <?php while ($c = $alumnos->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label for="fecha">Fecha:</label>
        <input type="date" name="fecha" required>

        <label for="nivel">Nivel / Graduación:</label>
        <input type="text" name="nivel" required>

        <input type="submit" value="Registrar Graduación">
    </form>
</div>
</div>
</body>
</html>
