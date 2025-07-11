<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include 'conexion.php';
include 'menu_profesor.php';

$profesor_id = $_SESSION['profesor_id'] ?? 0;
$gimnasio_id = $_SESSION['gimnasio_id'] ?? 0;
if ($profesor_id == 0) die("Acceso denegado.");

$alumnos = $conexion->query("
    SELECT id, apellido, nombre
    FROM clientes
    WHERE gimnasio_id = $gimnasio_id
    ORDER BY apellido
");

$mensaje = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $peso = $_POST['peso'];
    $altura = $_POST['altura'];
    $remera = $_POST['talle_remera'];
    $pantalon = $_POST['talle_pantalon'];
    $calzado = $_POST['talle_calzado'];
    $observaciones = $_POST['observaciones'];
    $fecha = date('Y-m-d');

    $stmt = $conexion->prepare("INSERT INTO datos_fisicos (profesor_id, cliente_id, fecha, peso, altura, talle_remera, talle_pantalon, talle_calzado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssssss", $profesor_id, $cliente_id, $fecha, $peso, $altura, $remera, $pantalon, $calzado, $observaciones);
    $stmt->execute();
    $mensaje = "✅ Datos físicos registrados correctamente.";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registrar Datos Físicos</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="estilo_unificado.css">
</head>
<body>
<div class="contenedor">
<div class="formulario">
    <h2>📋 Registrar Datos Físicos</h2>
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

        <label for="peso">Peso (kg):</label>
        <input type="text" name="peso" required>

        <label for="altura">Altura (cm):</label>
        <input type="text" name="altura" required>

        <label for="talle_remera">Talle Remera:</label>
        <input type="text" name="talle_remera">

        <label for="talle_pantalon">Talle Pantalón:</label>
        <input type="text" name="talle_pantalon">

        <label for="talle_calzado">Talle Calzado:</label>
        <input type="text" name="talle_calzado">

        <label for="observaciones">Observaciones:</label>
        <textarea name="observaciones" rows="4"></textarea>

        <input type="submit" value="Guardar Datos Físicos">
    </form>
</div>
</div>
</body>
</html>
