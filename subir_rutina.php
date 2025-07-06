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
    <title>Subir Rutina</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>

<div class="contenedor">
    <h2 class="titulo-seccion">📄 Subir Rutina / Archivo al Alumno</h2>

    <form action="guardar_rutina.php" method="POST" enctype="multipart/form-data" class="formulario">
        <div class="grupo-formulario">
            <label for="cliente_id">Alumno:</label>
            <select name="cliente_id" required>
                <option value="">-- Elegir alumno --</option>
                <?php while ($c = $alumnos->fetch_assoc()): ?>
                    <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="grupo-formulario">
            <label for="archivo">Archivo:</label>
            <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
        </div>

        <div class="grupo-formulario">
            <input type="submit" value="Subir Rutina" class="btn-principal">
        </div>
    </form>
</div>

</body>
</html>
