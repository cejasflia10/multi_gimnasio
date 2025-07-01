<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;

// DEBUG opcional
// echo "<pre>GIMNASIO ID: $gimnasio_id</pre>";

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
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        .formulario { max-width: 500px; margin: auto; background: #111; padding: 20px; border-radius: 10px; border: 1px solid gold; }
        label, select, input { display: block; width: 100%; margin-top: 10px; }
        select, input[type="file"], input[type="submit"] {
            background: #222; color: gold; border: 1px solid gold; padding: 10px; border-radius: 5px;
        }
        input[type="submit"] {
            background: gold; color: black; font-weight: bold; cursor: pointer;
        }
    </style>
</head>
<body>

<div class="formulario">
    <h2>📄 Subir Rutina / Archivo</h2>
    <form action="guardar_rutina.php" method="POST" enctype="multipart/form-data">
        <label for="cliente_id">Alumno:</label>
        <select name="cliente_id" required>
            <option value="">-- Elegir alumno --</option>
            <?php while ($c = $alumnos->fetch_assoc()): ?>
                <option value="<?= $c['id'] ?>"><?= $c['apellido'] . ', ' . $c['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label for="archivo">Archivo (PDF, JPG, PNG, DOC):</label>
        <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>

        <input type="submit" value="Subir">
    </form>
</div>

</body>
</html>
