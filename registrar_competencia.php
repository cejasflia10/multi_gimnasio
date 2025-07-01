<?php
session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

// Obtener alumnos del gimnasio del profesor
$alumnos = $conexion->query("
    SELECT id, apellido, nombre
    FROM clientes
    WHERE gimnasio_id = $gimnasio_id
    ORDER BY apellido
");

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $fecha = $_POST['fecha'];
    $nombre = $_POST['nombre_competencia'];
    $lugar = $_POST['lugar'];
    $resultado = $_POST['resultado'];
    $obs = $_POST['observaciones'];

    $stmt = $conexion->prepare("INSERT INTO competencias (profesor_id, cliente_id, nombre_competencia, lugar, fecha, resultado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $profesor_id, $cliente_id, $nombre, $lugar, $fecha, $resultado, $obs);
    $stmt->execute();
    $mensaje = "✅ Competencia registrada correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Competencia</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { background: #000; color: gold; font-family: Arial, sans-serif; padding: 20px; }
        .formulario {
            max-width: 600px; margin: auto; background: #111; padding: 20px;
            border-radius: 10px; border: 1px solid gold;
        }
        h2 { text-align: center; margin-bottom: 20px; }
        label, select, input, textarea {
            display: block; width: 100%; margin-top: 10px;
        }
        select, input[type='text'], input[type='date'], textarea {
            background: #222; color: gold; border: 1px solid gold;
            padding: 10px; border-radius: 5px;
        }
        input[type="submit"] {
            background: gold; color: black; font-weight: bold; cursor: pointer;
            border: none; padding: 12px; margin-top: 15px;
        }
        .mensaje { text-align: center; margin-top: 10px; color: lime; }
    </style>
</head>
<body>

<div class="formulario">
    <h2>🏆 Registrar Competencia</h2>
    <?php if (!empty($mensaje)): ?>
        <div class="mensaje"><?= $mensaje ?></div>
    <?php endif; ?>
    <form method="POST">
        <label for="cliente_id">Alumno:</label>
        <select name="cliente_id" required>
            <option value="">-- Elegir alumno --</option>
            <?php while ($a = $alumnos->fetch_assoc()): ?>
                <option value="<?= $a['id'] ?>"><?= $a['apellido'] . ', ' . $a['nombre'] ?></option>
            <?php endwhile; ?>
        </select>

        <label for="nombre_competencia">Nombre de la Competencia:</label>
        <input type="text" name="nombre_competencia" required>

        <label for="lugar">Lugar:</label>
        <input type="text" name="lugar" required>

        <label for="fecha">Fecha:</label>
        <input type="date" name="fecha" required>

        <label for="resultado">Resultado:</label>
        <input type="text" name="resultado" required>

        <label for="observaciones">Observaciones:</label>
        <textarea name="observaciones" rows="4"></textarea>

        <input type="submit" value="Registrar Competencia">
    </form>
</div>

</body>
</html>
